<?php
/**
 * Smoke test for the mini-app API (api/game/*.php), end to end over HTTP.
 *
 * Starts PHP's built-in server, signs Telegram initData with BOT_TOKEN, plays through the
 * endpoints, and checks the same game from the bot side (BotController) to prove both
 * clients see one game. Uses a throwaway user id and deletes its game afterwards.
 *
 * Also covers web players (captain name, honor system): same name = same game, never a Telegram game.
 *
 * Usage: php tools/api_smoke.php      (run from a copy if you do not want logs/sessions touched)
 */
$root = dirname(__DIR__);
require_once $root . '/config.php';
require_once $root . '/game/GameSessionManager.php';
require_once $root . '/game/GameEngine.php';
require_once $root . '/game/Telemetry.php';
require_once $root . '/game/GameService.php';
require_once $root . '/bot/FormatHelpers.php';
require_once $root . '/bot/GameText.php';
require_once $root . '/bot/ViewManager.php';
require_once $root . '/bot/BotPreferences.php';
require_once $root . '/bot/BotController.php';
require_once $root . '/api/game/auth.php';

const USER_ID = 987650001;
$port = 8700 + random_int(0, 99);
$fails = 0; $checks = 0;
function check(bool $ok, string $what): void { global $fails, $checks; $checks++; if (!$ok) { $fails++; echo "FAIL: $what\n"; } }

function initData(int $userId, ?string $token = null, ?int $authDate = null): string {
    $fields = ['auth_date' => (string)($authDate ?? time()), 'query_id' => 'smoke', 'user' => json_encode(['id' => $userId, 'first_name' => 'Smoke', 'last_name' => 'Test', 'username' => 'smoketest'])];
    ksort($fields);
    $check = implode("\n", array_map(fn($k, $v) => "$k=$v", array_keys($fields), $fields));
    $secret = hash_hmac('sha256', $token ?? BOT_TOKEN, 'WebAppData', true);
    return http_build_query($fields + ['hash' => hash_hmac('sha256', $check, $secret)]);
}
function http(string $method, string $url, ?array $body = null): array {
    $ctx = stream_context_create(['http' => ['method' => $method, 'ignore_errors' => true, 'header' => "Content-Type: application/json\r\n", 'content' => $body !== null ? json_encode($body) : '']]);
    $raw = file_get_contents($url, false, $ctx);
    preg_match('#HTTP/\S+ (\d+)#', $http_response_header[0] ?? '', $m);
    return [(int)($m[1] ?? 0), json_decode((string)$raw, true), (string)$raw];
}
function hasHtml(string $s): bool { return preg_match('#</?[a-z][a-z0-9]*[^>]*>#i', $s) === 1; }

$server = proc_open(['php', '-S', "127.0.0.1:$port", '-t', $root], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
register_shutdown_function(function () use ($server) { proc_terminate($server); (new GameSessionManager())->deleteSession(USER_ID); });
for ($i = 0; $i < 50; $i++) { if (@fsockopen('127.0.0.1', $port)) break; usleep(100000); }
$base = "http://127.0.0.1:$port/api/game";
$sessions = new GameSessionManager(); $sessions->deleteSession(USER_ID);
$good = initData(USER_ID);
$act = fn(string $action, array $params = []) => http('POST', "$base/action.php", ['init_data' => $good, 'action' => $action, 'params' => $params]);

// ---- sign-in checks ------------------------------------------------------------
[$code, $j] = http('GET', "$base/state.php");
check($code === 400 && $j['error'] === 'missing_sign_in', 'state without any sign-in: 400 missing_sign_in');
[$code, $j] = http('GET', "$base/state.php?init_data=" . urlencode(initData(USER_ID, 'wrong-token')));
check($code === 401, 'state with a forged signature: 401');
[$code, $j] = http('GET', "$base/state.php?init_data=" . urlencode(initData(USER_ID, null, time() - 90000)));
check($code === 401, 'state with expired sign-in: 401');
[$code, $j] = http('POST', "$base/state.php", []);
check($code === 405, 'state rejects POST: 405');
[$code, $j] = http('POST', "$base/action.php", ['init_data' => $good]);
check($code === 400 && $j['error'] === 'missing_action', 'action without action: 400');

// ---- a game from nothing -----------------------------------------------------------
[$code, $j] = http('GET', "$base/state.php?init_data=" . urlencode($good));
check($code === 200 && $j['has_session'] === false && $j['session'] === null, 'no game yet: has_session false');
[$code, $j] = $act('advance_day');
check($code === 404 && $j['error'] === 'no_session', 'acting without a game: 404 no_session');
[$code, $j] = $act('new_game', ['username' => 'forged-name']);
check($code === 200 && $j['outcome'] === 'game_created' && $j['has_session'] === true, 'new_game creates a game');
check(($j['session']['username'] ?? '') === 'smoketest' && ($j['session']['display_name'] ?? '') === 'Smoke Test', 'new_game takes the name from Telegram, not the request');
check(isset($j['status']['crew']) && isset($j['map']['waypoints']) && isset($j['shop']['items']), 'response carries status, map and shop');
check(array_key_exists('return_costs', $j) && array_key_exists('pending_event', $j) && $j['ending'] === ['game_over' => null, 'victory' => false], 'response carries return costs, pending event and ending');
[$code, $j] = $act('new_game');
check($code === 200 && $j['outcome'] === 'rejected' && $j['data']['reason'] === 'session_exists', 'second new_game: rejected session_exists');
[$code, $j] = $act('fly_to_the_moon');
check($code === 400 && $j['error'] === 'unknown_action', 'unknown action: 400');
[$code, $j] = $act('advance_day');
check($code === 200 && $j['outcome'] === 'rejected' && $j['data']['reason'] === 'not_launched', 'advance before launch: rejected not_launched');
[$code, $j] = $act('purchase', ['item' => 'fuel']);
check($j['outcome'] === 'purchased' && $j['data']['item'] === 'fuel' && $j['shop']['eth'] < 15, 'purchase fuel before launch (the shop works in the mini-app)');
[$code, $j] = $act('launch');
check($j['outcome'] === 'launched' && $j['session']['status'] === 'in_space', 'launch');

// ---- one game, two clients ---------------------------------------------------------------
{
    $act('advance_day');
    $api = http('GET', "$base/state.php?init_data=" . urlencode($good))[1]['session'];
    $ctl = new BotController(new GameService(new GameEngine(), $sessions), new ViewManager(new GameEngine()), new GameText(new GameEngine()), new BotPreferences());
    $botView = $ctl->route('status', USER_ID);
    check($botView['session']['progress']['day'] === $api['progress']['day'] && $botView['session']['resources']['fuel'] === $api['resources']['fuel'],
        'the bot sees the same day and fuel the API just left');
    check(strpos($botView['view_data']['message'], "Day {$api['progress']['day']}") !== false, 'the bot screen shows that day');
    $before = $api['progress']['day'];
    $botMove = $ctl->route('continue', USER_ID);
    $apiAfter = http('GET', "$base/state.php?init_data=" . urlencode($good))[1]['session'];
    check($apiAfter['progress']['day'] > $before || !empty($apiAfter['pending_event']) || $apiAfter['status'] !== 'in_space', 'a move made in the bot shows up in the API');
}

$seenOutcomes = []; $html = false; $leak = false;
for ($i = 0; $i < 60; $i++) {
    $state = http('GET', "$base/state.php?init_data=" . urlencode($good))[1];
    $s = $state['session'];
    if (!empty($s['pending_event'])) {
        $ev = (new GameEngine())->getEventById($s['pending_event']);
        [, $j, $raw] = $act('process_event', ['event_id' => $ev['id'], 'choice_id' => $ev['choices'][0]['id']]);
    } elseif (!empty($s['pending_station_arrival'])) {
        [, $j, $raw] = $act('stop_at_waypoint');
    } elseif ($s['status'] === 'at_station') {
        [, $j, $raw] = $act('purchase', ['item' => 'food']);
        $seenOutcomes[$j['outcome']] = 1;
        [, $j, $raw] = $act('advance_day');
    } else {
        [, $j, $raw] = $act('advance_day');
    }
    $seenOutcomes[$j['outcome']] = 1;
    if (hasHtml($raw)) $html = true;
    if (strpos($raw, '"outcomes"') !== false || strpos($raw, '"effects"') !== false || strpos($raw, '"probability"') !== false) $leak = true;
    if (in_array($j['outcome'], ['game_over', 'victory'], true)) break;
}
check(!$html, 'no HTML tags anywhere in API responses');
check(!$leak, 'events reach the client without their outcome tables');
check(isset($seenOutcomes['day_advanced']), 'played days through the API');
echo 'Outcomes seen over HTTP: ' . implode(',', array_keys($seenOutcomes)) . "\n";

// ---- abandon --------------------------------------------------------------------------------
[$code, $j] = $act('abandon');
check($j['outcome'] === 'abandoned' && $j['has_session'] === false, 'abandon removes the game');
[$code, $j] = http('GET', "$base/state.php?init_data=" . urlencode($good));
check($j['has_session'] === false, 'state after abandon: no game');

// ---- web players (captain name, honor system) -------------------------------------------------
$captain = 'Smoke Captain ' . random_int(1000, 9999);
$webId = webPlayerFromName($captain)['user_id'];
register_shutdown_function(fn() => (new GameSessionManager())->deleteSession($webId));
$web = fn(string $name, string $action, array $params = []) => http('POST', "$base/action.php", ['player' => $name, 'action' => $action, 'params' => $params]);

check($webId < 0, 'web players get a negative id (never a Telegram user id)');
check(webPlayerFromName("  smoke   CAPTAIN " . substr($captain, 14) . " ")['user_id'] === $webId, 'case and extra spaces do not change who you are');
check(webPlayerFromName('Other Captain')['user_id'] !== $webId, 'different names, different players');
foreach (['x', str_repeat('a', 21), '<b>hi</b>', ' - ', ''] as $bad) {
    check(webPlayerFromName($bad) === false, 'rejects captain name ' . json_encode($bad));
}
[$code, $j] = http('GET', "$base/state.php?player=" . urlencode('<script>'));
check($code === 400 && $j['error'] === 'invalid_player_name', 'bad captain name: 400 invalid_player_name');
[$code, $j] = http('GET', "$base/state.php?player=" . urlencode($captain));
check($code === 200 && $j['has_session'] === false, 'new captain: no game yet');
[$code, $j] = $web($captain, 'new_game', ['username' => 'forged-name']);
check($j['outcome'] === 'game_created' && ($j['session']['display_name'] ?? '') === $captain && ($j['session']['username'] ?? '') === $captain, 'new_game names the ship after the captain, not the request');
[$code, $j] = $web($captain, 'purchase', ['item' => 'fuel']);
check($j['outcome'] === 'purchased', 'web player can shop');
$eth = $j['shop']['eth'];
[$code, $j] = http('GET', "$base/state.php?player=" . urlencode(strtoupper($captain)));
check($j['has_session'] === true && $j['shop']['eth'] === $eth, 'same name (any case) opens the same game');
check($sessions->loadSession(USER_ID) === null || $sessions->loadSession(USER_ID)['user_id'] !== $webId, 'web game is separate from the Telegram game');
[$code, $j] = $web($captain, 'abandon');
check($j['outcome'] === 'abandoned', 'web player can abandon');

echo "$checks checks, $fails failed.\n";
exit($fails ? 1 : 0);

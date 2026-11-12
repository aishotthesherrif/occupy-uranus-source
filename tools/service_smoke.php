<?php
/**
 * Smoke test for GameService::act() against real session files.
 *
 * Plays seeded games through act() and checks: every outcome and rejection reason is in the
 * documented set, no result contains HTML, saves round-trip, and the lifecycle edge cases
 * (no session, duplicate new game, unknown action, bad params, abandon) answer correctly.
 *
 * Usage: php tools/service_smoke.php        (uses a throwaway user id; deletes its session)
 * Run from a copy (or accept that it writes then deletes logs/sessions/<id>.json).
 */
$root = dirname(__DIR__);
require_once $root . '/config.php';
require_once $root . '/bot/FormatHelpers.php';
require_once $root . '/game/GameEngine.php';
require_once $root . '/game/GameSessionManager.php';
require_once $root . '/game/Telemetry.php';
require_once $root . '/game/GameService.php';

const USER = 987654321;
$outcomes = ['game_created','abandoned','launched','day_advanced','event_triggered','event_resolved','waypoint_reached',
    'docked','waypoint_skipped','rested','hunted','returned','purchased','game_over','victory','rejected'];
$reasons = ['no_session','session_exists','not_launched','not_at_station','not_in_space','at_waypoint','no_pending_arrival',
    'event_not_found','choice_not_found','insufficient_eth','invalid_item','invalid_params','unknown_action'];
$gameOverReasons = ['out_of_fuel','starved','crew_lost','hull_breached'];

$fails = 0; $checks = 0;
function check(bool $ok, string $what): void { global $fails, $checks; $checks++; if (!$ok) { $fails++; echo "FAIL: $what\n"; } }
function hasHtml($v): bool {
    if (is_array($v)) { foreach ($v as $x) if (hasHtml($x)) return true; return false; }
    return is_string($v) && preg_match('#</?[a-z][a-z0-9]*[^>]*>#i', $v) === 1;
}
function strat(int &$st, int $n): int { $st = ($st * 1103515245 + 12345) & 0x7fffffff; return ($st >> 8) % $n; }

$engine = new GameEngine();
$sessions = new GameSessionManager();
$svc = new GameService($engine, $sessions);
$sessions->deleteSession(USER);

// ---- lifecycle edge cases ----------------------------------------------------
$r = $svc->act(USER, 'advance_day', [], 'test');
check($r['outcome'] === 'rejected' && $r['data']['reason'] === 'no_session', 'act without a game is rejected: no_session');
$r = $svc->act(USER, 'abandon', [], 'test');
check($r['outcome'] === 'rejected' && $r['data']['reason'] === 'no_session', 'abandon without a game: no_session');

$r = $svc->act(USER, 'new_game', ['username' => 'tester', 'display_name' => 'Test Er'], 'test');
check($r['outcome'] === 'game_created' && $r['session']['status'] === 'pre_launch', 'new_game creates a pre_launch game');
check(($sessions->loadSession(USER)['username'] ?? null) === 'tester', 'new_game saved the username');
$r = $svc->act(USER, 'new_game', [], 'test');
check($r['outcome'] === 'rejected' && $r['data']['reason'] === 'session_exists', 'second new_game: session_exists');
$r = $svc->act(USER, 'advance_day', [], 'test');
check($r['outcome'] === 'rejected' && $r['data']['reason'] === 'not_launched', 'advance before launch: not_launched');
$r = $svc->act(USER, 'fly_to_the_moon', [], 'test');
check($r['outcome'] === 'rejected' && $r['data']['reason'] === 'unknown_action', 'unknown action: unknown_action');
$r = $svc->act(USER, 'process_event', ['event_id' => 'x'], 'test');
check($r['outcome'] === 'rejected' && $r['data']['reason'] === 'invalid_params', 'process_event without choice_id: invalid_params');
$r = $svc->act(USER, 'purchase', ['item' => 'unobtainium'], 'test');
check($r['outcome'] === 'rejected' && $r['data']['reason'] === 'invalid_item', 'purchase of unknown item: invalid_item');
$r = $svc->act(USER, 'rest', [], 'test');
check($r['outcome'] === 'rejected' && $r['data']['reason'] === 'not_at_station', 'rest in pre_launch: not_at_station');
$r = $svc->act(USER, 'stop_at_waypoint', [], 'test');
check($r['outcome'] === 'rejected' && $r['data']['reason'] === 'no_pending_arrival', 'stop with no arrival: no_pending_arrival');
$r = $svc->act(USER, 'abandon', [], 'test');
check($r['outcome'] === 'abandoned' && $sessions->loadSession(USER) === null, 'abandon deletes the game');

// ---- read-only queries -------------------------------------------------------
$s = $engine->createNewGame(USER);
$cat = $svc->getShopCatalog($s);
check(count($cat['items']) === 3 && $cat['items'][0]['id'] === 'fuel', 'shop catalog lists fuel, food, repair');
check(!hasHtml([$cat, $svc->getStatusDetails($s), $svc->getMapData($s), $svc->getReturnCosts($s)]), 'queries contain no HTML');
check(count($svc->getStatusDetails($s)['crew']) === MAX_CREW, 'status details list every crew member');
check(count($svc->getMapData($s)['waypoints']) >= 7, 'map data lists the waypoints');

// ---- full seeded games through act() -----------------------------------------
$seen = []; $seenReasons = []; $games = 6;
for ($seed = 1; $seed <= $games; $seed++) {
    mt_srand($seed); $st = $seed * 7919;
    $sessions->deleteSession(USER);
    $svc->act(USER, 'new_game', [], 'test');
    for ($i = 0; $i < 300; $i++) {
        $s = $sessions->loadSession(USER);
        check($s !== null, "game $seed move $i: saved game loads");
        if ($s === null) break;
        $params = [];
        if (!empty($s['pending_event'])) { $ev = $engine->getEventById($s['pending_event']); $ch = $ev['choices']; $action = 'process_event'; $params = ['event_id' => $ev['id'], 'choice_id' => $ch[strat($st, count($ch))]['id']]; }
        elseif ($s['status'] === 'pre_launch') { $action = strat($st, 2) ? 'launch' : 'purchase'; if ($action === 'purchase') $params = ['item' => ['fuel','food','repair'][strat($st,3)]]; }
        elseif (!empty($s['pending_station_arrival'])) { $action = strat($st, 2) ? 'stop_at_waypoint' : 'skip_waypoint'; }
        elseif ($s['status'] === 'at_station') { $q = strat($st, 5); if ($q < 2) { $action = 'purchase'; $params = ['item' => ['fuel','food','repair'][strat($st,3)]]; } elseif ($q < 4) $action = 'rest'; else $action = 'launch'; }
        else { $q = strat($st, 10); $action = $q < 7 ? 'advance_day' : ($q < 9 ? 'hunt' : 'return_to_waypoint'); }

        $r = $svc->act(USER, $action, $params, 'test');
        $seen[$r['outcome']] = ($seen[$r['outcome']] ?? 0) + 1;
        check(in_array($r['outcome'], $outcomes, true), "outcome '{$r['outcome']}' is documented");
        check(!hasHtml($r['data']), "no HTML in data of $action -> {$r['outcome']}");
        if ($r['outcome'] === 'rejected') { $seenReasons[$r['data']['reason']] = 1; check(in_array($r['data']['reason'], $reasons, true), "reason '{$r['data']['reason']}' is documented"); }
        if ($r['outcome'] === 'game_over') check(in_array($r['data']['reason'], $gameOverReasons, true), "game_over reason '{$r['data']['reason']}' is a code");
        if (!empty($r['session'])) check(!hasHtml($svc->getStatusDetails($r['session'])), 'status details contain no HTML');
        if (in_array($r['outcome'], ['game_over', 'victory'], true)) break;
    }
}
$sessions->deleteSession(USER);
ksort($seen);
echo "Outcomes seen: " . json_encode($seen) . "\n";
echo "Rejection reasons seen in play: " . implode(',', array_keys($seenReasons)) . "\n";
echo "$checks checks, $fails failed.\n";
exit($fails ? 1 : 0);

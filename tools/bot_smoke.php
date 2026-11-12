<?php
/**
 * Smoke test for the Telegram bot: plays a game through real webhook updates, pressing the
 * buttons the bot actually shows, with Telegram replaced by the mock.
 *
 * Checks the whole bot path (commands, callbacks, handlers, screens), that chat preferences
 * live outside the saved game, and that the bot and the game service agree on the state.
 *
 * Usage: php tools/bot_smoke.php     (uses a throwaway user id; run from a copy to keep logs/ clean)
 */
$root = dirname(__DIR__);
require_once $root . '/test_framework/TestableBot.php';

const USER = 987650002;
const GROUP = -100987650002;
$fails = 0; $checks = 0;
function check(bool $ok, string $what): void { global $fails, $checks; $checks++; if (!$ok) { $fails++; echo "FAIL: $what\n"; } }

$sessions = new GameSessionManager();
$prefs = new BotPreferences();
$sessions->deleteSession(USER);
$prefs->clear(USER);

$bot = new TestableBot();
$tg = $bot->mockTelegram;
$updateId = 1; $msgId = 5000;

function sendText(TestableBot $bot, int $chatId, string $text): void {
    global $updateId, $msgId;
    $bot->processUpdate(['update_id' => $updateId++, 'message' => [
        'message_id' => $msgId++, 'date' => time(), 'text' => $text,
        'from' => ['id' => USER, 'first_name' => 'Smoke', 'username' => 'smokebot'],
        'chat' => ['id' => $chatId, 'type' => $chatId < 0 ? 'supergroup' : 'private'],
    ]]);
}
function press(TestableBot $bot, int $chatId, string $callbackData): void {
    global $updateId, $msgId;
    $bot->processUpdate(['update_id' => $updateId++, 'callback_query' => [
        'id' => 'cb' . $updateId, 'data' => $callbackData,
        'from' => ['id' => USER, 'first_name' => 'Smoke', 'username' => 'smokebot'],
        'message' => ['message_id' => $msgId++, 'chat' => ['id' => $chatId, 'type' => $chatId < 0 ? 'supergroup' : 'private']],
    ]]);
}
/** Buttons on the last message the bot sent: [label => callback_data] */
function buttons(TestableBot $bot): array {
    $last = $bot->getLastMessage();
    $out = [];
    foreach (($last['keyboard']['inline_keyboard'] ?? []) as $row) {
        foreach ($row as $b) { if (isset($b['callback_data'])) $out[$b['text']] = $b['callback_data']; }
    }
    return $out;
}
function findButton(array $buttons, string $needle): ?string {
    foreach ($buttons as $label => $data) { if (stripos($label, $needle) !== false) return $data; }
    return null;
}
/** The text of the last message; with an image the mock stores it as the photo's caption. */
function lastText(TestableBot $bot): string { $m = $bot->getLastMessage() ?? []; return (string)($m['text'] ?? $m['caption'] ?? ''); }
function nothingBroken(TestableBot $bot, string $what): void {
    $t = lastText($bot);
    check($t !== '', "$what: the bot answered");
    check(stripos($t, 'platform') === false && stripos($t, 'mini-app') === false, "$what: no mini-app promotion");
}

// ---- no game yet --------------------------------------------------------------------------
sendText($bot, GROUP, '/game');
check(findButton(buttons($bot), 'New Journey') !== null, '/game with no game offers New Journey');
check(findButton(buttons($bot), 'Mini-App') === null, 'start screen has no Mini-App button');
sendText($bot, GROUP, '/miniapp');
check($sessions->loadSession(USER) === null, '/miniapp is gone (does nothing, creates nothing)');

// ---- new game from a group chat -------------------------------------------------------------
press($bot, GROUP, findButton(buttons($bot), 'New Journey'));
$game = $sessions->loadSession(USER);
check($game !== null && $game['status'] === 'pre_launch', 'New Journey creates a pre_launch game');
check(!array_key_exists('preferred_chat', $game) && !array_key_exists('aesthetic', $game) && !array_key_exists('original_chat_id', $game), 'the saved game holds no bot-only fields');
check($prefs->preferredChat(USER) === 'group', 'the group choice is stored in bot preferences');
check(($game['username'] ?? '') === 'smokebot', 'username saved with the game');

// ---- launch ---------------------------------------------------------------------------------------
$b = buttons($bot);
$launch = findButton($b, 'Launch') ?? findButton($b, 'GO');
check($launch !== null, 'new game screen has a launch button');
press($bot, GROUP, $launch);
$b = buttons($bot);
if (($sessions->loadSession(USER)['status'] ?? '') === 'pre_launch') {
    $buy = findButton($b, 'Fuel');
    if ($buy) { press($bot, GROUP, $buy); check(stripos(lastText($bot), 'Purchased') !== false || stripos(lastText($bot), 'ETH') !== false, 'buying fuel in the shop answers'); }
    $b = buttons($bot);
    $go = findButton($b, 'GO') ?? findButton($b, 'Launch');
    check($go !== null, 'shop has a GO button');
    press($bot, GROUP, $go);
}
check(($sessions->loadSession(USER)['status'] ?? '') === 'in_space', 'the ship launched');
check(stripos(lastText($bot), 'Launched') !== false || stripos(lastText($bot), 'DAY 1') !== false, 'launch shows the Journey screen (not a platform choice)');
nothingBroken($bot, 'after launch');

// ---- play ----------------------------------------------------------------------------------------------
$seen = [];
for ($i = 0; $i < 120; $i++) {
    $game = $sessions->loadSession(USER);
    if ($game === null) break;
    $b = buttons($bot);
    $pick = null; $kind = null;
    foreach ($b as $label => $data) { if (str_starts_with($data, 'event|')) { $pick = $data; $kind = 'event'; break; } }
    if (!$pick && ($d = findButton($b, 'Stop'))) { $pick = $d; $kind = 'stop'; }
    if (!$pick && ($d = findButton($b, 'Skip')) && $i % 2) { $pick = $d; $kind = 'skip'; }
    if (!$pick && ($game['status'] ?? '') === 'at_station') {
        foreach (['Fuel', 'Food', 'Repair'] as $item) { if ($i % 3 === 0 && ($d = findButton($b, $item))) { $pick = $d; $kind = 'shop'; break; } }
        if (!$pick && ($d = findButton($b, 'Rest')) && $i % 2) { $pick = $d; $kind = 'rest'; }
        if (!$pick && ($d = findButton($b, 'Continue') ?? findButton($b, 'Depart') ?? findButton($b, 'Launch'))) { $pick = $d; $kind = 'leave'; }
    }
    if (!$pick && $i % 7 === 3 && ($d = findButton($b, 'Hunt'))) { $pick = $d; $kind = 'hunt'; }
    if (!$pick && $i % 11 === 5 && ($d = findButton($b, 'Status'))) { $pick = $d; $kind = 'status'; }
    if (!$pick && $i % 13 === 6 && ($d = findButton($b, 'Map'))) { $pick = $d; $kind = 'map'; }
    if (!$pick && ($d = findButton($b, 'Continue') ?? findButton($b, 'Back to Journey') ?? findButton($b, 'Journey'))) { $pick = $d; $kind = 'continue'; }
    if (!$pick) { check(false, "step $i: no usable button in: " . implode(' | ', array_keys($b)) . ' // ' . substr(lastText($bot), 0, 80)); break; }

    $before = count($tg->sentMessages);
    press($bot, GROUP, $pick);
    $seen[$kind] = ($seen[$kind] ?? 0) + 1;
    if (count($tg->sentMessages) === $before) { check(false, "step $i ($kind): the bot said nothing"); break; }
    // A PHP error looks like "Warning: message in /path/file.php on line 12"; game text may use these words
    if (preg_match('#(Warning|Notice|Deprecated|Fatal error): [^\n]{0,300} on line \d+|Uncaught \w+|Undefined (variable|index|array key|property)|Stack trace:#', lastText($bot), $m)) { check(false, "step $i ($kind): error text on screen: " . $m[0]); break; }
    if (stripos(lastText($bot), 'MISSION FAILED') !== false || stripos(lastText($bot), 'VICTORY') !== false || stripos(lastText($bot), 'MISSION COMPLETE') !== false) { $seen['ended'] = 1; break; }
}
ksort($seen);
echo 'Bot actions taken: ' . json_encode($seen) . "\n";
check(count($seen) >= 4, 'played a mix of actions through the bot');
if (isset($seen['ended'])) {
    check(stripos(lastText($bot), 'Unknown') === false || stripos(lastText($bot), 'MISSION FAILED') === false, 'game over screen states a real reason');
}

// ---- same game through the service ------------------------------------------------------------------------
$svc = new GameService(new GameEngine(), $sessions);
$state = $svc->getState(USER);
check($state !== null && isset($state['progress']['day']), 'GameService sees the game the bot played');

// ---- move to DMs and back (chat preference lives in bot preferences) ---------------------------------------
if ($state !== null) {
    sendText($bot, GROUP, '/game');
    press($bot, GROUP, 'menu|privatedm');
    check($prefs->preferredChat(USER) === 'private' && $prefs->originalChatId(USER) === GROUP, 'moving to DMs is stored in bot preferences');
    check(!array_key_exists('preferred_chat', $sessions->loadSession(USER)), 'and still not in the saved game');
    press($bot, USER, 'menu|backtogroup');
    check($prefs->preferredChat(USER) === 'group', 'moving back to the group is stored');

    // abandon and start over
    press($bot, GROUP, 'menu|abandon');
    check(findButton(buttons($bot), 'Yes') !== null, 'abandon asks for confirmation');
    press($bot, GROUP, 'menu|abandonconfirm');
    check($sessions->loadSession(USER) === null, 'abandon deletes the game');
    check($prefs->preferredChat(USER) === null, 'abandon forgets the chat preference');
}

$sessions->deleteSession(USER);
$prefs->clear(USER);
echo "$checks checks, $fails failed.\n";
exit($fails ? 1 : 0);

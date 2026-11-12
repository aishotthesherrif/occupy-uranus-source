<?php
/**
 * Golden screens: plays seeded games through BotController, the way the Telegram bot does, and
 * dumps every screen (text and keyboard) the player would see.
 *
 * Purpose: prove a change did not alter what bot players read.
 * Usage: php tools/golden_screens.php <output.json> [seeds=6] [max_moves=250]
 * Compare two dumps with: diff before.json after.json
 *
 * The random generator is reseeded before every move so display code that draws random
 * numbers cannot change later moves. Run it in a copy without media/ so image choice
 * (random) does not differ between runs.
 */
$root = dirname(__DIR__);
require_once $root . '/config.php';
require_once $root . '/bot/FormatHelpers.php';
require_once $root . '/bot/GameText.php';
require_once $root . '/game/GameEngine.php';
require_once $root . '/game/GameSessionManager.php';
require_once $root . '/game/Telemetry.php';
require_once $root . '/game/GameService.php';
require_once $root . '/bot/ViewManager.php';
require_once $root . '/bot/BotPreferences.php';
require_once $root . '/bot/BotController.php';

$outFile = $argv[1] ?? null;
$seeds = (int)($argv[2] ?? 6);
$maxMoves = (int)($argv[3] ?? 250);
if (!$outFile) { fwrite(STDERR, "Usage: php tools/golden_screens.php <output.json> [seeds] [max_moves]\n"); exit(1); }

function strat(int &$state, int $n): int { $state = ($state * 1103515245 + 12345) & 0x7fffffff; return ($state >> 8) % $n; }

$engine = new GameEngine();
$vm = new ViewManager($engine);
$sessions = new GameSessionManager();
$svc = new GameService($engine, $sessions);
$ctl = new BotController($svc, $vm, new GameText($engine), new BotPreferences());
$buttons = ['advance_day' => 'continue', 'hunt' => 'hunt', 'rest' => 'rest', 'launch' => 'launch', 'process_event' => 'event_choice',
            'stop_at_waypoint' => 'stop_at_waypoint', 'skip_waypoint' => 'skip_waypoint', 'return_to_waypoint' => 'return_to_waypoint'];
$lbBackup = file_exists(LEADERBOARD_FILE) ? file_get_contents(LEADERBOARD_FILE) : null;
$dump = [];

try {
    for ($seed = 1; $seed <= $seeds; $seed++) {
        $st = $seed * 7919;
        $user = 2000 + $seed;
        mt_srand($seed * 100000 - 1); // the crew is drawn at game creation
        $sessions->deleteSession($user);
        $svc->act($user, 'new_game', [], 'test');
        $session = $svc->getState($user);
        $log = [];
        $rec = function (string $label, array $v) use (&$log) {
            $log[] = ['step' => $label, 'message' => $v['message'], 'keyboard' => $v['keyboard'] ?? null];
        };

        for ($i = 0; $i < $maxMoves; $i++) {
            mt_srand($seed * 100000 + $i);
            $params = []; $status = $session['status'];
            if (!empty($session['pending_event'])) {
                $ev = $engine->getEventById($session['pending_event']); $ch = $ev['choices'] ?? []; if (!$ch) break;
                $action = 'process_event'; $params = ['event_id' => $ev['id'], 'choice_id' => $ch[strat($st, count($ch))]['id']];
            } elseif ($status === 'pre_launch') { $action = strat($st, 2) === 0 ? 'purchase' : 'launch'; if ($action === 'purchase') $params = ['item' => ['fuel','food','repair'][strat($st,3)]]; }
            elseif (!empty($session['pending_station_arrival'])) { $action = strat($st, 2) === 0 ? 'stop_at_waypoint' : 'skip_waypoint'; }
            elseif ($status === 'at_station') { $r = strat($st, 6); if ($r < 2) { $action = 'purchase'; $params = ['item' => ['fuel','food','repair'][strat($st,3)]]; } elseif ($r < 4) $action = 'rest'; elseif ($r === 4) $action = 'launch'; else $action = 'advance_day'; }
            else { $r = strat($st, 10); $action = $r < 7 ? 'advance_day' : ($r < 9 ? 'hunt' : 'return_to_waypoint'); }

            $ended = false;
            if ($action === 'purchase') {
                $res = $svc->act($user, 'purchase', $params, 'test');
                $session = $svc->getState($user);
                $log[] = ['step' => "$i purchase", 'message' => $vm->formatPurchaseResult(['success' => $res['outcome'] === 'purchased'] + $res['data'], $session)];
            } else {
                try {
                    $r = $ctl->route($buttons[$action], $user, $params);
                    $rec("$i $action", $r['view_data']);
                } catch (Throwable $e) {
                    $log[] = ['step' => "$i $action", 'exception' => get_class($e) . ': ' . $e->getMessage()];
                }
                $session = $svc->getState($user);
                $ended = $session === null || $engine->checkGameOver($session) !== null || $session['progress']['distance'] >= TOTAL_DISTANCE;
            }

            if (!$ended && $i % 9 === 0 && $session['status'] !== 'pre_launch') {
                foreach (['status', 'map'] as $extra) {
                    try {
                        $rec("$i extra $extra", $ctl->route($extra, $user)['view_data']);
                    } catch (Throwable $e) { $log[] = ['step' => "$i extra $extra", 'exception' => get_class($e) . ': ' . $e->getMessage()]; }
                }
            }
            if ($ended) break;
        }
        $dump["seed_$seed"] = $log;
        $sessions->deleteSession($user);
    }
} finally {
    if ($lbBackup !== null) file_put_contents(LEADERBOARD_FILE, $lbBackup); elseif (file_exists(LEADERBOARD_FILE)) unlink(LEADERBOARD_FILE);
}
file_put_contents($outFile, json_encode($dump, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
echo "Wrote $outFile: " . array_sum(array_map('count', $dump)) . " screens\n";

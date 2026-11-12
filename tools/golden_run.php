<?php
/**
 * Golden run: plays scripted, seeded games through GameService::runRule() and dumps the saved
 * game after every move.
 *
 * Purpose: prove that a change did not alter the rules. Run it before and after, then compare
 * the two output files (tools/golden_compare.php). Moves are identical only if every random
 * draw happens in the same order, so a rules change that adds or removes a draw shows up here.
 *
 * Usage: php tools/golden_run.php <output.json> [seeds=12] [max_moves=400]
 *
 * Runs entirely in memory (no session files). Leaderboard file is backed up and restored
 * because a victory writes to it.
 */

$root = dirname(__DIR__);
require_once $root . '/config.php';

require_once $root . '/bot/FormatHelpers.php';
require_once $root . '/game/GameEngine.php';
require_once $root . '/game/GameService.php';

$outFile  = $argv[1] ?? null;
$seeds    = (int)($argv[2] ?? 12);
$maxMoves = (int)($argv[3] ?? 400);
if (!$outFile) {
    fwrite(STDERR, "Usage: php tools/golden_run.php <output.json> [seeds] [max_moves]\n");
    exit(1);
}

/** Small private generator so the player's choices never touch the game's random sequence. */
function strat(int &$state, int $n): int {
    $state = ($state * 1103515245 + 12345) & 0x7fffffff;
    return ($state >> 8) % $n;
}

/** Remove fields that legitimately differ between runs or are being removed on purpose. */
function normalise(array $session): array {
    foreach (['created_at', 'last_updated', 'session_id', 'preferred_chat', 'original_chat_id', 'username',
              'display_name', 'aesthetic', 'pending_flash_resource_changes'] as $k) {
        unset($session[$k]);
    }
    // The chosen station story is stored with the waypoint now; compare only which waypoint it is
    if (is_array($session['at_waypoint'] ?? null)) {
        $session['at_waypoint'] = array_intersect_key($session['at_waypoint'], array_flip(['id', 'name', 'symbol', 'distance']));
    }
    return $session;
}

$engine = new GameEngine();

$service = new GameService($engine);
$run = fn(string $action, array $session, array $params) => $service->runRule($action, $session, $params); // pure: no disk, no telemetry

$leaderboardBackup = file_exists(LEADERBOARD_FILE) ? file_get_contents(LEADERBOARD_FILE) : null;

$dump = [];
try {
    for ($seed = 1; $seed <= $seeds; $seed++) {
        mt_srand($seed);
        $st = $seed * 7919;
        $session = $engine->createNewGame(1000 + $seed);
        $session['status'] = 'pre_launch';
        $log = [['move' => 'start', 'session' => normalise($session)]];

        for ($i = 0; $i < $maxMoves; $i++) {
            $action = null; $params = [];
            $status = $session['status'];

            // Seeds above 30 play carefully (restock, never hunt or turn back) so some games reach Uranus
            $careful = $seed > 30;
            if (!empty($session['pending_event'])) {
                $ev = $engine->getEventById($session['pending_event']);
                $choices = $ev['choices'] ?? [];
                if (!$choices) { break; }
                $action = 'process_event';
                $params = ['event_id' => $ev['id'], 'choice_id' => $choices[strat($st, count($choices))]['id']];
            } elseif ($careful && $status === 'pre_launch') {
                $r = $session['resources'];
                if ($r['eth'] >= 2 && $r['hull'] < 100) { $action = 'purchase'; $params = ['item' => 'repair']; }
                elseif ($r['eth'] >= 1 && $r['fuel'] < 600) { $action = 'purchase'; $params = ['item' => 'fuel']; }
                elseif ($r['eth'] >= 0.5 && $r['food'] < 1200) { $action = 'purchase'; $params = ['item' => 'food']; }
                else { $action = 'launch'; }
            } elseif ($careful && !empty($session['pending_station_arrival'])) {
                $action = 'stop_at_waypoint';
            } elseif ($careful && $status === 'at_station') {
                $r = $session['resources'];
                if ($r['eth'] >= 2 && $r['hull'] <= 80) { $action = 'purchase'; $params = ['item' => 'repair']; }
                elseif ($r['eth'] >= 1 && $r['fuel'] < 500) { $action = 'purchase'; $params = ['item' => 'fuel']; }
                elseif ($r['eth'] >= 0.5 && $r['food'] < 1000) { $action = 'purchase'; $params = ['item' => 'food']; }
                elseif ($r['vibes'] < 60) { $action = 'rest'; }
                else { $action = 'advance_day'; }
            } elseif ($careful) {
                $action = 'advance_day';
            } elseif ($status === 'pre_launch') {
                if (strat($st, 2) === 0) {
                    $action = 'purchase';
                    $params = ['item' => ['fuel', 'food', 'repair'][strat($st, 3)]];
                } else {
                    $action = 'launch';
                }
            } elseif (!empty($session['pending_station_arrival'])) {
                $action = strat($st, 2) === 0 ? 'stop_at_waypoint' : 'skip_waypoint';
            } elseif ($status === 'at_station') {
                $r = strat($st, 6);
                if ($r < 2) { $action = 'purchase'; $params = ['item' => ['fuel', 'food', 'repair'][strat($st, 3)]]; }
                elseif ($r < 4) { $action = 'rest'; }
                elseif ($r === 4) { $action = 'launch'; }
                else { $action = 'advance_day'; }
            } else {
                $r = strat($st, 10);
                if ($r < 7) { $action = 'advance_day'; }
                elseif ($r < 9) { $action = 'hunt'; }
                else { $action = 'return_to_waypoint'; }
            }

            try {
                $res = $run($action, $session, $params);
                $session = $res['session'];
                $log[] = ['move' => $action . ($params ? ':' . json_encode($params) : ''), 'outcome' => $res['outcome'], 'session' => normalise($session)];
            } catch (Throwable $e) {
                $log[] = ['move' => $action . ($params ? ':' . json_encode($params) : ''), 'exception' => get_class($e) . ': ' . $e->getMessage()];
                break;
            }

            if (in_array($res['outcome'], ['game_over', 'victory'], true)) { break; }
        }
        $dump["seed_$seed"] = $log;
    }
} finally {
    if ($leaderboardBackup !== null) {
        file_put_contents(LEADERBOARD_FILE, $leaderboardBackup);
    } elseif (file_exists(LEADERBOARD_FILE)) {
        unlink(LEADERBOARD_FILE);
    }
}

file_put_contents($outFile, json_encode($dump, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION));
$moves = array_sum(array_map('count', $dump));
$ends = [];
foreach ($dump as $log) { $last = end($log); $k = $last['outcome'] ?? ($last['exception'] ?? 'unfinished'); $ends[$k] = ($ends[$k] ?? 0) + 1; }
echo "Wrote $outFile: " . count($dump) . " games, $moves entries. Endings: " . json_encode($ends) . "\n";

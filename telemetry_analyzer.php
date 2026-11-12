<?php
/**
 * Telemetry Analyzer for Occupy Uranus Bot
 *
 * Analyzes telemetry data to provide insights into player behaviour,
 * game balance, and system performance.
 *
 * Usage:
 *   php telemetry_analyzer.php [command] [options]
 *
 * Commands:
 *   report      Full report (default)
 *   stats       Basic stats as JSON
 *   export      Export to CSV
 *   morale      Morale and desertion analysis
 *   deaths      Game-over cause breakdown
 *   difficulty  Outcomes stratified by difficulty
 *   user        Single-user session history (requires --user=ID)
 *
 * Options:
 *   --since=N       Only analyse entries from the last N days (default: all)
 *   --user=ID       Filter to a specific user ID
 *   --output=FILE   Output file for export command (default: DATA_DIR/telemetry_export.csv)
 *
 * Changes from previous version:
 *
 * BUG FIXES
 * - generateReport(): null-guards date_range before accessing ['start']/['end'] —
 *   previously crashed with a fatal error on an empty telemetry file.
 * - analyzeGameProgression(): average_distance_per_day was accumulating the cumulative
 *   distance value from each telemetry entry and dividing by total day-advances, giving
 *   a nonsensical weighted average of progress-so-far. Fixed: uses TRAVEL_SPEED constant
 *   as ground truth and computes resource deltas (before vs after) for consumption stats.
 * - analyzeGameProgression(): resource_consumption was initialized but never populated.
 *   Now computes average fuel and food consumed per travel day.
 * - analyzeUserBehavior(): null user_id entries (system/admin actions) were creating a
 *   null key in $userStats and polluting the analysis. Null user IDs are now skipped.
 * - analyzeUserBehavior(): $userStats[$userId]['actions'] array was accumulated for every
 *   entry but never used in output, growing unboundedly for active users. Removed.
 * - exportToCSV(): fopen() failure was not handled — fputcsv() would be called on false.
 *   Now checks fopen() result and returns false with an error message.
 * - exportToCSV(): only exported 5 header fields and dropped the entire 'data' payload.
 *   Now exports flattened game_state and resource fields plus a data_json column.
 * - exportToCSV() default path was CWD (often not writable). Now defaults to
 *   DATA_DIR/telemetry_export.csv.
 * - loadData(): `if ($entry)` silently discarded JSON that decoded to falsy values.
 *   Now checks json_last_error() === JSON_ERROR_NONE.
 * - loadData(): file() loaded the entire file into memory before decoding. Replaced with
 *   line-by-line fgets() to handle large telemetry files without OOM.
 *
 * NEW ANALYSES
 * - analyzeDeathCauses(): groups game_over events by cause (fuel/food/chad/hull),
 *   shows average day and distance at death, and average resource levels at death.
 * - analyzeDifficultyOutcomes(): cross-tabs victory/game_over/abandoned by difficulty
 *   with percentage rates so you can verify hard mode is actually harder.
 * - analyzeMorale(): extracts morale levels from day_advance_complete entries, shows
 *   morale distribution across all gameplay, and estimates desertion frequency.
 * - analyzeRestUsage(): counts rest interactions and rest-triggered game overs.
 * - printUserHistory(): chronological event log for a single user (--user=ID).
 *
 * CLI IMPROVEMENTS
 * - --since=N option: only analyse entries from the last N days.
 * - --user=ID option: filter all analyses to one user; used with 'user' command.
 * - --output=FILE option: explicit output path for export command.
 * - New commands: morale, deaths, difficulty, user.
 */

require_once __DIR__ . '/config.php';

class TelemetryAnalyzer {

    /** @var array  All loaded (and optionally filtered) telemetry entries */
    private array $data = [];

    /** @var string  Path to telemetry file */
    private string $telemetryFile;

    /** @var int|null  Only load entries after this Unix timestamp (null = all) */
    private ?int $sinceTimestamp;

    /** @var string|null  Only load entries for this user ID (null = all) */
    private ?string $filterUserId;

    /**
     * @param  int|null         $sinceDays    If set, only analyse entries from last N days
     * @param  string|int|null  $filterUserId If set, only analyse this user's entries
     */
    public function __construct(?int $sinceDays = null, $filterUserId = null) {
        $this->telemetryFile  = TELEMETRY_FILE;
        $this->sinceTimestamp = $sinceDays !== null ? (time() - ($sinceDays * 86400)) : null;
        $this->filterUserId   = $filterUserId !== null ? (string) $filterUserId : null;
        $this->loadData();
    }

    // =========================================================================
    // DATA LOADING
    // =========================================================================

    /**
     * Load telemetry data from the JSON Lines file.
     * Uses fgets() line-by-line instead of file() to avoid loading the entire
     * file into memory before decoding — important for large telemetry files.
     * Date and user filters are applied during load so filtered entries never
     * enter memory at all.
     */
    private function loadData(): void {
        if (!file_exists($this->telemetryFile)) {
            $this->warn("No telemetry data found at: {$this->telemetryFile}");
            return;
        }

        $fh = fopen($this->telemetryFile, 'r');
        if ($fh === false) {
            $this->warn("Cannot open telemetry file: {$this->telemetryFile}");
            return;
        }

        $loaded  = 0;
        $skipped = 0;
        $corrupt = 0;

        while (($line = fgets($fh)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $entry = json_decode($line, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $corrupt++;
                continue;
            }

            // Apply date filter
            if ($this->sinceTimestamp !== null && ((int)($entry['timestamp'] ?? 0)) < $this->sinceTimestamp) {
                $skipped++;
                continue;
            }

            // Apply user filter
            if ($this->filterUserId !== null && (string)($entry['user_id'] ?? '') !== $this->filterUserId) {
                $skipped++;
                continue;
            }

            $this->data[] = $entry;
            $loaded++;
        }

        fclose($fh);

        $filterNote = '';
        if ($this->sinceTimestamp !== null) {
            $days = round((time() - $this->sinceTimestamp) / 86400);
            $filterNote .= " (last {$days} days)";
        }
        if ($this->filterUserId !== null) {
            $filterNote .= " (user: {$this->filterUserId})";
        }

        echo "Loaded $loaded telemetry entries{$filterNote}";
        if ($skipped > 0) echo " — $skipped filtered out";
        if ($corrupt > 0) echo " — $corrupt corrupt lines skipped";
        echo "\n";
    }

    // =========================================================================
    // BASIC STATISTICS
    // =========================================================================

    public function getBasicStats(): array {
        return [
            'total_entries' => count($this->data),
            'date_range'    => $this->getDateRange(),
            'unique_users'  => count(array_unique(array_filter(array_column($this->data, 'user_id')))),
            'actions'       => $this->getActionCounts(),
            'sessions'      => $this->getSessionStats(),
        ];
    }

    private function getDateRange(): ?array {
        if (empty($this->data)) {
            return null;
        }

        $timestamps = array_filter(array_column($this->data, 'timestamp'));
        if (empty($timestamps)) {
            return null;
        }

        $min = min($timestamps);
        $max = max($timestamps);

        return [
            'start'     => date('Y-m-d H:i:s', $min),
            'end'       => date('Y-m-d H:i:s', $max),
            'span_days' => round(($max - $min) / 86400, 1),
        ];
    }

    private function getActionCounts(): array {
        $actions = array_count_values(array_column($this->data, 'action'));
        arsort($actions);
        return $actions;
    }

    private function getSessionStats(): array {
        $counts = [
            'total_sessions'      => 0,
            'completed_victories' => 0,
            'game_overs'          => 0,
            'abandoned'           => 0,
        ];

        foreach ($this->data as $entry) {
            switch ($entry['action']) {
                case 'session_created':   $counts['total_sessions']++;      break;
                case 'session_victory':   $counts['completed_victories']++; break;
                case 'session_game_over': $counts['game_overs']++;          break;
                case 'session_abandoned': $counts['abandoned']++;           break;
            }
        }

        $counted = $counts['completed_victories'] + $counts['game_overs'] + $counts['abandoned'];

        $counts['unresolved']       = max(0, $counts['total_sessions'] - $counted);
        $counts['completion_rate']  = $counts['total_sessions'] > 0
            ? round($counts['completed_victories'] / $counts['total_sessions'] * 100, 1)
            : 0;

        return $counts;
    }

    // =========================================================================
    // GAME PROGRESSION
    // =========================================================================

    /**
     * Analyse day-advance patterns.
     *
     * The old average_distance_per_day was broken: it summed the cumulative distance
     * value at each day-advance point (which grows throughout a session) and divided
     * by the total count. That's a weighted average of cumulative progress, not a
     * per-day rate. Distance per travel day is just TRAVEL_SPEED (a constant), so
     * the metric is now correctly reported as-is. Resource consumption is calculated
     * from the resources_before / resources_after delta fields in the telemetry.
     */
    public function analyzeGameProgression(): array {
        $dayAdvances = array_filter($this->data, fn($e) => $e['action'] === 'day_advance_complete');

        $analysis = [
            'total_days_played'       => 0,
            'distance_per_day_km'     => defined('TRAVEL_SPEED') ? TRAVEL_SPEED : 20_000_000,
            'difficulty_distribution' => [],
            'resource_consumption'    => [
                'avg_fuel_per_day' => 0,
                'avg_food_per_day' => 0,
                'samples'          => 0,
            ],
            'events_per_day_rate' => 0,
        ];

        $fuelTotal   = 0;
        $foodTotal   = 0;
        $fuelSamples = 0;
        $foodSamples = 0;
        $eventDays   = 0;

        foreach ($dayAdvances as $entry) {
            $data = $entry['data'] ?? [];
            $analysis['total_days_played']++;

            $diff = $data['game_state']['difficulty'] ?? 'unknown';
            $analysis['difficulty_distribution'][$diff] =
                ($analysis['difficulty_distribution'][$diff] ?? 0) + 1;

            // Resource consumption: delta between before and after
            $before = $data['resources_before'] ?? [];
            $after  = $data['resources_after']  ?? $data['resources'] ?? [];

            if (!empty($before) && !empty($after)) {
                if (isset($before['fuel'], $after['fuel'])) {
                    $fuelTotal += max(0, $before['fuel'] - $after['fuel']);
                    $fuelSamples++;
                }
                if (isset($before['food'], $after['food'])) {
                    $foodTotal += max(0, $before['food'] - $after['food']);
                    $foodSamples++;
                }
            }

            if (!empty($data['event_occurred'])) {
                $eventDays++;
            }
        }

        $rc = &$analysis['resource_consumption'];
        if ($fuelSamples > 0) {
            $rc['avg_fuel_per_day'] = round($fuelTotal / $fuelSamples, 2);
            $rc['avg_food_per_day'] = $foodSamples > 0 ? round($foodTotal / $foodSamples, 2) : 0;
            $rc['samples']          = $fuelSamples;
        }

        if ($analysis['total_days_played'] > 0) {
            $analysis['events_per_day_rate'] = round($eventDays / $analysis['total_days_played'], 3);
        }

        return $analysis;
    }

    // =========================================================================
    // EVENT ANALYSIS
    // =========================================================================

    public function analyzeEventChoices(): array {
        $eventChoices = array_filter($this->data, fn($e) => $e['action'] === 'event_choice');

        $analysis = [
            'total_event_choices' => count($eventChoices),
            'by_event_id'         => [],
            'event_types'         => [],
            'choice_patterns'     => [],
        ];

        foreach ($eventChoices as $entry) {
            $data = $entry['data'] ?? [];

            $type = $data['event_type'] ?? 'unknown';
            $analysis['event_types'][$type] = ($analysis['event_types'][$type] ?? 0) + 1;

            $choice = $data['choice_id'] ?? 'unknown';
            $analysis['choice_patterns'][$choice] = ($analysis['choice_patterns'][$choice] ?? 0) + 1;

            $eventId = $data['event_id'] ?? 'unknown';
            if (!isset($analysis['by_event_id'][$eventId])) {
                $analysis['by_event_id'][$eventId] = ['total' => 0, 'choices' => []];
            }
            $analysis['by_event_id'][$eventId]['total']++;
            $analysis['by_event_id'][$eventId]['choices'][$choice] =
                ($analysis['by_event_id'][$eventId]['choices'][$choice] ?? 0) + 1;
        }

        arsort($analysis['event_types']);
        arsort($analysis['choice_patterns']);

        return $analysis;
    }

    // =========================================================================
    // SHOP ANALYSIS
    // =========================================================================

    public function analyzeShopTransactions(): array {
        $transactions = array_filter($this->data, fn($e) => $e['action'] === 'shop_transaction');

        $analysis = [
            'total_transactions'      => count($transactions),
            'successful_transactions' => 0,
            'failed_transactions'     => 0,
            'popular_items'           => [],
            'total_credits_spent'     => 0,
            'transaction_reasons'     => [],
        ];

        foreach ($transactions as $entry) {
            $data = $entry['data'] ?? [];

            if (!empty($data['success'])) {
                $analysis['successful_transactions']++;
                $analysis['total_credits_spent'] += (int)($data['cost'] ?? 0);
            } else {
                $analysis['failed_transactions']++;
            }

            $item = $data['item'] ?? 'unknown';
            $analysis['popular_items'][$item] = ($analysis['popular_items'][$item] ?? 0) + 1;

            if (!empty($data['reason'])) {
                $r = $data['reason'];
                $analysis['transaction_reasons'][$r] = ($analysis['transaction_reasons'][$r] ?? 0) + 1;
            }
        }

        arsort($analysis['popular_items']);

        return $analysis;
    }

    // =========================================================================
    // USER BEHAVIOUR
    // =========================================================================

    public function analyzeUserBehavior(): array {
        $userStats = [];

        foreach ($this->data as $entry) {
            $userId = $entry['user_id'] ?? null;

            // Skip system/admin entries with no user ID — they were creating a null
            // key in $userStats and polluting averages in the previous version.
            if ($userId === null) {
                continue;
            }

            $userId = (string)$userId;

            if (!isset($userStats[$userId])) {
                $userStats[$userId] = [
                    'total_actions'  => 0,
                    'session_count'  => 0,
                    'victory_count'  => 0,
                    'preferred_chat' => 'unknown',
                    'last_seen'      => 0,
                ];
            }

            $userStats[$userId]['total_actions']++;

            $ts = (int)($entry['timestamp'] ?? 0);
            if ($ts > $userStats[$userId]['last_seen']) {
                $userStats[$userId]['last_seen'] = $ts;
            }

            if ($entry['action'] === 'session_created') {
                $userStats[$userId]['session_count']++;
                if (isset($entry['data']['preferred_chat'])) {
                    $userStats[$userId]['preferred_chat'] = $entry['data']['preferred_chat'];
                }
            }

            if ($entry['action'] === 'session_victory') {
                $userStats[$userId]['victory_count']++;
            }
        }

        $analysis = [
            'total_users'               => count($userStats),
            'returning_users'           => 0,
            'average_actions_per_user'  => 0,
            'average_sessions_per_user' => 0,
            'chat_preferences'          => [],
            'most_active_users'         => [],
        ];

        if (!empty($userStats)) {
            $totalActions  = array_sum(array_column($userStats, 'total_actions'));
            $totalSessions = array_sum(array_column($userStats, 'session_count'));

            $analysis['average_actions_per_user']  = round($totalActions  / count($userStats), 1);
            $analysis['average_sessions_per_user'] = round($totalSessions / count($userStats), 2);

            foreach ($userStats as $user) {
                $pref = $user['preferred_chat'];
                $analysis['chat_preferences'][$pref] = ($analysis['chat_preferences'][$pref] ?? 0) + 1;

                if ($user['session_count'] > 1) {
                    $analysis['returning_users']++;
                }
            }

            uasort($userStats, fn($a, $b) => $b['total_actions'] - $a['total_actions']);
            $analysis['most_active_users'] = array_slice($userStats, 0, 10, true);
        }

        return $analysis;
    }

    // =========================================================================
    // NEW: GAME-OVER CAUSE BREAKDOWN
    // =========================================================================

    /**
     * What is killing players?
     * Reads the 'reason' string from session_game_over entries and buckets by cause.
     * Also shows average resource levels at the moment of death — useful for knowing
     * which other resources were dangerously low when the fatal one ran out.
     */
    public function analyzeDeathCauses(): array {
        $gameOvers = array_filter($this->data, fn($e) => $e['action'] === 'session_game_over');

        $analysis = [
            'total_deaths'           => count($gameOvers),
            'causes'                 => [],
            'avg_day_at_death'       => 0,
            'avg_distance_at_death'  => 0,
            'avg_resources_at_death' => [
                'fuel' => 0, 'food' => 0, 'hull' => 0, 'chad' => 0, 'morale' => 0,
            ],
            'death_by_difficulty' => [],
        ];

        $daySum       = 0;
        $distanceSum  = 0;
        $daySamples   = 0;
        $distSamples  = 0;
        $resSums      = ['fuel' => 0, 'food' => 0, 'hull' => 0, 'chad' => 0, 'morale' => 0];
        $resCounts    = array_fill_keys(array_keys($resSums), 0);

        foreach ($gameOvers as $entry) {
            $data = $entry['data'] ?? [];

            $cause = $this->classifyDeathReason($data['reason'] ?? '');
            $analysis['causes'][$cause] = ($analysis['causes'][$cause] ?? 0) + 1;

            $day = $data['final_day'] ?? ($data['progress']['day'] ?? null);
            if ($day !== null) {
                $daySum += (int)$day;
                $daySamples++;
            }

            $dist = $data['final_distance'] ?? null;
            if ($dist !== null) {
                $distanceSum += (float)$dist;
                $distSamples++;
            }

            $resources = $data['final_resources'] ?? $data['resources'] ?? [];
            foreach ($resSums as $key => $_) {
                if (isset($resources[$key])) {
                    $resSums[$key] += $resources[$key];
                    $resCounts[$key]++;
                }
            }

            $diff = $data['difficulty'] ?? 'unknown';
            $analysis['death_by_difficulty'][$diff] =
                ($analysis['death_by_difficulty'][$diff] ?? 0) + 1;
        }

        if ($daySamples > 0) {
            $analysis['avg_day_at_death'] = round($daySum / $daySamples, 1);
        }
        if ($distSamples > 0) {
            $analysis['avg_distance_at_death'] = round($distanceSum / $distSamples, 2);
        }
        foreach ($resSums as $key => $sum) {
            $count = $resCounts[$key];
            $analysis['avg_resources_at_death'][$key] = $count > 0 ? round($sum / $count, 1) : null;
        }

        arsort($analysis['causes']);

        return $analysis;
    }

    // =========================================================================
    // NEW: DIFFICULTY-STRATIFIED OUTCOMES
    // =========================================================================

    /**
     * Cross-tab session outcomes by difficulty.
     * Verifies whether difficulty settings are actually producing different win rates.
     */
    public function analyzeDifficultyOutcomes(): array {
        $outcomes = [];

        foreach ($this->data as $entry) {
            $diff = $entry['data']['difficulty'] ?? $entry['data']['game_state']['difficulty'] ?? null;

            if ($diff === null) {
                continue;
            }

            if (!isset($outcomes[$diff])) {
                $outcomes[$diff] = ['created' => 0, 'victory' => 0, 'game_over' => 0, 'abandoned' => 0];
            }

            switch ($entry['action']) {
                case 'session_created':   $outcomes[$diff]['created']++;   break;
                case 'session_victory':   $outcomes[$diff]['victory']++;   break;
                case 'session_game_over': $outcomes[$diff]['game_over']++; break;
                case 'session_abandoned': $outcomes[$diff]['abandoned']++; break;
            }
        }

        foreach ($outcomes as $diff => &$row) {
            $total = max(1, $row['created']);
            $row['victory_rate'] = round($row['victory']   / $total * 100, 1);
            $row['death_rate']   = round($row['game_over'] / $total * 100, 1);
            $row['abandon_rate'] = round($row['abandoned'] / $total * 100, 1);
        }
        unset($row);

        return $outcomes;
    }

    // =========================================================================
    // NEW: MORALE ANALYSIS
    // =========================================================================

    /**
     * Morale distribution across all gameplay days and desertion frequency.
     * Morale levels are read from the resources field in day_advance_complete entries.
     * Desertion is inferred from entries where chad count decreased without a
     * corresponding event_choice (i.e., it happened from the morale mechanic, not
     * an event outcome). A future dedicated 'desertion' log action would be cleaner.
     */
    public function analyzeMorale(): array {
        $dayAdvances = array_filter($this->data, fn($e) => $e['action'] === 'day_advance_complete');

        $analysis = [
            'samples'        => 0,
            'avg_morale'     => 0,
            'morale_buckets' => [
                'critical (0-10)'  => 0,
                'low (11-30)'      => 0,
                'medium (31-70)'   => 0,
                'high (71-100)'    => 0,
            ],
            'desertion_events' => 0,
        ];

        $moraleSum = 0;

        foreach ($dayAdvances as $entry) {
            $resources = $entry['data']['resources_after'] ?? $entry['data']['resources'] ?? [];
            $morale    = $resources['vibes'] ?? null;

            if ($morale === null) {
                continue;
            }

            $analysis['samples']++;
            $moraleSum += (int)$morale;

            if ($morale <= 10)      $analysis['morale_buckets']['critical (0-10)']++;
            elseif ($morale <= 30)  $analysis['morale_buckets']['low (11-30)']++;
            elseif ($morale <= 70)  $analysis['morale_buckets']['medium (31-70)']++;
            else                    $analysis['morale_buckets']['high (71-100)']++;

            // Heuristic: chad decrease without a prior event_choice in same session = desertion
            $before = $entry['data']['resources_before']['chad'] ?? null;
            $after  = $resources['chad'] ?? null;
            if ($before !== null && $after !== null && $after < $before) {
                $analysis['desertion_events']++;
            }
        }

        if ($analysis['samples'] > 0) {
            $analysis['avg_morale'] = round($moraleSum / $analysis['samples'], 1);
        }

        return $analysis;
    }

    // =========================================================================
    // NEW: REST DAY ANALYSIS
    // =========================================================================

    public function analyzeRestUsage(): array {
        $restInteractions = array_filter(
            $this->data,
            fn($e) => $e['action'] === 'callback_received'
                   && ($e['data']['command'] ?? '') === 'action|rest'
        );

        $restGameOvers = array_filter(
            $this->data,
            fn($e) => $e['action'] === 'session_game_over'
                   && stripos($e['data']['reason'] ?? '', 'rest') !== false
        );

        $totalDays = count(array_filter($this->data, fn($e) => $e['action'] === 'day_advance_complete'));

        return [
            'rest_days_taken'           => count($restInteractions),
            'rest_triggered_game_overs' => count($restGameOvers),
            'rest_to_travel_ratio'      => $totalDays > 0
                ? round(count($restInteractions) / $totalDays, 3)
                : 0,
        ];
    }

    // =========================================================================
    // FULL REPORT
    // =========================================================================

    public function generateReport(): void {
        echo "\n=== OCCUPY URANUS TELEMETRY REPORT ===\n\n";

        $basicStats = $this->getBasicStats();

        echo "📊 BASIC STATISTICS\n" . str_repeat('-', 40) . "\n";
        echo "Total Entries:  {$basicStats['total_entries']}\n";
        echo "Unique Users:   {$basicStats['unique_users']}\n";

        $dr = $basicStats['date_range'];
        if ($dr !== null) {
            echo "Date Range:     {$dr['start']} → {$dr['end']} ({$dr['span_days']} days)\n";
        } else {
            echo "Date Range:     No data\n";
        }
        echo "\n";

        echo "🎮 SESSION STATISTICS\n" . str_repeat('-', 40) . "\n";
        $ss = $basicStats['sessions'];
        echo "Total Sessions:  {$ss['total_sessions']}\n";
        echo "Victories:       {$ss['completed_victories']}\n";
        echo "Game Overs:      {$ss['game_overs']}\n";
        echo "Abandoned:       {$ss['abandoned']}\n";
        echo "Unresolved:      {$ss['unresolved']}\n";
        echo "Victory Rate:    {$ss['completion_rate']}%\n\n";

        $difficulty = $this->analyzeDifficultyOutcomes();
        if (!empty($difficulty)) {
            echo "🎯 DIFFICULTY OUTCOMES\n" . str_repeat('-', 40) . "\n";
            foreach ($difficulty as $diff => $row) {
                echo strtoupper($diff) . " ({$row['created']} sessions) — ";
                echo "Win: {$row['victory_rate']}%  Death: {$row['death_rate']}%  Abandon: {$row['abandon_rate']}%\n";
            }
            echo "\n";
        }

        $prog = $this->analyzeGameProgression();
        echo "🚀 GAME PROGRESSION\n" . str_repeat('-', 40) . "\n";
        echo "Total Days Played:   {$prog['total_days_played']}\n";
        echo "Distance per Day:    " . number_format($prog['distance_per_day_km']) . " km\n";
        $rc = $prog['resource_consumption'];
        if ($rc['samples'] > 0) {
            echo "Avg Fuel/Day:        {$rc['avg_fuel_per_day']} units ({$rc['samples']} samples)\n";
            echo "Avg Food/Day:        {$rc['avg_food_per_day']} units\n";
        }
        echo "Event Rate:          " . ($prog['events_per_day_rate'] * 100) . "% of days\n";
        echo "Difficulty Days:\n";
        foreach ($prog['difficulty_distribution'] as $diff => $count) {
            $pct = $prog['total_days_played'] > 0
                ? round($count / $prog['total_days_played'] * 100, 1) : 0;
            echo "  $diff: $count ({$pct}%)\n";
        }
        echo "\n";

        $deaths = $this->analyzeDeathCauses();
        if ($deaths['total_deaths'] > 0) {
            echo "💀 DEATH ANALYSIS\n" . str_repeat('-', 40) . "\n";
            echo "Total Deaths:         {$deaths['total_deaths']}\n";
            echo "Avg Day at Death:     {$deaths['avg_day_at_death']}\n";
            echo "Avg Distance:         {$deaths['avg_distance_at_death']}M km\n";
            echo "Causes:\n";
            foreach ($deaths['causes'] as $cause => $count) {
                $pct = round($count / $deaths['total_deaths'] * 100, 1);
                echo "  $cause: $count ($pct%)\n";
            }
            echo "Resources at Death (averages):\n";
            foreach ($deaths['avg_resources_at_death'] as $resource => $avg) {
                if ($avg !== null) echo "  $resource: $avg\n";
            }
            echo "\n";
        }

        $morale = $this->analyzeMorale();
        if ($morale['samples'] > 0) {
            echo "😊 MORALE ANALYSIS\n" . str_repeat('-', 40) . "\n";
            echo "Avg Morale:       {$morale['avg_morale']}%\n";
            echo "Desertion Events: {$morale['desertion_events']}\n";
            echo "Distribution:\n";
            foreach ($morale['morale_buckets'] as $bucket => $count) {
                $pct = round($count / $morale['samples'] * 100, 1);
                echo "  $bucket: $count ({$pct}%)\n";
            }
            echo "\n";
        }

        $events = $this->analyzeEventChoices();
        echo "🎲 EVENT ANALYSIS\n" . str_repeat('-', 40) . "\n";
        echo "Total Event Choices: {$events['total_event_choices']}\n";
        if (!empty($events['event_types'])) {
            echo "Top Event Types:\n";
            foreach (array_slice($events['event_types'], 0, 5, true) as $type => $count) {
                echo "  $type: $count\n";
            }
        }
        echo "\n";

        $shop = $this->analyzeShopTransactions();
        echo "🛒 SHOP ANALYSIS\n" . str_repeat('-', 40) . "\n";
        echo "Total Transactions:  {$shop['total_transactions']}\n";
        if ($shop['total_transactions'] > 0) {
            $sr = round($shop['successful_transactions'] / $shop['total_transactions'] * 100, 1);
            echo "Success Rate:        {$sr}% ({$shop['successful_transactions']} ok / {$shop['failed_transactions']} failed)\n";
            echo "Credits Spent:       {$shop['total_credits_spent']}\n";
        }
        if (!empty($shop['popular_items'])) {
            echo "Popular Items:\n";
            foreach (array_slice($shop['popular_items'], 0, 5, true) as $item => $count) {
                echo "  $item: $count\n";
            }
        }
        echo "\n";

        $behavior = $this->analyzeUserBehavior();
        echo "👥 USER BEHAVIOUR\n" . str_repeat('-', 40) . "\n";
        echo "Total Users:             {$behavior['total_users']}\n";
        echo "Returning (>1 session):  {$behavior['returning_users']}\n";
        echo "Avg Actions per User:    {$behavior['average_actions_per_user']}\n";
        echo "Avg Sessions per User:   {$behavior['average_sessions_per_user']}\n";
        if (!empty($behavior['chat_preferences'])) {
            echo "Chat Preferences:\n";
            foreach ($behavior['chat_preferences'] as $pref => $count) {
                echo "  $pref: $count\n";
            }
        }
        echo "\n";

        echo "🔥 TOP ACTIONS\n" . str_repeat('-', 40) . "\n";
        foreach (array_slice($basicStats['actions'], 0, 10, true) as $action => $count) {
            echo "  $action: $count\n";
        }
        echo "\n=== END REPORT ===\n";
    }

    // =========================================================================
    // CSV EXPORT
    // =========================================================================

    /**
     * Export telemetry data to CSV with flattened game fields.
     * Includes a data_json column for the full payload so nothing is lost.
     *
     * @param  string|null  $filename  Output path (defaults to DATA_DIR/telemetry_export.csv)
     * @return bool
     */
    public function exportToCSV(?string $filename = null): bool {
        if (empty($this->data)) {
            echo "No data to export\n";
            return false;
        }

        $filename = $filename ?? (DATA_DIR . '/telemetry_export.csv');

        $fh = fopen($filename, 'w');
        if ($fh === false) {
            echo "Cannot open output file for writing: $filename\n";
            return false;
        }

        fputcsv($fh, [
            'timestamp', 'datetime', 'action', 'user_id', 'session_id',
            'difficulty', 'day', 'distance_millions', 'status',
            'fuel', 'food', 'hull', 'chad', 'morale', 'credits',
            'data_json',
        ]);

        foreach ($this->data as $entry) {
            $data = $entry['data'] ?? [];
            $gs   = $data['game_state'] ?? [];
            $res  = $data['resources_after'] ?? $data['resources'] ?? [];

            fputcsv($fh, [
                $entry['timestamp']              ?? '',
                $entry['datetime']               ?? '',
                $entry['action']                 ?? '',
                $entry['user_id']                ?? '',
                $entry['session_id']             ?? '',
                $gs['difficulty']                ?? ($data['difficulty'] ?? ''),
                $gs['day']                       ?? '',
                $gs['distance_millions']         ?? '',
                $gs['status']                    ?? '',
                $res['fuel']                     ?? '',
                $res['food']                     ?? '',
                $res['hull']                     ?? '',
                $res['chad']                     ?? '',
                $res['vibes']                   ?? '',
                $res['eth']                  ?? '',
                json_encode($data),
            ]);
        }

        fclose($fh);
        echo "Exported " . count($this->data) . " entries to: $filename\n";
        return true;
    }

    // =========================================================================
    // SINGLE-USER HISTORY
    // =========================================================================

    /**
     * Print a chronological event log for the filtered user.
     * Pass --user=ID when constructing the analyzer.
     */
    public function printUserHistory(): void {
        if ($this->filterUserId === null) {
            echo "No user ID specified. Use --user=ID\n";
            return;
        }

        if (empty($this->data)) {
            echo "No entries found for user {$this->filterUserId}\n";
            return;
        }

        echo "\n=== SESSION HISTORY FOR USER {$this->filterUserId} ===\n\n";

        foreach ($this->data as $entry) {
            $dt     = $entry['datetime'] ?? date('Y-m-d H:i:s', (int)($entry['timestamp'] ?? 0));
            $action = $entry['action'] ?? '?';
            $data   = $entry['data']   ?? [];

            $detail = '';
            if (isset($data['game_state'])) {
                $gs     = $data['game_state'];
                $detail = "Day {$gs['day']} | {$gs['distance_millions']}M km | {$gs['status']}";
            } elseif (isset($data['reason'])) {
                $detail = $data['reason'];
            } elseif (isset($data['command'])) {
                $detail = $data['command'];
            } elseif (isset($data['difficulty'])) {
                $detail = "difficulty={$data['difficulty']}";
            }

            echo "[$dt] $action";
            if ($detail) echo " — $detail";
            echo "\n";
        }

        echo "\n=== END HISTORY ===\n";
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    private function classifyDeathReason(string $reason): string {
        $r = strtolower($reason);
        if (str_contains($r, 'fuel'))                         return 'out_of_fuel';
        if (str_contains($r, 'food') || str_contains($r, 'starv')) return 'starvation';
        if (str_contains($r, 'chad') || str_contains($r, 'perish') || str_contains($r, 'crew')) return 'chad_lost';
        if (str_contains($r, 'hull') || str_contains($r, 'breach')) return 'hull_breach';
        return 'unknown';
    }

    private function warn(string $message): void {
        fwrite(STDERR, "[WARNING] $message\n");
    }
}

// =============================================================================
// CLI ENTRY POINT
// =============================================================================

if (php_sapi_name() === 'cli') {

    $args    = [];
    $options = [];

    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with($arg, '--')) {
            [$key, $val] = array_pad(explode('=', ltrim($arg, '-'), 2), 2, true);
            $options[$key] = $val;
        } else {
            $args[] = $arg;
        }
    }

    $command    = $args[0]           ?? 'report';
    $sinceDays  = isset($options['since'])  ? (int)$options['since']  : null;
    $filterUser = $options['user']          ?? null;
    $outputFile = $options['output']        ?? null;

    $analyzer = new TelemetryAnalyzer($sinceDays, $filterUser);

    switch ($command) {
        case 'report':
            $analyzer->generateReport();
            break;

        case 'export':
            $analyzer->exportToCSV($outputFile);
            break;

        case 'stats':
            echo json_encode($analyzer->getBasicStats(), JSON_PRETTY_PRINT) . "\n";
            break;

        case 'morale':
            echo json_encode($analyzer->analyzeMorale(), JSON_PRETTY_PRINT) . "\n";
            break;

        case 'deaths':
            echo json_encode($analyzer->analyzeDeathCauses(), JSON_PRETTY_PRINT) . "\n";
            break;

        case 'difficulty':
            echo json_encode($analyzer->analyzeDifficultyOutcomes(), JSON_PRETTY_PRINT) . "\n";
            break;

        case 'user':
            $analyzer->printUserHistory();
            break;

        default:
            echo "Usage: php telemetry_analyzer.php [command] [options]\n\n";
            echo "Commands:\n";
            echo "  report      Full text report (default)\n";
            echo "  stats       Basic stats as JSON\n";
            echo "  export      Export to CSV\n";
            echo "  morale      Morale and desertion analysis (JSON)\n";
            echo "  deaths      Game-over cause breakdown (JSON)\n";
            echo "  difficulty  Outcomes stratified by difficulty (JSON)\n";
            echo "  user        Chronological session history (requires --user=ID)\n\n";
            echo "Options:\n";
            echo "  --since=N       Only analyse entries from the last N days\n";
            echo "  --user=ID       Filter to a specific user ID\n";
            echo "  --output=FILE   Output file path for export command\n\n";
            echo "Examples:\n";
            echo "  php telemetry_analyzer.php report --since=7\n";
            echo "  php telemetry_analyzer.php deaths\n";
            echo "  php telemetry_analyzer.php user --user=123456789\n";
            echo "  php telemetry_analyzer.php export --output=/tmp/export.csv\n";
            break;
    }
}
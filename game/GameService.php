<?php
/**
 * GameService
 *
 * The one door to the game. The Telegram bot and the mini-app API both call act(); neither
 * touches GameEngine, GameSessionManager or the leaderboard directly.
 *
 * Plain data in, plain data out: no Telegram, no HTML, no screen names, no display text.
 * Each client turns the result into its own screens.
 *
 * Result shape of act():
 *   ['session' => array|null, 'outcome' => string, 'data' => array]
 *
 * Actions:  new_game, abandon, launch, advance_day, hunt, rest, process_event,
 *           stop_at_waypoint, skip_waypoint, return_to_waypoint, purchase
 *
 * Outcomes: game_created, abandoned, launched, day_advanced, event_triggered, event_resolved,
 *           waypoint_reached, docked, waypoint_skipped, rested, hunted, returned, purchased,
 *           game_over, victory, rejected
 *
 * 'rejected' means nothing happened; data['reason'] says why (a code, never a sentence).
 * Reason codes: no_session, session_exists, not_launched, not_at_station, not_in_space,
 *   at_waypoint, no_pending_arrival, event_not_found, choice_not_found, insufficient_eth,
 *   invalid_item, invalid_params, unknown_action
 * 'game_over' data['reason'] codes: out_of_fuel, starved, crew_lost, hull_breached
 */
class GameService {
    private GameEngine $engine;
    private ?GameSessionManager $sessions;

    /**
     * @param GameSessionManager|null $sessions  Null is allowed for pure use (runRule only), e.g. tests.
     */
    public function __construct(GameEngine $engine, ?GameSessionManager $sessions = null) {
        $this->engine   = $engine;
        $this->sessions = $sessions;
    }

    // =========================================================================
    // ACTIONS
    // =========================================================================

    /**
     * Run one player action against that player's saved game, save the result, record telemetry.
     *
     * @param int    $userId  Telegram user id (identifies the saved game for both clients)
     * @param string $action  See class doc
     * @param array  $params  new_game: username?, display_name?  process_event: event_id, choice_id
     *                        purchase: item
     * @param string $client  'bot' | 'mini-app' (recorded in telemetry only)
     */
    public function act(int $userId, string $action, array $params = [], string $client = 'unknown'): array {
        if ($this->sessions === null) {
            throw new LogicException('GameService::act() needs a GameSessionManager');
        }

        if ($action === 'new_game') {
            return $this->newGame($userId, $params, $client);
        }
        if ($action === 'abandon') {
            return $this->abandon($userId, $client);
        }

        $session = $this->sessions->loadSession($userId);
        if ($session === null) {
            return $this->rejected(null, 'no_session');
        }

        // Telemetry around event choices and purchases, as the bot did before
        if ($action === 'process_event' && isset($params['event_id'], $params['choice_id'])) {
            Telemetry::eventChoice($session, $params['event_id'], $params['choice_id'], 'processing', $client);
        }
        if ($action === 'purchase' && isset($params['item'])) {
            Telemetry::shopTransaction($session, $params['item'], 1, 0, true, 'attempting_purchase', $client);
        }

        try {
            $result = $this->runRule($action, $session, $params);
        } catch (InvalidArgumentException $e) {
            return $this->rejected($session, $this->isKnownAction($action) ? 'invalid_params' : 'unknown_action');
        }

        $updated = $result['session'];

        if ($result['outcome'] === 'victory') {
            $this->engine->saveToLeaderboard($updated);
        }

        $this->sessions->saveSession($userId, $updated);

        // Telemetry after the move
        if ($action === 'process_event' && $result['outcome'] === 'event_resolved') {
            Telemetry::eventChoice($updated, $params['event_id'], $params['choice_id'], 'completed', $client);
        }
        if ($action === 'purchase') {
            $ok = $result['outcome'] === 'purchased';
            Telemetry::shopTransaction($updated, $params['item'] ?? '', 1, $result['data']['cost'] ?? 0, $ok, $ok ? null : ($result['data']['reason'] ?? null), $client);
        }
        if ($result['outcome'] === 'game_over' || $result['outcome'] === 'victory') {
            Telemetry::sessionLifecycle($result['outcome'], $updated, [
                'reason'     => $result['data']['reason'] ?? null,
                'final_day'  => $updated['progress']['day'],
                'final_chad' => $updated['resources']['chad'],
            ], $client);
        }

        return $result;
    }

    /**
     * The game rules for one action on an in-memory session. No files, no telemetry:
     * the same input gives the same output, so tests can replay games exactly.
     *
     * @throws InvalidArgumentException for an unknown action or missing params
     */
    public function runRule(string $action, array $session, array $params = []): array {
        return match ($action) {
            'advance_day'        => $this->advanceDay($session),
            'process_event'      => $this->processEvent($session, $params),
            'rest'               => $this->rest($session),
            'launch'             => $this->launch($session),
            'hunt'               => $this->hunt($session),
            'return_to_waypoint' => $this->returnToWaypoint($session),
            'stop_at_waypoint'   => $this->stopAtWaypoint($session),
            'skip_waypoint'      => $this->skipWaypoint($session),
            'purchase'           => $this->purchase($session, $params),
            default              => throw new InvalidArgumentException("Unknown action: $action"),
        };
    }

    private function isKnownAction(string $action): bool {
        return in_array($action, ['advance_day', 'process_event', 'rest', 'launch', 'hunt',
            'return_to_waypoint', 'stop_at_waypoint', 'skip_waypoint', 'purchase'], true);
    }

    // =========================================================================
    // READ-ONLY QUERIES
    // =========================================================================

    /** The player's saved game, or null if they have none. */
    public function getState(int $userId): ?array {
        return $this->sessions?->loadSession($userId);
    }

    /** Station shop: what is for sale, and whether this player can afford each item now. */
    public function getShopCatalog(array $session): array {
        $items = [];
        foreach ($this->engine->getShopItems() as $id => $def) {
            $current = $session['resources'][$def['resource']];
            $items[] = [
                'id'         => $id,
                'price'      => $def['price'],
                'quantity'   => $def['quantity'],
                'resource'   => $def['resource'],
                'current'    => $current,
                'max'        => $def['max'],
                'affordable' => $session['resources']['eth'] >= $def['price'],
                'full'       => $current >= $def['max'],
            ];
        }
        return ['eth' => $session['resources']['eth'], 'items' => $items];
    }

    /** Whether the journey is over: game_over is a reason code (or null), victory is true at Uranus. */
    public function getEnding(array $session): array {
        return [
            'game_over' => $this->engine->checkGameOver($session),
            'victory'   => $session['progress']['distance'] >= TOTAL_DISTANCE,
        ];
    }

    /** The event the player must answer now, in the form clients may show (no outcomes), or null. */
    public function getPendingEvent(array $session): ?array {
        if (empty($session['pending_event'])) {
            return null;
        }
        $event = $this->engine->getEventById($session['pending_event']);
        return $event === null ? null : $this->engine->describeEvent($event, $session);
    }

    /** An event from a result's data, in the form clients may show (no outcomes). */
    public function describeEvent(array $event, array $session): array {
        return $this->engine->describeEvent($event, $session);
    }

    public function getStatusDetails(array $session): array {
        return $this->engine->getStatusDetails($session);
    }

    public function getMapData(array $session): array {
        return $this->engine->getMapData($session);
    }

    public function getLeaderboard(): array {
        return $this->engine->getLeaderboard();
    }

    /** Cost and details of turning back, for a confirmation prompt. */
    public function getReturnCosts(array $session): array {
        return $this->engine->calculateReturnCosts($session);
    }

    // =========================================================================
    // SESSION LIFECYCLE
    // =========================================================================

    private function newGame(int $userId, array $params, string $client): array {
        if ($this->sessions->loadSession($userId) !== null) {
            return $this->rejected($this->sessions->loadSession($userId), 'session_exists');
        }

        $session = $this->engine->createNewGame($userId);
        $session['status'] = 'pre_launch';
        if (!empty($params['username'])) {
            $session['username'] = $params['username'];
        }
        if (!empty($params['display_name'])) {
            $session['display_name'] = $params['display_name'];
        }

        $this->sessions->saveSession($userId, $session);
        Telemetry::sessionLifecycle('created', $session, [], $client);

        return ['session' => $session, 'outcome' => 'game_created', 'data' => []];
    }

    private function abandon(int $userId, string $client): array {
        $session = $this->sessions->loadSession($userId);
        if ($session === null) {
            return $this->rejected(null, 'no_session');
        }

        Telemetry::sessionLifecycle('abandoned', $session, [
            'final_day'       => $session['progress']['day'],
            'final_distance'  => round($session['progress']['distance'] / 1000000, 2),
            'final_chad'      => $session['resources']['chad'],
            'final_resources' => $session['resources'],
        ], $client);

        $this->sessions->deleteSession($userId);

        return ['session' => null, 'outcome' => 'abandoned', 'data' => ['last_session' => $session]];
    }

    // =========================================================================
    // RULES (one per action; each mirrors what ActionManager decided, as data)
    // =========================================================================

    private function advanceDay(array $session): array {
        $r = $this->engine->advanceDay($session);
        $session = $r['session'];
        $report = $this->dayReport($r);

        if (!empty($r['game_over'])) {
            return $this->out($session, 'game_over', ['reason' => $r['reason']] + $report);
        }
        if (!empty($r['victory'])) {
            return $this->out($session, 'victory', $report);
        }
        if (($r['reason'] ?? null) === 'not_launched') {
            return $this->rejected($session, 'not_launched');
        }

        // Waypoints come before events so a random event never hides a waypoint choice.
        // Uranus itself is the finish line, handled as victory above.
        $waypoint = $session['at_waypoint'] ?? null;
        if ($waypoint && ($waypoint['id'] ?? '') !== 'uranus'
            && $session['status'] === 'at_waypoint'
            && empty($session['pending_station_arrival'])) {
            $session['pending_station_arrival'] = $waypoint;
            return $this->out($session, 'waypoint_reached', ['waypoint' => $waypoint] + $report);
        }

        if (!empty($r['event'])) {
            return $this->out($session, 'event_triggered', ['event' => $r['event']] + $report);
        }

        return $this->out($session, 'day_advanced', $report);
    }

    /** What happened during the day's travel, from GameEngine::advanceDay(). */
    private function dayReport(array $r): array {
        return [
            'distance_traveled' => $r['distance_traveled'] ?? null,
            'fuel_consumed'     => $r['fuel_consumed'] ?? null,
            'food_consumed'     => $r['food_consumed'] ?? null,
            'vibes_state'       => $r['vibes_state'] ?? null,   // null | 'low' | 'critical'
            'deserter'          => $r['deserter'] ?? null,      // name of crew member who left
            'slingshot'         => $r['slingshot'] ?? false,    // Jupiter speed-up happened today
            'milestone'         => $r['milestone'] ?? null,     // 25 | 50 | 75 when just reached
        ];
    }

    private function processEvent(array $session, array $params): array {
        if (empty($params['event_id']) || empty($params['choice_id'])) {
            throw new InvalidArgumentException('process_event requires event_id and choice_id');
        }

        $r = $this->engine->processEventChoice($session, $params['event_id'], $params['choice_id']);
        $session = $r['session'];

        if (($r['success'] ?? true) === false) {
            return $this->rejected($session, $r['reason']);
        }

        // A chad dying from the event's effects can end the game
        $gameOver = $this->engine->checkGameOver($session);
        if ($gameOver) {
            return $this->out($session, 'game_over', ['reason' => $gameOver]);
        }

        return $this->out($session, 'event_resolved', [
            'choice_text'      => $r['choice_text'],
            'outcome_text'     => $r['outcome_text'],
            'chad_reaction'    => $r['chad_reaction'],   // ['name', 'says'] | null
            'deaths'           => $r['deaths'],          // names of crew who died
            'resource_changes' => $r['resource_changes'], // resource => [before, after, delta]
            'event_outcome'    => $session['recent_event_outcome'] ?? 'success', // 'success' | 'failure'
        ]);
    }

    private function rest(array $session): array {
        $r = $this->engine->rest($session);
        $session = $r['session'];

        if (!empty($r['game_over'])) {
            return $this->out($session, 'game_over', ['reason' => $r['reason']]);
        }
        if (($r['success'] ?? true) === false) {
            return $this->rejected($session, $r['reason']);
        }

        return $this->out($session, 'rested', [
            'hull_repaired' => $r['hull_repaired'],
            'vibes_gained'  => $r['vibes_gained'],
            'food_consumed' => $r['food_consumed'],
            'station'       => $session['at_waypoint'] ?? null,
        ]);
    }

    private function launch(array $session): array {
        $r = $this->engine->purchaseItem($session, 'launch', 0);
        return $this->out($r['session'], 'launched', [
            'initial'      => $r['initial'],       // true for the first launch, false when leaving a station
            'low_supplies' => $r['low_supplies'],  // first launch with supplies under the minimums
            'minimums'     => $r['minimums'],
            'had_boost'    => $r['had_boost'],     // gravitational assist lost by launching
        ]);
    }

    private function hunt(array $session): array {
        $r = $this->engine->huntResources($session);
        $session = $r['session'];

        if (!empty($r['game_over'])) {
            return $this->out($session, 'game_over', ['reason' => $r['reason']]);
        }
        if (($r['success'] ?? true) === false) {
            return $this->rejected($session, $r['reason']);
        }
        if (!empty($r['event'])) {
            return $this->out($session, 'event_triggered', ['event' => $r['event'], 'from_hunt' => true]);
        }

        return $this->out($session, 'hunted', ['found_nothing' => true]);
    }

    private function returnToWaypoint(array $session): array {
        $r = $this->engine->returnToLastWaypoint($session);
        $session = $r['session'];

        if (!empty($r['game_over'])) {
            return $this->out($session, 'game_over', ['reason' => $r['reason']]);
        }
        if (($r['success'] ?? true) === false) {
            return $this->rejected($session, $r['reason']);
        }

        return $this->out($session, 'returned', [
            'waypoint'      => $r['waypoint'],
            'days'          => $r['days'],
            'fuel_consumed' => $r['fuel_consumed'],
            'food_consumed' => $r['food_consumed'],
        ]);
    }

    private function stopAtWaypoint(array $session): array {
        if (empty($session['pending_station_arrival'])) {
            return $this->rejected($session, 'no_pending_arrival');
        }

        $waypoint = $session['pending_station_arrival'];
        $r = $this->engine->stopAtWaypoint($session, $waypoint);

        // Pick today's story for this station; both clients read it back from the saved game
        $content = $this->engine->selectStationContent($r['session'], $waypoint);

        return $this->out($content['session'], 'docked', [
            'station'   => $content['station'],   // waypoint with description and local_story chosen
            'had_boost' => $r['had_boost'],
        ]);
    }

    private function skipWaypoint(array $session): array {
        if (empty($session['pending_station_arrival'])) {
            return $this->rejected($session, 'no_pending_arrival');
        }

        $waypoint = $session['pending_station_arrival'];
        $r = $this->engine->skipWaypoint($session, $waypoint);

        return $this->out($r['session'], 'waypoint_skipped', [
            'waypoint'           => $waypoint,
            'was_already_boosted' => $r['was_already_boosted'],
        ]);
    }

    private function purchase(array $session, array $params): array {
        if (empty($params['item'])) {
            throw new InvalidArgumentException('purchase requires item');
        }

        $r = $this->engine->purchaseItem($session, $params['item'], 1);
        $session = $r['session'];

        if (!$r['success']) {
            return $this->rejected($session, $r['reason'], [
                'item'          => $r['item'] ?? $params['item'],
                'cost'          => $r['cost'] ?? 0,
                'eth_available' => $r['eth_available'] ?? null,
            ]);
        }

        return $this->out($session, 'purchased', [
            'item'               => $r['item'],
            'cost'               => $r['cost'],
            'quantity_requested' => $r['quantity_requested'],
            'quantity_added'     => $r['quantity_added'],
            'capped'             => $r['capped'],   // true when the tank/hull was too full to take it all
        ]);
    }

    // =========================================================================
    // OPERATOR TOOLS (admin screens, who's playing)
    // =========================================================================

    /** Players active in the last $days days, furthest along first. */
    public function getActivePlayers(int $days = 7): array {
        return $this->sessions->getActivePlayers($days);
    }

    public function countGames(): int {
        return $this->sessions->getActiveSessionCount();
    }

    public function getAllGames(): array {
        return $this->sessions->getAllSessions();
    }

    /** Delete one player's saved game (admin reset). */
    public function deleteGame(int $userId): bool {
        return $this->sessions->deleteSession($userId);
    }

    /** Delete every saved game (admin reset). Returns how many were removed. */
    public function deleteAllGames(): int {
        return $this->sessions->deleteAllSessions();
    }

    // =========================================================================
    // RESULT HELPERS
    // =========================================================================

    private function out(?array $session, string $outcome, array $data = []): array {
        return ['session' => $session, 'outcome' => $outcome, 'data' => $data];
    }

    private function rejected(?array $session, string $reason, array $extra = []): array {
        return $this->out($session, 'rejected', ['reason' => $reason] + $extra);
    }
}

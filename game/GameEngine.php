<?php
/**
 * Game Engine
 * Core game logic for Occupy Uranus
 *
 * PATCH NOTES — changes from previous version:
 *
 * REFERENCE/RETURN ALIASING FIXED (was Issue #2)
 * - advanceDay(), processEventChoice(), rest(), purchaseItem() previously took
 *   $session by reference AND returned it inside the result array. This meant
 *   the caller's variable and $result['session'] silently aliased the same zval.
 *   If anyone copied the session before calling, or if the code was refactored
 *   to value semantics, saved data could silently diverge from returned data.
 *
 *   New contract: all four methods take $session BY VALUE (no &), work on it
 *   internally, and return the modified copy in $result['session']. Callers
 *   MUST use $result['session'] for the authoritative post-call state.
 *   This is explicit, predictable, and safe to refactor.
 *
 * DIFFICULTY VALIDATION (was Issue #9)
 * - createNewGame() now validates the difficulty string against the allowed set
 *   ['easy', 'normal', 'hard']. Invalid values are rejected with an
 *   InvalidArgumentException rather than silently falling through to 'normal'
 *   defaults while recording the bogus string in the session.
 * - A static VALID_DIFFICULTIES constant centralises the allowed set.
 *
 * CREW CAP ON POSITIVE CHANGES
 * - applyEffects(): positive chad changes are now clamped to MAX_CREW, matching
 *   the existing floor-at-0 clamp for negative changes and the ceiling clamps
 *   for hull and vibes. Previously, an event that granted +1 chad when at 5/5
 *   would silently exceed the maximum.
 *
 * KEYBOARD BUILDERS: $session no longer passed by reference
 * - getJourneyKeyboard(), getStatusKeyboard(), getMapKeyboard(), and all
 *   format*() methods that previously took &$session now take $session by value.
 *   These methods are read-only — they never needed to mutate the session.
 *   Removing the & makes the contract honest and prevents accidental mutation.
 */

class GameEngine {
    // Difficulty system removed 2026-03-02 - all games use same starting values

    /**
     * Within-request cache for game data files (events, waypoints, etc.).
     * These are read-only data files that never change at runtime, so caching them
     * for the lifetime of one PHP request is safe and avoids redundant file reads.
     * Static properties reset on every request — this provides no cross-request benefit.
     */
    private static $eventsCache           = null;
    private static $waypointsCache        = null;
    private static $itemsCache            = null; // Reserved for future shop expansion
    private static $chadActivitiesCache   = null;
    private static $chadPersonalitiesCache = null;

    /** Role order for chad (matches getChadTitle positions) */
    private const CREW_ROLES = ['Captain', 'Engineer', 'Quant', 'Medic', 'HODLer'];

    // =========================================================================
    // GAME INITIALIZATION
    // =========================================================================

    // getStartingSuppliesInfo removed 2026-03-02 (difficulty system eliminated)

    /**
     * Create a brand-new game session for a user.
     *
     * Starting resources (rebalanced 2026-03-01):
     * - Fuel: 450 units (90 days of travel)
     * - Food: 1313 units (87 days for 5 chad)
     * - ETH: 15.000 (strategic shopping at waypoints)
     *
     * Difficulty system removed 2026-03-02 - all games use same starting values.
     *
     * @param  int|string  $userId
     * @return array       Fully initialised session array
     */
    public function createNewGame($userId): array {
        $chadRoster = $this->createChadRoster();

        return [
            'session_id'        => 'sess_' . $userId . '_' . time(),
            'created_at'        => time(),
            'last_updated'      => time(),
            'user_id'           => $userId,
            'status'            => 'pre_launch',
            'resources' => [
                'eth' => 15.000,  // Strategic shopping at waypoints
                'fuel'    => 400,   // 80 days supply - hunting/stations essential
                'food'    => 800,   // 53 days supply - hunting/stations essential
                'chad'    => MAX_CREW,
                'hull'    => 100,   // Start at full hull
                'vibes'  => MAX_VIBES,
            ],
            'progress' => [
                'distance' => 0,
                'day'      => 1,
                'speed'    => TRAVEL_SPEED,
                'speed_multiplier' => 1.0,
            ],
            'chad_names'        => array_column($chadRoster, 'name'),
            'chad_roster'       => $chadRoster,
            'waypoints_reached' => [],
            'milestones_shown'  => [],
            // Associative format: [$eventId => true]
            // Older sessions may have a sequential array — normaliseEventsCompleted()
            // migrates transparently on first access.
            'events_completed'  => [],
            'pending_event'     => null,
            'stats' => [
                'events_encountered'      => 0,
                'chad_lost'               => 0,
                'fuel_consumed'           => 0,
                'food_consumed'           => 0,
                'total_distance_traveled' => 0,
                'event_net_fuel'          => 0,
                'event_net_food'          => 0,
                'event_net_eth'           => 0,
                'event_net_hull'          => 0,
                'waypoints_stopped'       => 0,
            ],
        ];
    }

    // =========================================================================
    // GAME PROGRESSION
    // =========================================================================

    /**
     * Advance the game by one day: consume resources, move ship, check events.
     *
     * CONTRACT: $session is taken BY VALUE. The caller's variable is NOT modified.
     * The authoritative post-call state is in $result['session'].
     *
     * @param  array  $session  Current session state (not modified — by value)
     * @return array  Result array with keys: success, session, message,
     *                and optionally: game_over + reason, victory, event
     */
    public function advanceDay(array $session): array {
        if ($session['status'] === 'pre_launch') {
            return [
                'success' => false,
                'reason'  => 'not_launched',
                'session' => $session,
            ];
        }

        // Clear waypoint state when continuing journey from a station
        if ($session['status'] === 'at_station') {
            $session['status'] = 'in_space';
            $session['at_waypoint'] = null;
        }

        // Clear recent event outcome flag (it's been shown, now clear for next time)
        unset($session['recent_event_outcome']);

        // Surface a pending event instead of advancing — player must resolve it first
        if ($session['pending_event'] !== null) {
            $event = $this->getEventById($session['pending_event']);
            return [
                'success' => false,
                'reason'  => 'event_pending',
                'session' => $session,
                'event'   => $event,
            ];
        }

        // Normalise milestone tracking for older sessions and clear transient flag
        if (!isset($session['milestones_shown']) || !is_array($session['milestones_shown'])) {
            $session['milestones_shown'] = [];
        }
        unset($session['milestone_just_reached']);

        // ── Resource consumption ─────────────────────────────────────────────
        $fuelConsumed = FUEL_PER_DAY;
        $foodConsumed = $session['resources']['chad'] * FOOD_PER_CREW;

        $session['resources']['fuel'] -= $fuelConsumed;
        $session['resources']['food'] -= $foodConsumed;
        $session['stats']['fuel_consumed'] += $fuelConsumed;
        $session['stats']['food_consumed'] += $foodConsumed;

        // ── Vibes effects ────────────────────────────────────────────────────
        // Critical vibes can cause desertion. This is checked AFTER resource
        // consumption so that low-vibes consequences compound with scarcity.
        $vibesState = null;      // null | 'low' | 'critical'
        $desertionName = null;

        if ($session['resources']['vibes'] <= 10) {
            // Critical vibes: 15% chance per day of chad desertion
            $vibesState = 'critical';

            if ($session['resources']['chad'] > 1 && rand(1, 100) <= 15) {
                $deserterIndex = $session['resources']['chad'] - 1;
                $desertionName = $session['chad_names'][$deserterIndex] ?? 'A chad';
                $session['resources']['chad']--;
                $session['stats']['chad_lost']++;
            }
        } elseif ($session['resources']['vibes'] <= 30) {
            $vibesState = 'low';
        }

        // ── Distance & day ───────────────────────────────────────────────────
        // Apply speed multiplier (gravitational assist from skipping stations)
        $speedMultiplier = $session['progress']['speed_multiplier'] ?? 1.0;
        $effectiveSpeed = $session['progress']['speed'] * $speedMultiplier;
        $session['progress']['distance'] += $effectiveSpeed;
        $session['progress']['day']++;

        // ── Total distance traveled (cumulative, includes backtrack) ───────────
        $session['stats']['total_distance_traveled'] += $effectiveSpeed;

        // ── Waypoint check ───────────────────────────────────────────────────
        $waypoint = $this->checkWaypointReached($session);
        $session['at_waypoint'] = $waypoint;

        // Set status to at_waypoint when crossing threshold (decision phase)
        if ($waypoint !== null) {
            $session['status'] = 'at_waypoint';
        }

        // ── Speed increase after Jupiter ─────────────────────────────────────
        // After passing Jupiter, base speed increases from 20M to 40M km/day
        if (in_array('Jupiter Gas Mine', $session['waypoints_reached']) && $session['progress']['speed'] === TRAVEL_SPEED) {
            $session['progress']['speed'] = TRAVEL_SPEED * 2;
        }

        // ── Slingshot announcement (Jupiter) ────────────────────────────────
        $slingshot = $waypoint
            && $waypoint['name'] === 'Jupiter Gas Mine'
            && $session['progress']['speed'] > TRAVEL_SPEED;

        // ── Milestone check (25/50/75% journey progress) ─────────────────────
        $milestoneReached = null;
        $progressPct = ($session['progress']['distance'] / TOTAL_DISTANCE) * 100;
        $milestones  = [25, 50, 75];
        foreach ($milestones as $milestone) {
            if ($progressPct >= $milestone && !in_array($milestone, $session['milestones_shown'], true)) {
                $session['milestones_shown'][]    = $milestone;
                $session['milestone_just_reached'] = $milestone;
                $milestoneReached = $milestone;
                break;
            }
        }

        // What happened today, as plain data. Each client writes its own text from this.
        $report = [
            'distance_traveled' => $effectiveSpeed,
            'fuel_consumed'     => $fuelConsumed,
            'food_consumed'     => $foodConsumed,
            'vibes_state'       => $vibesState,
            'deserter'          => $desertionName,
            'waypoint'          => $waypoint,
            'slingshot'         => $slingshot,
            'milestone'         => $milestoneReached,
        ];

        // ── Game over check ───────────────────────────────────────────────────
        $gameOver = $this->checkGameOver($session);
        if ($gameOver) {
            return $report + [
                'game_over' => true,
                'reason'    => $gameOver,
                'session'   => $session,
            ];
        }

        // ── Victory check ─────────────────────────────────────────────────────
        if ($session['progress']['distance'] >= TOTAL_DISTANCE) {
            return $report + [
                'victory' => true,
                'session' => $session,
            ];
        }

        // ── Random event check (speed-based frequency) ───────────────────────
        // Event chance scales with speed: faster travel = more encounters
        // Formula: (effectiveSpeed / baseSpeed) × 25%, capped at 75%
        // 20M km/day → 25%, 40M km/day → 50%, 80M km/day → 75%
        // IMPORTANT: Suppress events when crossing a waypoint (station arrival takes priority)
        if (!$waypoint) {
            $baseSpeedKm = TRAVEL_SPEED; // 20M km/day reference
            $baseEventChance = 25; // 25% at base speed
            $eventChance = min(75, ($effectiveSpeed / $baseSpeedKm) * $baseEventChance);

            if (rand(1, 100) <= $eventChance) {
                $event = $this->getRandomEvent($session);
                if ($event) {
                    $session['pending_event'] = $event['id'];
                    $session['stats']['events_encountered']++;
                    return $report + [
                        'success' => true,
                        'session' => $session,
                        'event'   => $event,
                    ];
                }
            }
        }

        // ── No event ─────────────────────────────────────────────────────────
        return $report + [
            'success' => true,
            'session' => $session,
        ];
    }

    /**
     * Process the player's choice for an active event.
     *
     * CONTRACT: $session is taken BY VALUE. The caller's variable is NOT modified.
     * The authoritative post-call state is in $result['session'].
     *
     * @param  array   $session   Current session state (not modified — by value)
     * @param  string  $eventId
     * @param  string  $choiceId
     * @return array   Result with keys: success, message, session
     */
    public function processEventChoice(array $session, string $eventId, string $choiceId): array {
        $session['pending_event'] = null;

        $this->normaliseEventsCompleted($session);

        $events = $this->loadEvents();
        $event  = null;
        foreach ($events as $e) {
            if ($e['id'] === $eventId) {
                $event = $e;
                break;
            }
        }

        if (!$event) {
            return ['success' => false, 'reason' => 'event_not_found', 'session' => $session];
        }

        $choice = null;
        foreach ($event['choices'] as $c) {
            if ($c['id'] === $choiceId) {
                $choice = $c;
                break;
            }
        }

        if (!$choice) {
            return ['success' => false, 'reason' => 'choice_not_found', 'session' => $session];
        }

        $outcome = $this->selectOutcome($choice['outcomes']);

        // Capture resources before applying effects
        $resourcesBefore = $session['resources'];

        $deaths = $this->applyEffects($session, $outcome['effects']);

        // Calculate resource deltas (only changed resources)
        $resourceChanges = $this->calculateResourceDeltas($resourcesBefore, $session['resources']);

        // Track net resource changes from events for leaderboard stats
        // Only track fuel, food, ETH, hull (not chad - deaths are tracked separately)
        foreach (['fuel', 'food', 'eth', 'hull'] as $resource) {
            if (isset($resourceChanges[$resource]['delta'])) {
                $statKey = 'event_net_' . $resource;
                $session['stats'][$statKey] = ($session['stats'][$statKey] ?? 0) + $resourceChanges[$resource]['delta'];
            }
        }

        // Mark as completed in associative format
        $session['events_completed'][$eventId] = true;

        // Classify event outcome for chad quotes (success vs failure)
        $session['recent_event_outcome'] = $this->classifyEventOutcome($outcome['effects'], $deaths);

        $outcomeText = $this->replaceChadTemplates($outcome['message'], $session);

        // Crew reaction: either authored in the event, or (25% of regular events) a personality quote
        $chadReaction = null;
        if (!empty($outcome['chad_reaction'])) {
            $reaction = is_string($outcome['chad_reaction'])
                ? $this->parseChadReactionTemplate($outcome['chad_reaction'], $session)
                : $outcome['chad_reaction'];
            $says = $reaction['says'] ?? '';
            if ($says !== '') {
                $chadReaction = ['name' => $reaction['name'] ?? 'Chad', 'says' => $says];
            }
        } else {
            $roster = $this->getChadRoster($session);
            $activeCount = $session['resources']['chad'] ?? 0;
            if ($activeCount > 0 && rand(1, 100) <= 25) {
                $idx    = array_rand(array_slice($roster, 0, $activeCount));
                $member = $roster[$idx];
                $quote  = $this->getChadQuote($member, $session, $idx);
                if ($quote !== null && $quote !== '') {
                    $chadReaction = ['name' => $member['name'], 'says' => $quote];
                }
            }
        }

        return [
            'success'          => true,
            'session'          => $session,
            'choice_text'      => $choice['text'],
            'outcome_text'     => $outcomeText,
            'chad_reaction'    => $chadReaction,
            'deaths'           => $deaths,
            'resource_changes' => $resourceChanges,
        ];
    }

    /**
     * Rest for one day at a waypoint: restore hull and vibes at the cost of food.
     * Special case of advancing time while parked at a station.
     * Distance does NOT advance — ship is docked.
     *
     * CONTRACT: $session is taken BY VALUE. The caller's variable is NOT modified.
     * The authoritative post-call state is in $result['session'].
     *
     * IMPORTANT FOR BOT: The returned array may contain 'game_over' => true if resources
     * are exhausted during the rest day. handleRest() in OccupyUranusBot must check for
     * this key and route to showGameOver() exactly as handleContinue() does.
     *
     * @param  array  $session  Current session state (not modified — by value)
     * @return array  Result with keys: success, message, session, hull_repaired, vibes_gained, and optionally game_over + reason
     */
    public function rest(array $session): array {
        if ($session['status'] !== 'at_station') {
            return [
                'success' => false,
                'reason'  => 'not_at_station',
                'session' => $session,
            ];
        }

        // Clear recent event outcome flag (it's been shown, now clear for next time)
        unset($session['recent_event_outcome']);

        // Advance one day (like advanceDay but no distance traveled - ship is docked)
        $session['progress']['day']++;

        // Consume food (chad eats while resting)
        $foodConsumed = $session['resources']['chad'] * FOOD_PER_CREW;
        $session['resources']['food'] -= $foodConsumed;
        $session['stats']['food_consumed'] += $foodConsumed;

        // Clamp food to zero floor
        $session['resources']['food'] = max(0, $session['resources']['food']);

        // Check game over (starvation)
        $gameOver = $this->checkGameOver($session);
        if ($gameOver) {
            return [
                'success'   => false,
                'game_over' => true,
                'reason'    => $gameOver,
                'session'   => $session,
            ];
        }

        // Restore hull (repair while docked)
        $hullRepaired = 20;
        $session['resources']['hull'] = min(MAX_HULL, $session['resources']['hull'] + $hullRepaired);

        // Restore vibes (after survival is confirmed)
        $vibesGained = 30;
        $session['resources']['vibes'] = min(MAX_VIBES, $session['resources']['vibes'] + $vibesGained);

        return [
            'success'       => true,
            'session'       => $session,
            'hull_repaired' => $hullRepaired,
            'vibes_gained'  => $vibesGained,
            'food_consumed' => $foodConsumed,
        ];
    }

    /**
     * Calculate return-to-waypoint costs and details.
     * Used by ViewManager to display confirmation dialog.
     *
     * @param  array  $session  Current session state
     * @return array  [
     *   'last_waypoint' => [...],
     *   'fuel_cost' => int,
     *   'food_cost' => int,
     *   'backtrack_km' => int,
     *   'days' => int
     * ]
     */
    public function calculateReturnCosts(array $session): array {
        $waypoints = $this->loadWaypoints();
        $currentDistance = $session['progress']['distance'];

        // Find last waypoint: largest with distance <= current
        $lastWaypoint = null;
        foreach ($waypoints as $wp) {
            if ($wp['distance'] <= $currentDistance) {
                $lastWaypoint = $wp;
            }
        }
        if ($lastWaypoint === null) {
            $lastWaypoint = ['name' => 'Earth Orbit', 'distance' => 0, 'symbol' => '🌍', 'id' => 'earth'];
        }

        // Fixed 2-day cost
        $fuelCost = FUEL_PER_DAY * 2;
        $foodCost = $session['resources']['chad'] * FOOD_PER_CREW * 2;
        $backtrackKm = $currentDistance - $lastWaypoint['distance'];

        return [
            'last_waypoint' => $lastWaypoint,
            'fuel_cost' => $fuelCost,
            'food_cost' => $foodCost,
            'backtrack_km' => $backtrackKm,
            'days' => 2,
        ];
    }

    /**
     * Return to the last waypoint: 2-day cost, consume fuel/food, set distance back.
     * Insufficient resources → game over (same pattern as rest()).
     *
     * CONTRACT: $session is taken BY VALUE. The caller's variable is NOT modified.
     *
     * @param  array  $session  Current session state (not modified — by value)
     * @return array  Result with keys: success, message, session, and optionally game_over + reason
     */
    public function returnToLastWaypoint(array $session): array {
        if ($session['status'] !== 'in_space') {
            return [
                'success' => false,
                'reason'  => 'not_in_space',
                'session' => $session,
            ];
        }

        if ($this->isAtWaypoint($session)) {
            return [
                'success' => false,
                'reason'  => 'at_waypoint',
                'session' => $session,
            ];
        }

        $waypoints = $this->loadWaypoints();
        $currentDistance = $session['progress']['distance'];

        // Find last waypoint: largest with distance <= current
        $lastWaypoint = null;
        foreach ($waypoints as $wp) {
            if ($wp['distance'] <= $currentDistance) {
                $lastWaypoint = $wp;
            }
        }
        if ($lastWaypoint === null) {
            $lastWaypoint = ['name' => 'Earth Orbit', 'distance' => 0, 'id' => 'earth'];
        }

        // Fixed 2-day cost
        $session['progress']['day'] += 2;
        $fuelConsumed = FUEL_PER_DAY * 2;
        $foodConsumed = $session['resources']['chad'] * FOOD_PER_CREW * 2;
        $session['resources']['fuel'] -= $fuelConsumed;
        $session['resources']['food'] -= $foodConsumed;
        $session['stats']['fuel_consumed'] += $fuelConsumed;
        $session['stats']['food_consumed'] += $foodConsumed;
        $session['resources']['fuel'] = max(0, $session['resources']['fuel']);
        $session['resources']['food'] = max(0, $session['resources']['food']);

        $gameOver = $this->checkGameOver($session);
        if ($gameOver) {
            return [
                'success'   => false,
                'game_over' => true,
                'reason'    => $gameOver,
                'session'   => $session,
            ];
        }

        $backtrackKm = $currentDistance - $lastWaypoint['distance'];
        $session['stats']['total_distance_traveled'] += $backtrackKm;
        $session['progress']['distance'] = $lastWaypoint['distance'];
        $session['at_waypoint'] = $lastWaypoint;

        return [
            'success'       => true,
            'session'       => $session,
            'waypoint'      => $lastWaypoint,
            'days'          => 2,
            'fuel_consumed' => $fuelConsumed,
            'food_consumed' => $foodConsumed,
        ];
    }

    /**
     * Hunt for resources: 1-day cost, no distance advance, triggers random hunt-eligible event.
     * Only available when NOT at waypoint.
     *
     * CONTRACT: $session is taken BY VALUE. The caller's variable is NOT modified.
     *
     * @param  array  $session  Current session state (not modified — by value)
     * @return array  Result with keys: success, message, session, and optionally game_over + reason, event
     */
    public function huntResources(array $session): array {
        if ($session['status'] !== 'in_space') {
            return [
                'success' => false,
                'reason'  => 'not_in_space',
                'session' => $session,
            ];
        }

        if ($this->isAtWaypoint($session)) {
            return [
                'success' => false,
                'reason'  => 'at_waypoint',
                'session' => $session,
            ];
        }

        // Clear recent event outcome flag (it's been shown, now clear for next time)
        unset($session['recent_event_outcome']);

        // Consume 1 day (fuel, food)
        $session['progress']['day']++;
        $fuelConsumed = FUEL_PER_DAY;
        $foodConsumed = $session['resources']['chad'] * FOOD_PER_CREW;
        $session['resources']['fuel'] -= $fuelConsumed;
        $session['resources']['food'] -= $foodConsumed;
        $session['stats']['fuel_consumed'] += $fuelConsumed;
        $session['stats']['food_consumed'] += $foodConsumed;
        $session['resources']['fuel'] = max(0, $session['resources']['fuel']);
        $session['resources']['food'] = max(0, $session['resources']['food']);

        $gameOver = $this->checkGameOver($session);
        if ($gameOver) {
            return [
                'success'   => false,
                'game_over' => true,
                'reason'    => $gameOver,
                'session'   => $session,
            ];
        }

        $event = $this->getRandomHuntEvent($session);
        if (!$event) {
            return [
                'success'       => true,
                'session'       => $session,
                'found_nothing' => true,
            ];
        }

        $session['pending_event'] = $event['id'];
        $session['stats']['events_encountered']++;

        return [
            'success' => true,
            'session' => $session,
            'event'   => $event,
        ];
    }

    // =========================================================================
    // SHOP & LAUNCH
    // =========================================================================

    /**
     * Process a shop purchase.
     * Fuel and food purchases are capped at MAX_FUEL and MAX_FOOD respectively.
     * Hull is capped at MAX_HULL (this was already implemented).
     *
     * CONTRACT: $session is taken BY VALUE. The caller's variable is NOT modified.
     * The authoritative post-call state is in $result['session'].
     *
     * @param  array       $session   Current session state (not modified — by value)
     * @param  string      $item      'fuel' | 'food' | 'repair' | 'launch'
     * @param  int|string  $quantity  Quantity requested (item-dependent)
     * @return array       Result with keys: success, message, session, and optionally cost
     */
    public function purchaseItem(array $session, string $item, $quantity): array {
        // ── Launch ───────────────────────────────────────────────────────────
        if ($item === 'launch') {
            $minFuel = 20;
            $minFood = 15;
            $minHull = 30;

            $isInitialLaunch = ($session['status'] === 'pre_launch');
            $session['status'] = 'in_space';

            // Initialize speed multiplier if missing (for old sessions)
            if (!isset($session['progress']['speed_multiplier'])) {
                $session['progress']['speed_multiplier'] = 1.0;
            }

            // Reset speed multiplier when launching (no boost for stopping at stations)
            $hadBoost = $session['progress']['speed_multiplier'] > 1.0;
            $session['progress']['speed_multiplier'] = 1.0;

            // Initial launch vs leaving a station; low supplies only matters on the first launch
            $lowSupplies = $isInitialLaunch && (
                $session['resources']['fuel'] < $minFuel ||
                $session['resources']['food'] < $minFood ||
                $session['resources']['hull'] < $minHull
            );

            return [
                'success'      => true,
                'item'         => 'launch',
                'initial'      => $isInitialLaunch,
                'low_supplies' => $lowSupplies,
                'minimums'     => ['fuel' => $minFuel, 'food' => $minFood, 'hull' => $minHull],
                'had_boost'    => $hadBoost,
                'session'      => $session,
            ];
        }

        // ── Validate item ────────────────────────────────────────────────────
        $shopItems = $this->getShopItems();
        if (!isset($shopItems[$item])) {
            return [
                'success' => false,
                'reason' => 'invalid_item',
                'session' => $session,
                'cost' => 0,
            ];
        }

        $price = $shopItems[$item]['price'];

        if ($session['resources']['eth'] < $price) {
            return [
                'success' => false,
                'reason' => 'insufficient_eth',
                'item' => $item,
                'cost' => $price,
                'eth_available' => $session['resources']['eth'],
                'session' => $session,
            ];
        }

        // ── Purchase ─────────────────────────────────────────────────────────
        $session['resources']['eth'] -= $price;
        $def               = $shopItems[$item];
        $resource          = $def['resource'];
        $quantityRequested = $def['quantity'];
        $before            = $session['resources'][$resource];
        $session['resources'][$resource] = min($def['max'], $before + $quantityRequested);
        $quantityAdded     = $session['resources'][$resource] - $before;
        $capped            = ($quantityAdded < $quantityRequested);

        return [
            'success' => true,
            'item' => $item,
            'quantity_requested' => $quantityRequested,
            'quantity_added' => $quantityAdded,
            'cost' => $price,
            'capped' => $capped,
            'session' => $session,
        ];
    }

    // =========================================================================
    // STATE QUERIES (data for any client: bot text and mini-app both build on these)
    // =========================================================================

    /**
     * Items sold at stations. Single source for prices and quantities: purchaseItem()
     * applies them and every client shows them.
     *
     * @return array  id => ['price' => ETH, 'quantity' => units per purchase,
     *                       'resource' => session resource it fills, 'max' => resource ceiling]
     */
    public function getShopItems(): array {
        return [
            'fuel'   => ['price' => 1.000, 'quantity' => 100, 'resource' => 'fuel', 'max' => MAX_FUEL],
            'food'   => ['price' => 0.500, 'quantity' => 50,  'resource' => 'food', 'max' => MAX_FOOD],
            'repair' => ['price' => 2.000, 'quantity' => 20,  'resource' => 'hull', 'max' => MAX_HULL],
        ];
    }

    /** Crew vibes band: 'high' (80+) | 'good' (60+) | 'strained' (40+) | 'low' (20+) | 'mutiny'. */
    public function getVibesBand(int $vibes): string {
        if ($vibes >= 80) return 'high';
        if ($vibes >= 60) return 'good';
        if ($vibes >= 40) return 'strained';
        if ($vibes >= 20) return 'low';
        return 'mutiny';
    }

    /** Hull band: 'excellent' (80+) | 'minor_damage' (60+) | 'significant_damage' (40+) | 'critical' (20+) | 'failing'. */
    public function getHullBand(int $hull): string {
        if ($hull >= 80) return 'excellent';
        if ($hull >= 60) return 'minor_damage';
        if ($hull >= 40) return 'significant_damage';
        if ($hull >= 20) return 'critical';
        return 'failing';
    }

    /**
     * Resource warnings, most pressing first in fixed order: fuel, food, hull, vibes.
     *
     * @return array  List of ['code' => 'fuel_low'|'food_low'|'hull_critical'|'vibes_low', 'days' => int|null]
     */
    public function getCriticalWarnings(array $session): array {
        $warnings = [];
        $chad = $session['resources']['chad'];

        $fuelDays = $this->calculateDaysRemaining($session['resources']['fuel'], FUEL_PER_DAY);
        if ($fuelDays < 10) {
            $warnings[] = ['code' => 'fuel_low', 'days' => $fuelDays];
        }

        $foodDays = $this->calculateDaysRemaining($session['resources']['food'], $chad * FOOD_PER_CREW);
        if ($foodDays < 10) {
            $warnings[] = ['code' => 'food_low', 'days' => $foodDays];
        }

        if ($session['resources']['hull'] < 30) {
            $warnings[] = ['code' => 'hull_critical', 'days' => null];
        }

        if ($session['resources']['vibes'] < 30) {
            $warnings[] = ['code' => 'vibes_low', 'days' => null];
        }

        return $warnings;
    }

    /**
     * Everything a status screen needs, as plain data (no text, no emoji).
     *
     * @return array
     */
    public function getStatusDetails(array $session): array {
        $res         = $session['resources'];
        $distance    = $session['progress']['distance'];
        $baseSpeed   = $session['progress']['speed'];
        $multiplier  = $session['progress']['speed_multiplier'] ?? 1.0;
        $effective   = $baseSpeed * $multiplier;
        $remaining   = TOTAL_DISTANCE - $distance;

        $nextWaypoint = $this->getNextWaypoint($session);
        $next = null;
        if ($nextWaypoint !== null) {
            $toNext = $nextWaypoint['distance'] - $distance;
            $next = [
                'id'        => $nextWaypoint['id'],
                'name'      => $nextWaypoint['name'],
                'distance'  => $nextWaypoint['distance'],
                'remaining' => $toNext,
                'days'      => $effective > 0 ? (int) ceil($toNext / $effective) : null,
            ];
        }

        $roster = $this->getChadRoster($session);
        $crew = [];
        for ($i = 0; $i < MAX_CREW; $i++) {
            $member = $roster[$i] ?? null;
            $active = $i < $res['chad'];
            $crew[] = [
                'index'  => $i,
                'name'   => $member['name'] ?? ($session['chad_names'][$i] ?? 'Chad Member ' . ($i + 1)),
                'role'   => $member['role'] ?? $this->getChadTitle($i),
                'trait'  => $member['trait'] ?? null,
                'active' => $active,
                'quote'  => ($active && $member !== null) ? $this->getChadQuote($member, $session, $i) : null,
            ];
        }

        return [
            'day'      => $session['progress']['day'],
            'status'   => $session['status'],
            'progress' => [
                'distance'       => $distance,
                'total_distance' => TOTAL_DISTANCE,
                'remaining'      => $remaining,
                'percent'        => $this->getProgressPercent($session),
                'traveled'       => $session['stats']['total_distance_traveled'] ?? $distance,
            ],
            'speed' => [
                'base'         => $baseSpeed,
                'multiplier'   => $multiplier,
                'effective'    => $effective,
                'boost_active' => $multiplier > 1.0,
            ],
            'days_to_uranus' => $effective > 0 ? (int) ceil($remaining / $effective) : null,
            'next_waypoint'  => $next,
            'at_waypoint'    => $this->getAtWaypoint($session),
            'resources' => [
                'fuel'  => ['amount' => $res['fuel'], 'days' => $this->calculateDaysRemaining($res['fuel'], FUEL_PER_DAY)],
                'food'  => ['amount' => $res['food'], 'days' => $this->calculateDaysRemaining($res['food'], $res['chad'] * FOOD_PER_CREW)],
                'hull'  => ['amount' => $res['hull'], 'band' => $this->getHullBand($res['hull'])],
                'vibes' => ['amount' => $res['vibes'], 'band' => $this->getVibesBand($res['vibes'])],
                'chad'  => ['active' => $res['chad'], 'max' => MAX_CREW],
                'eth'   => $res['eth'],
            ],
            'warnings'      => $this->getCriticalWarnings($session),
            'crew'          => $crew,
            'quote_context' => $this->getQuoteContext($session),
        ];
    }

    /**
     * The route as data: every waypoint with where the ship is relative to it.
     *
     * @return array  ['distance', 'total_distance', 'percent', 'waypoints' => [['id','name','symbol','distance','state']]]
     *                state is 'passed' | 'current' | 'ahead'
     */
    public function getMapData(array $session): array {
        $distance = $session['progress']['distance'];
        $atId     = $session['at_waypoint']['id'] ?? null;

        $waypoints = [];
        foreach ($this->loadWaypoints() as $wp) {
            if ($atId !== null && $wp['id'] === $atId) {
                $state = 'current';
            } elseif ($wp['distance'] <= $distance) {
                $state = 'passed';
            } else {
                $state = 'ahead';
            }
            $waypoints[] = [
                'id'       => $wp['id'],
                'name'     => $wp['name'],
                'symbol'   => $wp['symbol'] ?? null,
                'distance' => $wp['distance'],
                'state'    => $state,
            ];
        }

        return [
            'distance'       => $distance,
            'total_distance' => TOTAL_DISTANCE,
            'percent'        => $this->getProgressPercent($session),
            'waypoints'      => $waypoints,
        ];
    }

    /**
     * An event as a player may see it: title, description and the choices with their public
     * cost and risk hints. Outcomes (probabilities and effects) are left out, so a client
     * can never reveal them. Crew-name placeholders are filled in for this player's crew.
     *
     * Names are picked at random for some placeholders, so describe an event once when it
     * appears and keep the result; asking again may name different crew members.
     */
    public function describeEvent(array $event, array $session): array {
        $reactions = [];
        foreach ($event['chad_reactions'] ?? [] as $template) {
            $reaction = is_string($template) ? $this->parseChadReactionTemplate($template, $session) : $template;
            if (($reaction['says'] ?? '') !== '') {
                $reactions[] = ['name' => $reaction['name'] ?? 'Chad', 'says' => $reaction['says']];
            }
        }

        $choices = [];
        foreach ($event['choices'] as $choice) {
            $choices[] = [
                'id'   => $choice['id'],
                'text' => $choice['text'],
                'cost' => $choice['cost'] ?? null,
                'risk' => $choice['risk'] ?? null,
            ];
        }

        return [
            'id'          => $event['id'],
            'title'       => $event['title'],
            'description' => $this->replaceChadTemplates($event['description'], $session),
            'reactions'   => $reactions,
            'choices'     => $choices,
        ];
    }

    /**
     * Get the next waypoint ahead of the current position.
     *
     * @param  array  $session
     * @return array|null  Next waypoint data or null if none ahead
     */
    public function getNextWaypoint(array $session): ?array {
        $currentDistance = $session['progress']['distance'];
        $waypoints = $this->loadWaypoints();

        foreach ($waypoints as $waypoint) {
            if ($waypoint['distance'] > $currentDistance) {
                return $waypoint;
            }
        }

        return null;
    }

    /**
     * Calculate days remaining for a consumable resource.
     *
     * @param  int  $amount     Current amount of resource
     * @param  int  $perDayRate Consumption rate per day
     * @return int  Days remaining (999 if infinite/invalid rate)
     */
    public function calculateDaysRemaining(int $amount, int $perDayRate): int {
        return $perDayRate > 0 ? (int) floor($amount / $perDayRate) : 999;
    }

    /**
     * Get contextual quote for chad based on game state.
     *
     * @param  array  $chadMember  Chad data with trait
     * @param  array  $session     Game session
     * @param  int    $chadIndex   Chad position (0-4)
     * @return string  Selected quote
     */
    public function getChadQuote(array $chadMember, array $session, int $chadIndex): string {
        $trait = $chadMember['trait'] ?? 'Optimistic';
        $day = $session['progress']['day'];

        // Determine context based on game state
        $context = $this->getQuoteContext($session);

        // Load personality quotes
        $personalities = $this->loadChadPersonalities();
        $traitQuotes = $personalities['personality_quotes'][$trait] ?? [];

        // Try to get contextual quotes
        $quotes = [];
        if (is_array($traitQuotes)) {
            // New structure: nested by context
            if (isset($traitQuotes[$context]) && is_array($traitQuotes[$context])) {
                $quotes = $traitQuotes[$context];
            }
            // Fallback to general context
            elseif (isset($traitQuotes['general']) && is_array($traitQuotes['general'])) {
                $quotes = $traitQuotes['general'];
            }
            // Old structure: flat array (backward compatibility)
            elseif (isset($traitQuotes[0])) {
                $quotes = $traitQuotes;
            }
        }

        // Ultimate fallback
        if (empty($quotes)) {
            $quotes = ["Everything's fine."];
        }

        // Select quote using deterministic hash (rotates based on day + chad + trait)
        $hash = abs(crc32($day . '-' . $chadIndex . '-' . $trait));
        $quoteIndex = $hash % count($quotes);

        return $quotes[$quoteIndex];
    }

    /**
     * Determine quote context based on game state.
     * Priority: low_vibes > critical_resources > near_victory > near_waypoint > general
     *
     * @param  array  $session
     * @return string  Context identifier
     */
    public function getQuoteContext(array $session): string {
        $vibes = $session['resources']['vibes'];
        $chad = $session['resources']['chad'];
        $fuel = $session['resources']['fuel'];
        $food = $session['resources']['food'];
        $distance = $session['progress']['distance'];
        $speedMultiplier = $session['progress']['speed_multiplier'] ?? 1.0;
        $baseSpeed = $session['progress']['speed'];
        $effectiveSpeed = $baseSpeed * $speedMultiplier;
        $status = $session['status'] ?? 'in_space';

        // Calculate days remaining for resources
        $fuelPerDay = FUEL_PER_DAY;
        $foodPerDay = $chad * FOOD_PER_CREW;
        $fuelDays = $this->calculateDaysRemaining($fuel, $fuelPerDay);
        $foodDays = $this->calculateDaysRemaining($food, $foodPerDay);

        // Calculate days to Uranus
        $remainingDistance = TOTAL_DISTANCE - $distance;
        $daysToUranus = $effectiveSpeed > 0 ? (int) ceil($remainingDistance / $effectiveSpeed) : 999;

        // Calculate days to next waypoint
        $daysToNextWaypoint = null;
        $nextWaypoint = $this->getNextWaypoint($session);
        if ($nextWaypoint !== null) {
            $distanceToNext = $nextWaypoint['distance'] - $distance;
            $daysToNextWaypoint = $effectiveSpeed > 0 ? (int) ceil($distanceToNext / $effectiveSpeed) : 999;
        }

        // Priority-based context selection (most urgent first)

        // Post-event contexts (highest priority - show once then clear)
        if (isset($session['recent_event_outcome'])) {
            if ($session['recent_event_outcome'] === 'success') {
                return 'post_event_success';
            }
            if ($session['recent_event_outcome'] === 'failure') {
                return 'post_event_failure';
            }
        }

        // At station (docked, using services)
        if ($status === 'at_station') {
            return 'at_station';
        }

        // Low vibes
        if ($vibes < 30) {
            return 'low_vibes';
        }

        // Critical resources
        if ($fuelDays < 5 || $foodDays < 5) {
            return 'critical_resources';
        }

        // Near victory
        if ($daysToUranus <= 20) {
            return 'near_victory';
        }

        // Near waypoint (approaching station)
        if ($daysToNextWaypoint !== null && $daysToNextWaypoint <= 5) {
            return 'near_waypoint';
        }

        return 'general';
    }


    // =========================================================================
    // IMAGE INTEGRATION
    // =========================================================================


    // =========================================================================
    // ENHANCED EVENT FORMATTING
    // =========================================================================

    /**
     * Look up an event by ID.
     * Returns null if not found — callers must handle this gracefully.
     *
     * @param  string  $eventId
     * @return array|null
     */
    public function getEventById(string $eventId): ?array {
        $events = $this->loadEvents();
        foreach ($events as $event) {
            if ($event['id'] === $eventId) {
                return $event;
            }
        }
        return null;
    }

    // =========================================================================
    // MAP GENERATION
    // =========================================================================

    // =========================================================================
    // CREW HELPERS
    // =========================================================================

    /**
     * Create a chad roster with names, roles, and personality traits.
     * Uses chad_personalities.json for names and traits.
     *
     * @return array  Array of ['name' => string, 'role' => string, 'trait' => string]
     */
    private function createChadRoster(): array {
        $personalities = $this->loadChadPersonalities();
        $traits        = $personalities['traits'] ?? [];
        $firstNames    = $personalities['first_names'] ?? ['Alex', 'Jordan', 'Sam', 'Casey', 'Morgan'];
        $lastNames     = $personalities['last_names'] ?? ['Chen', 'Webb', 'Rodriguez', 'Kim', 'Patel'];

        $roster = [];
        $usedNames = [];

        for ($i = 0; $i < MAX_CREW; $i++) {
            $role = self::CREW_ROLES[$i] ?? 'Chad Member';
            $trait = !empty($traits) ? $traits[array_rand($traits)] : 'Brave';

            // Pick unique name
            do {
                $first = $firstNames[array_rand($firstNames)];
                $last  = $lastNames[array_rand($lastNames)];
                $name = $first . ' ' . $last;
            } while (isset($usedNames[$name]) && count($usedNames) < count($firstNames) * count($lastNames));
            $usedNames[$name] = true;

            $roster[] = ['name' => $name, 'role' => $role, 'trait' => $trait];
        }

        return $roster;
    }

    /**
     * Load chad personalities from JSON (traits, names for roster generation).
     *
     * @return array  ['traits' => [...], 'first_names' => [...], 'last_names' => [...], 'personality_quotes' => [...]]
     */
    private function loadChadPersonalities(): array {
        if (self::$chadPersonalitiesCache !== null) {
            return self::$chadPersonalitiesCache;
        }

        $path = defined('CREW_PERSONALITIES_FILE') ? CREW_PERSONALITIES_FILE : (__DIR__ . '/../data/chad_personalities.json');
        if (!file_exists($path)) {
            self::$chadPersonalitiesCache = [
                'traits'           => ['Brave', 'Optimistic', 'Sarcastic', 'Paranoid', 'Curious'],
                'first_names'      => ['Alex', 'Jordan', 'Sam', 'Casey', 'Morgan'],
                'last_names'       => ['Chen', 'Webb', 'Rodriguez', 'Kim', 'Patel'],
                'personality_quotes' => [],
            ];
            return self::$chadPersonalitiesCache;
        }

        $data = json_decode(file_get_contents($path), true);
        $cp   = $data['chad_personalities'] ?? $data;

        self::$chadPersonalitiesCache = [
            'traits'            => $cp['traits'] ?? [],
            'first_names'       => $cp['chad_names']['first_names'] ?? ['Alex', 'Jordan', 'Sam', 'Casey', 'Morgan'],
            'last_names'        => $cp['chad_names']['last_names'] ?? ['Chen', 'Webb', 'Rodriguez', 'Kim', 'Patel'],
            'personality_quotes' => $cp['personality_quotes'] ?? [],
        ];
        return self::$chadPersonalitiesCache;
    }

    /**
     * Get chad roster for template substitution. Backward-compat: build from chad_names if no roster.
     *
     * @param  array  $session
     * @return array  Array of ['name' => string, 'role' => string, 'trait' => string]
     */
    public function getChadRoster(array $session): array {
        if (!empty($session['chad_roster'])) {
            return $session['chad_roster'];
        }

        // Backward compat: build minimal roster from chad_names
        $names = $session['chad_names'] ?? ['Alex', 'Jordan', 'Sam', 'Casey', 'Morgan'];
        $roster = [];
        for ($i = 0; $i < MAX_CREW; $i++) {
            $roster[] = [
                'name'  => $names[$i] ?? 'Chad Member ' . ($i + 1),
                'role'  => self::CREW_ROLES[$i] ?? 'Chad Member',
                'trait' => 'Brave',
            ];
        }
        return $roster;
    }

    /**
     * Replace chad template placeholders in text.
     * Placeholders: {captain_name}, {engineer_name}, {quant_name}, {medic_name}, {hodler_name},
     *              {paranoid_chad_name}, {chad_name} (random active chad)
     *
     * @param  string  $text
     * @param  array   $session
     * @return string
     */
    public function replaceChadTemplates(string $text, array $session): string {
        $roster = $this->getChadRoster($session);
        $activeCount = $session['resources']['chad'] ?? MAX_CREW;

        $roleToName = [];
        $paranoidName = null;
        foreach ($roster as $i => $member) {
            if ($i < $activeCount) {
                $roleToName[$member['role']] = $member['name'];
                if ($member['trait'] === 'Paranoid' && $paranoidName === null) {
                    $paranoidName = $member['name'];
                }
            }
        }

        $replacements = [
            '{captain_name}'     => $roleToName['Captain'] ?? $roster[0]['name'] ?? 'Chad',
            '{engineer_name}'   => $roleToName['Engineer'] ?? $roster[1]['name'] ?? 'Chad',
            '{quant_name}'      => $roleToName['Quant'] ?? $roster[2]['name'] ?? 'Chad',
            '{medic_name}'      => $roleToName['Medic'] ?? $roster[3]['name'] ?? 'Chad',
            '{hodler_name}'     => $roleToName['HODLer'] ?? $roster[4]['name'] ?? 'Chad',
            '{paranoid_chad_name}' => $paranoidName ?? $roster[0]['name'] ?? 'Chad',
            '{chad_name}'       => $this->getRandomActiveChadName($roster, $activeCount),
        ];

        foreach ($replacements as $placeholder => $value) {
            $text = str_replace($placeholder, $value, $text);
        }
        return $text;
    }

    /**
     * Parse chad reaction template string.
     * Template format: "{role}: Quote text" or just "Quote text"
     * Examples: "{captain}: This is concerning." or "We should be careful."
     *
     * @param  string  $template
     * @param  array   $session
     * @return array   ['name' => 'Chad Member Name', 'says' => 'Quote text']
     */
    private function parseChadReactionTemplate(string $template, array $session): array {
        $roster = $this->getChadRoster($session);
        $activeCount = $session['resources']['chad'] ?? MAX_CREW;

        // Match pattern: {role}: quote
        if (preg_match('/^\{(\w+)\}:\s*(.+)$/s', $template, $matches)) {
            $rolePlaceholder = $matches[1];
            $quote = $matches[2];

            // Map role placeholder to chad name
            $roleMap = [
                'captain' => 'Captain',
                'engineer' => 'Engineer',
                'navigator' => 'Navigator',
                'medic' => 'Medic',
                'specialist' => 'Specialist',
                'chad' => null, // Random chad
            ];

            $targetRole = $roleMap[$rolePlaceholder] ?? null;
            $chadName = 'Chad';

            if ($targetRole === null) {
                // Random active chad
                $chadName = $this->getRandomActiveChadName($roster, $activeCount);
            } else {
                // Find chad with this role
                foreach ($roster as $i => $member) {
                    if ($i < $activeCount && $member['role'] === $targetRole) {
                        $chadName = $member['name'];
                        break;
                    }
                }
            }

            return [
                'name' => $chadName,
                'says' => trim($quote),
            ];
        }

        // No role specified - return as generic chad quote
        return [
            'name' => 'Chad',
            'says' => trim($template),
        ];
    }

    private function getRandomActiveChadName(array $roster, int $activeCount): string {
        $active = array_slice($roster, 0, max(1, $activeCount));
        if (empty($active)) {
            return 'Chad';
        }
        $idx = array_rand($active);
        return $active[$idx]['name'] ?? 'Chad';
    }

    /**
     * Get a random personality quote for a trait (for event outcome flavor).
     *
     * @param  string  $trait
     * @return string|null
     */
    public function getPersonalityQuote(string $trait): ?string {
        $personalities = $this->loadChadPersonalities();
        $traitQuotes = $personalities['personality_quotes'][$trait] ?? null;
        if (empty($traitQuotes)) {
            return null;
        }

        // Pick a random category (general, low_vibes, etc.)
        $categories = array_values($traitQuotes);
        if (empty($categories)) {
            return null;
        }
        $categoryQuotes = $categories[array_rand($categories)];

        // Pick a random quote from that category
        if (empty($categoryQuotes)) {
            return null;
        }
        return $categoryQuotes[array_rand($categoryQuotes)];
    }

    public function getChadTitle(int $position): string {
        return self::CREW_ROLES[$position] ?? 'Chad Member';
    }

    private function loadChadActivities(): array {
        if (self::$chadActivitiesCache !== null) {
            return self::$chadActivitiesCache;
        }

        $filePath = __DIR__ . '/../data/chad_activities.json';
        if (!file_exists($filePath)) {
            self::$chadActivitiesCache = [];
            return self::$chadActivitiesCache;
        }

        $data = json_decode(file_get_contents($filePath), true);
        self::$chadActivitiesCache = $data['chad_activities'] ?? [];
        return self::$chadActivitiesCache;
    }

    public function getRandomChadActivity(string $role): string {
        $activities = $this->loadChadActivities();
        if (empty($activities[$role])) {
            return 'Performing routine duties';
        }
        $roleActivities = $activities[$role];
        return $roleActivities[array_rand($roleActivities)];
    }

    // =========================================================================
    // INTERNAL HELPERS
    // =========================================================================

    /**
     * Load events from the data file, caching in the within-request static.
     *
     * @return array
     */
    private function loadEvents(): array {
        if (self::$eventsCache !== null) {
            return self::$eventsCache;
        }

        $events = [];
        if (file_exists(EVENTS_FILE)) {
            $data   = json_decode(file_get_contents(EVENTS_FILE), true);
            $events = $data['events'] ?? [];
        }
        self::$eventsCache = $events;
        return self::$eventsCache;
    }

    /**
     * Load waypoints from the data file, caching in the within-request static.
     *
     * @return array
     */
    public function loadWaypoints(): array {
        if (self::$waypointsCache !== null) {
            return self::$waypointsCache;
        }

        if (!file_exists(WAYPOINTS_FILE)) {
            self::$waypointsCache = [];
            return self::$waypointsCache;
        }

        $data = json_decode(file_get_contents(WAYPOINTS_FILE), true);
        self::$waypointsCache = $data['waypoints'] ?? [];
        return self::$waypointsCache;
    }

    /**
     * Check whether the ship has crossed a waypoint threshold on this day's advance.
     *
     * NOTE: $session is passed by reference here because this is a private helper
     * that legitimately mutates waypoints_reached. The public API contract (by-value)
     * is maintained because the caller (advanceDay) works on its own copy.
     *
     * @param  array  $session  Must already have updated distance for the current day
     * @return array|null  Waypoint data if one was crossed, null otherwise
     */
    private function checkWaypointReached(array &$session): ?array {
        $waypoints    = $this->loadWaypoints();

        // Calculate effective speed (base speed × multiplier) to properly detect waypoint crossings
        $speedMultiplier = $session['progress']['speed_multiplier'] ?? 1.0;
        $effectiveSpeed = $session['progress']['speed'] * $speedMultiplier;
        $prevDistance = $session['progress']['distance'] - $effectiveSpeed;

        foreach ($waypoints as $wp) {
            if ($prevDistance < $wp['distance'] && $session['progress']['distance'] >= $wp['distance']) {
                if (!in_array($wp['name'], $session['waypoints_reached'])) {
                    $session['waypoints_reached'][] = $wp['name'];
                    return $wp;
                }
            }
        }

        return null;
    }

    /**
     * Pick a random event that the player hasn't completed yet (unless repeatable).
     *
     * @param  array  $session  Passed by reference for normaliseEventsCompleted() migration
     * @return array|null
     */
    private function getRandomEvent(array &$session): ?array {
        $events = $this->loadEvents();
        if (empty($events)) {
            return null;
        }

        $this->normaliseEventsCompleted($session);

        $available = [];
        foreach ($events as $event) {
            $completed  = isset($session['events_completed'][$event['id']]);
            $repeatable = $event['repeatable'] ?? false;

            if (!$completed || $repeatable) {
                $available[] = $event;
            }
        }

        return empty($available) ? null : $available[array_rand($available)];
    }

    /**
     * Pick a random hunt-eligible event (for Hunt Resources action).
     *
     * @param  array  $session  Passed by reference for normaliseEventsCompleted() migration
     * @return array|null
     */
    private function getRandomHuntEvent(array &$session): ?array {
        $events = $this->loadEvents();
        if (empty($events)) {
            return null;
        }

        $this->normaliseEventsCompleted($session);

        $available = [];
        foreach ($events as $event) {
            if (empty($event['hunt_eligible'])) {
                continue;
            }
            $completed  = isset($session['events_completed'][$event['id']]);
            $repeatable = $event['repeatable'] ?? false;

            if (!$completed || $repeatable) {
                $available[] = $event;
            }
        }

        return empty($available) ? null : $available[array_rand($available)];
    }

    /**
     * Select an outcome from a weighted probability table.
     *
     * Probabilities should sum to 1.0. If floating-point imprecision causes the
     * cumulative sum to fall just short of the random value, the last outcome is
     * returned as the "everything else" bucket — not the first, which would skew
     * probability toward the first entry when tables don't sum to exactly 1.0.
     *
     * @param  array  $outcomes  Array of ['probability' => float, 'message' => ..., 'effects' => ...]
     * @return array  Selected outcome
     */
    private function selectOutcome(array $outcomes): array {
        $rand       = rand(1, 100) / 100;
        $cumulative = 0.0;

        foreach ($outcomes as $outcome) {
            $cumulative += $outcome['probability'];
            if ($rand <= $cumulative) {
                return $outcome;
            }
        }

        // Floating-point fallback: return last outcome as the "remainder" bucket
        return end($outcomes);
    }

    /**
     * Classify an event outcome as 'success' or 'failure' based on effects.
     * Used to determine contextual chad quotes after events.
     *
     * @param  array  $effects       Resource effects from event outcome
     * @param  array  $deathMessages Death messages if any chad died
     * @return string 'success' or 'failure'
     */
    private function classifyEventOutcome(array $effects, array $deathMessages): string {
        // Chad death = always failure
        if (!empty($deathMessages)) {
            return 'failure';
        }

        // Check for explicit chad loss in effects
        if (isset($effects['chad']) && $effects['chad'] < 0) {
            return 'failure';
        }

        // Calculate net resource change
        $netChange = 0;

        // Positive effects
        if (isset($effects['fuel']) && $effects['fuel'] > 0) $netChange += $effects['fuel'] / 10; // Weight fuel
        if (isset($effects['food']) && $effects['food'] > 0) $netChange += $effects['food'] / 20; // Weight food
        if (isset($effects['eth']) && $effects['eth'] > 0) $netChange += $effects['eth'] / 100; // Weight ETH
        if (isset($effects['hull']) && $effects['hull'] > 0) $netChange += $effects['hull']; // Direct value
        if (isset($effects['vibes']) && $effects['vibes'] > 0) $netChange += $effects['vibes']; // Direct value

        // Negative effects
        if (isset($effects['fuel']) && $effects['fuel'] < 0) $netChange += $effects['fuel'] / 10;
        if (isset($effects['food']) && $effects['food'] < 0) $netChange += $effects['food'] / 20;
        if (isset($effects['eth']) && $effects['eth'] < 0) $netChange += $effects['eth'] / 100;
        if (isset($effects['hull']) && $effects['hull'] < 0) $netChange += $effects['hull'] * 1.5; // Hull damage weighted heavily
        if (isset($effects['vibes']) && $effects['vibes'] < 0) $netChange += $effects['vibes'];

        // Classify based on net change
        return $netChange >= 0 ? 'success' : 'failure';
    }

    /**
     * Apply a set of resource effects to the session.
     * Chad deaths are named — the last active chad (by position) is identified
     * from session['chad_names'] and a death message is generated.
     * All resources are clamped: floor at 0, hull and vibes ceiling at 100,
     * chad ceiling at MAX_CREW.
     *
     * @param  array  $session  Passed by reference (private helper — public callers use by-value)
     * @param  array  $effects  Map of resource => change (e.g. ['fuel' => -20, 'chad' => -1])
     * @return array  Names of chad who perished (may be empty)
     */
    private function applyEffects(array &$session, array $effects): array {
        $deadNames = [];

        foreach ($effects as $resource => $change) {
            if ($resource === 'chad') {
                if ($change < 0) {
                    $deaths = abs($change);
                    for ($i = 0; $i < $deaths; $i++) {
                        // The last active chad by index is the one who perishes
                        $deadIndex = ($session['resources']['chad'] - 1) - $i;
                        if ($deadIndex >= 0 && isset($session['chad_names'][$deadIndex])) {
                            $deadNames[] = $session['chad_names'][$deadIndex];
                        }
                    }
                    $session['resources']['chad'] += $change;
                    $session['stats']['chad_lost'] += $deaths;
                } else {
                    $session['resources']['chad'] += $change;
                }
                // Clamp chad to [0, MAX_CREW]
                $session['resources']['chad'] = max(0, min(MAX_CREW, $session['resources']['chad']));
            } else {
                $session['resources'][$resource] = ($session['resources'][$resource] ?? 0) + $change;

                // Clamp floor at 0
                if (isset($session['resources'][$resource])) {
                    $session['resources'][$resource] = max(0, $session['resources'][$resource]);
                    // Ceiling for percentage resources
                    if ($resource === 'hull' || $resource === 'vibes') {
                        $session['resources'][$resource] = min(100, $session['resources'][$resource]);
                    }
                    // Ceiling for capacity-limited resources
                    if ($resource === 'fuel') {
                        $session['resources'][$resource] = min(MAX_FUEL, $session['resources'][$resource]);
                    }
                    if ($resource === 'food') {
                        $session['resources'][$resource] = min(MAX_FOOD, $session['resources'][$resource]);
                    }
                }
            }
        }

        return $deadNames;
    }

    /**
     * Calculate resource deltas between before and after states.
     * Only returns resources that actually changed.
     *
     * @param  array  $before  Resources before effects
     * @param  array  $after   Resources after effects
     * @return array  Map of resource => ['before' => X, 'after' => Y, 'delta' => Z]
     */
    private function calculateResourceDeltas(array $before, array $after): array {
        $changes = [];
        $allResources = array_unique(array_merge(array_keys($before), array_keys($after)));

        foreach ($allResources as $resource) {
            $beforeVal = $before[$resource] ?? 0;
            $afterVal = $after[$resource] ?? 0;

            if ($beforeVal !== $afterVal) {
                $changes[$resource] = [
                    'before' => $beforeVal,
                    'after' => $afterVal,
                    'delta' => $afterVal - $beforeVal
                ];
            }
        }

        return $changes;
    }

    /**
     * Check whether any game-over condition has been met.
     *
     * @param  array  $session
     * @return string|null  Reason code, or null if the game continues:
     *                       'out_of_fuel' | 'starved' | 'crew_lost' | 'hull_breached'
     */
    public function checkGameOver(array $session): ?string {
        if ($session['resources']['fuel'] <= 0) {
            return 'out_of_fuel';
        }
        if ($session['resources']['food'] <= 0) {
            return 'starved';
        }
        if ($session['resources']['chad'] <= 0) {
            return 'crew_lost';
        }
        if ($session['resources']['hull'] <= 0) {
            return 'hull_breached';
        }
        return null;
    }

    /**
     * Normalise events_completed from the old sequential array format to the new
     * associative format. Modifies $session in place; the converted format will be
     * persisted on the next saveSession() call, migrating old sessions transparently.
     *
     * Old format: ['event_meteor', 'event_alien']
     * New format: ['event_meteor' => true, 'event_alien' => true]
     *
     * @param  array  $session  Passed by reference
     */
    private function normaliseEventsCompleted(array &$session): void {
        if (!isset($session['events_completed'])) {
            $session['events_completed'] = [];
            return;
        }

        // Detect sequential array (old format): keys are 0, 1, 2, ...
        // An empty array passes array_is_list() in PHP 8.1+ but we support older PHP
        $ec = $session['events_completed'];
        if (!empty($ec) && array_keys($ec) === range(0, count($ec) - 1)) {
            $session['events_completed'] = array_fill_keys(array_values($ec), true);
        }
    }

    /**
     * Calculate journey progress as an integer percentage.
     * @return int Progress percentage (0-100)
     */
    public function getProgressPercent(array $session): int {
        return min(100, (int) round(($session['progress']['distance'] / TOTAL_DISTANCE) * 100));
    }

    /**
     * Check whether the player is currently at a waypoint (docked).
     *
     * @param  array  $session
     * @return bool
     */
    public function isAtWaypoint(array $session): bool {
        return isset($session['at_waypoint']) && $session['at_waypoint'] !== null;
    }

    /**
     * Get the waypoint the player is currently at, or null.
     *
     * @param  array  $session
     * @return array|null  Waypoint data or null
     */
    public function getAtWaypoint(array $session): ?array {
        return $session['at_waypoint'] ?? null;
    }

    /**
     * Get the current waypoint the player is at or just passed.
     * Returns the last waypoint with distance <= current position.
     *
     * @param array $session Current session state
     * @return array|null Waypoint data or null if before first waypoint
     */
    public function getCurrentWaypoint(array $session): ?array {
        $waypoints = $this->loadWaypoints();
        $currentDistance = $session['progress']['distance'];

        $currentWaypoint = null;
        foreach ($waypoints as $wp) {
            if ($wp['distance'] <= $currentDistance) {
                $currentWaypoint = $wp;
            } else {
                break; // Waypoints are sorted, no need to continue
            }
        }

        return $currentWaypoint;
    }

    /**
     * Select content from waypoint with exclusion of previously seen variants.
     * Randomly picks from available options, excluding what player saw last time.
     *
     * @param  array  $waypoint       Waypoint data with descriptions/local_stories
     * @param  array  $seenContent    Previously seen content IDs for this waypoint
     * @return array  ['waypoint' => modified waypoint, 'selected_ids' => ['description_id' => ..., 'story_id' => ...]]
     */
    public function selectWaypointContent(array $waypoint, array $seenContent): array {
        $waypointId = $waypoint['id'] ?? 'unknown';
        $selectedIds = [];

        // Select description (exclude previously seen)
        if (!empty($waypoint['descriptions']) && is_array($waypoint['descriptions'])) {
            $lastSeenDescId = $seenContent['description_id'] ?? null;

            // Filter out previously seen
            $availableDescs = array_filter($waypoint['descriptions'], function($desc) use ($lastSeenDescId) {
                return $desc['id'] !== $lastSeenDescId;
            });

            // If all seen (or only one option), reset and allow all
            if (empty($availableDescs)) {
                $availableDescs = $waypoint['descriptions'];
            }

            // Random selection
            $availableDescs = array_values($availableDescs); // Re-index
            $selected = $availableDescs[array_rand($availableDescs)];

            $waypoint['description'] = $selected['text'];
            $selectedIds['description_id'] = $selected['id'];
        }

        // Select story (exclude previously seen)
        if (!empty($waypoint['local_stories']) && is_array($waypoint['local_stories'])) {
            $lastSeenStoryId = $seenContent['story_id'] ?? null;

            // Filter out previously seen
            $availableStories = array_filter($waypoint['local_stories'], function($story) use ($lastSeenStoryId) {
                return $story['id'] !== $lastSeenStoryId;
            });

            // If all seen (or only one option), reset and allow all
            if (empty($availableStories)) {
                $availableStories = $waypoint['local_stories'];
            }

            // Random selection
            $availableStories = array_values($availableStories); // Re-index
            $selected = $availableStories[array_rand($availableStories)];

            $waypoint['local_story'] = $selected['text'];
            $selectedIds['story_id'] = $selected['id'];
        }

        return [
            'waypoint' => $waypoint,
            'selected_ids' => $selectedIds
        ];
    }

    // =========================================================================
    // LEADERBOARD
    // =========================================================================

    /**
     * Load leaderboard data from disk.
     * Returns 5 leaderboard categories with entries sorted appropriately.
     *
     * Schema:
     * {
     *   "speed_demon": [{"user_id": 123, "username": "alice", "days": 45, "chad": 5, ...}, ...],
     *   "perfect_captain": [...],
     *   "space_tycoon": [...],
     *   "resource_master": [...],
     *   "event_master": [...]
     * }
     *
     * @return array  5 leaderboard categories
     */
    public function getLeaderboard(): array {
        $emptyBoards = [
            'speed_demon' => [],
            'perfect_captain' => [],
            'space_tycoon' => [],
            'resource_master' => [],
            'event_master' => []
        ];

        if (!defined('LEADERBOARD_FILE') || !file_exists(LEADERBOARD_FILE)) {
            return $emptyBoards;
        }

        $json = @file_get_contents(LEADERBOARD_FILE);
        if ($json === false) {
            error_log("GameEngine: failed to read leaderboard file");
            return $emptyBoards;
        }

        $data = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("GameEngine: leaderboard file corrupt, resetting");
            return $emptyBoards;
        }

        // Ensure all board keys exist
        foreach ($emptyBoards as $board => $default) {
            $data[$board] = $data[$board] ?? $default;
        }

        // Sort each board by its primary metric
        // Speed Demon & Perfect Captain: days (ascending = faster)
        usort($data['speed_demon'], fn($a, $b) => ($a['days'] ?? 999) <=> ($b['days'] ?? 999));
        usort($data['perfect_captain'], fn($a, $b) => ($a['days'] ?? 999) <=> ($b['days'] ?? 999));

        // Space Tycoon: ETH (descending = more is better)
        usort($data['space_tycoon'], fn($a, $b) => ($b['eth'] ?? 0) <=> ($a['eth'] ?? 0));

        // Resource Master: resource_score (descending = more is better)
        usort($data['resource_master'], fn($a, $b) => ($b['resource_score'] ?? 0) <=> ($a['resource_score'] ?? 0));

        // Event Master: event_net_total (descending = more is better)
        usort($data['event_master'], fn($a, $b) => ($b['event_net_total'] ?? 0) <=> ($a['event_net_total'] ?? 0));

        // Keep only top 50 per board
        foreach (array_keys($emptyBoards) as $board) {
            $data[$board] = array_slice($data[$board], 0, 50);
        }

        return $data;
    }

    /**
     * Save a victory to all applicable leaderboards.
     * Each victory is saved to multiple boards based on criteria.
     *
     * @param  array  $session  Completed game session
     * @return bool   True if saved successfully
     */
    public function saveToLeaderboard(array $session): bool {
        if (!defined('LEADERBOARD_FILE')) {
            return false;
        }

        $leaderboard = $this->getLeaderboard();

        // Extract common data
        $userId = $session['user_id'] ?? 0;
        $username = $session['username'] ?? 'Unknown';
        $days = $session['progress']['day'] ?? 0;
        $chad = $session['resources']['chad'] ?? 0;
        $fuel = $session['resources']['fuel'] ?? 0;
        $food = $session['resources']['food'] ?? 0;
        $eth = $session['resources']['eth'] ?? 0;
        $hull = $session['resources']['hull'] ?? 0;
        $vibes = $session['resources']['vibes'] ?? 0;
        $timestamp = time();

        // Calculate derived metrics
        $resourceScore = ($fuel * 1) + ($food * 0.5);
        $eventNetFuel = $session['stats']['event_net_fuel'] ?? 0;
        $eventNetFood = $session['stats']['event_net_food'] ?? 0;
        $eventNetEth = $session['stats']['event_net_eth'] ?? 0;
        $eventNetHull = $session['stats']['event_net_hull'] ?? 0;
        $eventNetTotal = $eventNetFuel + $eventNetFood + $eventNetEth + $eventNetHull;

        // 1. Speed Demon - All victories qualify
        $leaderboard['speed_demon'][] = [
            'user_id' => $userId,
            'username' => $username,
            'days' => $days,
            'chad' => $chad,
            'timestamp' => $timestamp,
        ];

        // 2. Perfect Captain - Only if chad = 5 AND hull = 100
        if ($chad === 5 && $hull === 100) {
            $leaderboard['perfect_captain'][] = [
                'user_id' => $userId,
                'username' => $username,
                'days' => $days,
                'chad' => $chad,
                'hull' => $hull,
                'timestamp' => $timestamp,
            ];
        }

        // 3. Space Tycoon - All victories qualify
        $leaderboard['space_tycoon'][] = [
            'user_id' => $userId,
            'username' => $username,
            'eth' => $eth,
            'days' => $days,
            'timestamp' => $timestamp,
        ];

        // 4. Resource Master - All victories qualify
        $leaderboard['resource_master'][] = [
            'user_id' => $userId,
            'username' => $username,
            'resource_score' => $resourceScore,
            'fuel' => $fuel,
            'food' => $food,
            'timestamp' => $timestamp,
        ];

        // 5. Event Master - All victories qualify
        $leaderboard['event_master'][] = [
            'user_id' => $userId,
            'username' => $username,
            'event_net_total' => $eventNetTotal,
            'event_net_fuel' => $eventNetFuel,
            'event_net_food' => $eventNetFood,
            'event_net_eth' => $eventNetEth,
            'event_net_hull' => $eventNetHull,
            'timestamp' => $timestamp,
        ];

        // Sort and truncate each board
        usort($leaderboard['speed_demon'], fn($a, $b) => ($a['days'] ?? 999) <=> ($b['days'] ?? 999));
        usort($leaderboard['perfect_captain'], fn($a, $b) => ($a['days'] ?? 999) <=> ($b['days'] ?? 999));
        usort($leaderboard['space_tycoon'], fn($a, $b) => ($b['eth'] ?? 0) <=> ($a['eth'] ?? 0));
        usort($leaderboard['resource_master'], fn($a, $b) => ($b['resource_score'] ?? 0) <=> ($a['resource_score'] ?? 0));
        usort($leaderboard['event_master'], fn($a, $b) => ($b['event_net_total'] ?? 0) <=> ($a['event_net_total'] ?? 0));

        foreach (['speed_demon', 'perfect_captain', 'space_tycoon', 'resource_master', 'event_master'] as $board) {
            $leaderboard[$board] = array_slice($leaderboard[$board], 0, 50);
        }

        // Write atomically (temp file + rename)
        $dir = dirname(LEADERBOARD_FILE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $tmpFile = LEADERBOARD_FILE . '.tmp.' . getmypid();
        $json    = json_encode($leaderboard, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (file_put_contents($tmpFile, $json) === false) {
            error_log("GameEngine: failed to write temp leaderboard file");
            @unlink($tmpFile);
            return false;
        }

        if (!rename($tmpFile, LEADERBOARD_FILE)) {
            error_log("GameEngine: failed to rename temp leaderboard file");
            @unlink($tmpFile);
            return false;
        }

        return true;
    }

    // =========================================================================
    // SESSION STATE MANAGEMENT
    // =========================================================================

    /**
     * Launch the ship from pre_launch status to in_space.
     * This is the only valid status transition for launching.
     *
     * @param  array  $session  Current session state
     * @return array  Modified session with status = 'in_space'
     */
    public function launchShip(array $session): array {
        if ($session['status'] !== 'pre_launch') {
            // Already launched or invalid state - return unchanged
            return $session;
        }

        $session['status'] = 'in_space';
        return $session;
    }

    /**
     * Stop at a waypoint.
     * - Clears pending_station_arrival
     * - Sets status to 'at_station'
     * - Sets at_waypoint reference
     * - Resets speed multiplier to 1.0 (loses gravitational assist)
     *
     * @param  array  $session   Current session state
     * @param  array  $waypoint  Waypoint data
     * @return array  ['session' => modified session, 'had_boost' => bool]
     */
    public function stopAtWaypoint(array $session, array $waypoint): array {
        // Clear pending arrival
        unset($session['pending_station_arrival']);

        // Set status and waypoint reference
        $session['status'] = 'at_station';
        $session['at_waypoint'] = $waypoint;

        // Track waypoint stop for leaderboard stats
        $session['stats']['waypoints_stopped'] = ($session['stats']['waypoints_stopped'] ?? 0) + 1;

        // Reset gravitational assist when stopping
        if (!isset($session['progress']['speed_multiplier'])) {
            $session['progress']['speed_multiplier'] = 1.0;
        }
        $hadBoost = $session['progress']['speed_multiplier'] > 1.0;
        $session['progress']['speed_multiplier'] = 1.0;

        return [
            'session' => $session,
            'had_boost' => $hadBoost,
        ];
    }

    /**
     * Skip a waypoint (bypass without stopping).
     * - Clears pending_station_arrival
     * - Clears at_waypoint reference
     * - Applies gravitational assist (2x speed multiplier)
     *
     * @param  array  $session   Current session state
     * @param  array  $waypoint  Waypoint data (for logging/context)
     * @return array  ['session' => modified session, 'was_already_boosted' => bool]
     */
    public function skipWaypoint(array $session, array $waypoint): array {
        // Clear pending arrival
        unset($session['pending_station_arrival']);

        // Clear waypoint reference (not stopping)
        $session['at_waypoint'] = null;

        // Set status to in_space (skipping means we're traveling, not stopped)
        $session['status'] = 'in_space';

        // Initialize multiplier if missing (for old sessions)
        if (!isset($session['progress']['speed_multiplier'])) {
            $session['progress']['speed_multiplier'] = 1.0;
        }

        // Apply gravitational assist
        $wasAlreadyBoosted = $session['progress']['speed_multiplier'] > 1.0;
        $session['progress']['speed_multiplier'] = 2.0;

        return [
            'session' => $session,
            'was_already_boosted' => $wasAlreadyBoosted,
        ];
    }

    /**
     * Choose which story and description the player reads at a station, remember what was
     * shown so the next visit differs, and store the chosen version as the current waypoint.
     *
     * Call when the ship docks. Both the bot and the mini-app then read the same story from
     * $session['at_waypoint'] instead of picking their own.
     *
     * @param  array  $session  Current session state (not modified, by value)
     * @param  array  $station  Full waypoint data (descriptions, local_stories, ...)
     * @return array  ['session' => updated, 'station' => waypoint with description/local_story chosen]
     */
    public function selectStationContent(array $session, array $station): array {
        $stationId = $station['id'] ?? 'unknown';

        if (!isset($session['seen_waypoint_content'])) {
            $session['seen_waypoint_content'] = [];
        }
        $seen = $session['seen_waypoint_content'][$stationId] ?? [];

        $result  = $this->selectWaypointContent($station, $seen);
        $chosen  = $result['waypoint'];
        // The alternatives are not needed once one is chosen
        unset($chosen['descriptions'], $chosen['local_stories']);

        $session['seen_waypoint_content'][$stationId] = $result['selected_ids'];
        $session['at_waypoint'] = $chosen;

        return ['session' => $session, 'station' => $chosen];
    }
}

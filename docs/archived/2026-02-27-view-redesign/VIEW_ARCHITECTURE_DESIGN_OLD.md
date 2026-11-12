# View Architecture Design Document

**Date:** 2026-02-26
**Status:** Design Phase
**Decision:** Day summary format becomes standard Journey View

---

## Design Principles

### 1. Views Are Pure Functions of State
```php
// A view renders ONLY from session state, never from action results
View::render('journey', $session) === View::render('journey', $session)

// ALWAYS the same output for the same session state
```

### 2. Actions and Views Are Completely Separated
```php
// Actions mutate state
$newSession = Action::execute('advance_day', $oldSession);

// Views render state
$view = View::render('journey', $newSession);

// NEVER: Action decides which view to show
// NEVER: View contains action logic
```

### 3. Session State Is the Single Source of Truth
```php
// Everything needed to render ANY view is in session
// No "context" parameters, no "result" objects needed
$view = View::render('journey', $session);  // That's it!
```

---

## Complete Architecture

```
┌─────────────────────────────────────────────────┐
│          TELEGRAM WEBHOOK                       │
│  (POST /uranus_game_bot.php)                   │
└─────────────────┬───────────────────────────────┘
                  │
                  │ Update object (message/callback)
                  │
┌─────────────────▼───────────────────────────────┐
│          REQUEST PARSER                         │
│  Extracts: userId, chatId, button/command       │
└─────────────────┬───────────────────────────────┘
                  │
                  │ Parsed request
                  │
┌─────────────────▼───────────────────────────────┐
│          GAME CONTROLLER                        │
│  Single entry point: handle($button, $context)  │
│                                                  │
│  1. Load session                                │
│  2. Route to action (if needed)                 │
│  3. Save session (if changed)                   │
│  4. Select view to display                      │
│  5. Render and send                             │
└────┬────────────────────────────────────────┬───┘
     │                                        │
     │                                        │
┌────▼─────────────┐              ┌──────────▼─────┐
│  ACTION MANAGER  │              │  VIEW MANAGER  │
│                  │              │                │
│  execute()       │              │  render()      │
│  - advance_day   │              │  - journey     │
│  - rest          │              │  - status      │
│  - hunt          │              │  - map         │
│  - return        │              │  - event       │
│  - shop_purchase │              │  - shop        │
│  - event_choice  │              │  - game_over   │
│                  │              │  - victory     │
│  Returns:        │              │                │
│  - new session   │              │  Returns:      │
│  - metadata      │              │  - message     │
│                  │              │  - keyboard    │
│                  │              │  - image_url   │
└────┬─────────────┘              └──────────┬─────┘
     │                                       │
     │ Uses                                  │ Uses
     │                                       │
┌────▼───────────────────────────────────────▼─────┐
│          GAME ENGINE (Pure Logic)                │
│  - advanceDay(), rest(), hunt()                  │
│  - processEventChoice()                          │
│  - checkGameOver(), checkVictory()               │
│  - formatResourcesSummary()                      │
└──────────────────────────────────────────────────┘
```

---

## Layer 1: Game Controller

### Single Entry Point

```php
class GameController {
    public function handle(string $button, array $context): void {
        $userId = $context['userId'];
        $chatId = $context['chatId'];

        // 1. Load session
        $session = $this->sessionManager->loadSession($userId);

        if ($session === null) {
            $this->renderView('no_session', [], $context);
            return;
        }

        // 2. Execute action if button triggers one
        $actionResult = $this->actionManager->executeIfNeeded($button, $session);

        if ($actionResult) {
            $session = $actionResult['session'];
            $this->sessionManager->saveSession($userId, $session);
        }

        // 3. Select view based on session state + button
        $viewName = $this->selectView($button, $session, $actionResult);

        // 4. Render and send
        $this->renderView($viewName, $session, $context);
    }

    private function selectView(string $button, array $session, ?array $actionResult): string {
        // Priority 1: Terminal states in session
        if ($session['status'] === 'game_over') {
            return 'game_over';
        }
        if ($session['status'] === 'victory') {
            return 'victory';
        }
        if ($session['status'] === 'pre_launch' || $session['status'] === 'at_waypoint') {
            return 'shop';
        }

        // Priority 2: Pending events in session
        if (!empty($session['pending_event'])) {
            return 'event';
        }

        // Priority 3: Button explicitly requests a view
        $buttonViewMap = [
            'continue' => 'journey',  // After action, show journey
            'journey' => 'journey',
            'status' => 'status',
            'map' => 'map',
            'shop' => 'shop',
            'rest' => 'journey',      // After rest action, show journey
            'hunt' => 'journey',      // After hunt action, show journey or event
            'return' => 'journey',    // After return action, show journey
        ];

        return $buttonViewMap[$button] ?? 'journey';
    }
}
```

---

## Layer 2: Action Manager

### Action Execution

```php
class ActionManager {
    private GameEngine $gameEngine;

    public function executeIfNeeded(string $button, array $session): ?array {
        // Map buttons to actions
        $buttonActionMap = [
            'continue' => 'advance_day',
            'rest' => 'rest',
            'hunt' => 'hunt',
            'return' => 'return_to_waypoint',
            // Note: 'journey', 'status', 'map' have NO action - pure navigation
        ];

        $action = $buttonActionMap[$button] ?? null;

        if ($action === null) {
            return null;  // No action needed, just navigation
        }

        return $this->execute($action, $session);
    }

    public function execute(string $action, array $session, array $params = []): array {
        switch ($action) {
            case 'advance_day':
                $result = $this->gameEngine->advanceDay($session);
                return $this->normalizeResult($result);

            case 'rest':
                $result = $this->gameEngine->rest($session);
                return $this->normalizeResult($result);

            case 'hunt':
                $result = $this->gameEngine->huntResources($session);
                return $this->normalizeResult($result);

            case 'return_to_waypoint':
                $result = $this->gameEngine->returnToLastWaypoint($session);
                return $this->normalizeResult($result);

            case 'shop_purchase':
                $result = $this->gameEngine->purchaseItem(
                    $session,
                    $params['item'],
                    $params['quantity']
                );
                return $this->normalizeResult($result);

            case 'event_choice':
                $result = $this->gameEngine->processEventChoice(
                    $session,
                    $params['event_id'],
                    $params['choice_id']
                );
                return $this->normalizeResult($result);

            default:
                throw new \InvalidArgumentException("Unknown action: $action");
        }
    }

    /**
     * Normalize action results to standard format.
     * Updates session state based on result flags.
     */
    private function normalizeResult(array $result): array {
        $session = $result['session'];

        // Set terminal states
        if ($result['game_over'] ?? false) {
            $session['status'] = 'game_over';
            $session['game_over_reason'] = $result['reason'] ?? 'Unknown';
        }

        if ($result['victory'] ?? false) {
            $session['status'] = 'victory';
        }

        // Pending event
        if (isset($result['event'])) {
            $session['pending_event'] = $result['event']['id'];
        }

        return [
            'session' => $session,
            'metadata' => [
                'action_message' => $result['message'] ?? null,
                'game_over' => $result['game_over'] ?? false,
                'victory' => $result['victory'] ?? false,
            ],
        ];
    }
}
```

---

## Layer 3: View Manager

### View Rendering (Pure Functions)

```php
class ViewManager {
    private GameEngine $gameEngine;

    /**
     * Render a view from session state.
     * PURE FUNCTION: Same input always produces same output.
     */
    public function render(string $viewName, array $session): array {
        switch ($viewName) {
            case 'journey':
                return $this->renderJourneyView($session);
            case 'status':
                return $this->renderStatusView($session);
            case 'map':
                return $this->renderMapView($session);
            case 'event':
                return $this->renderEventView($session);
            case 'shop':
                return $this->renderShopView($session);
            case 'game_over':
                return $this->renderGameOverView($session);
            case 'victory':
                return $this->renderVictoryView($session);
            case 'no_session':
                return $this->renderNoSessionView();
            default:
                throw new \InvalidArgumentException("Unknown view: $viewName");
        }
    }

    /**
     * JOURNEY VIEW - Standard format with day summary
     *
     * This is THE canonical journey view. It shows:
     * - Current day
     * - Waypoint reached (if any)
     * - Travel status
     * - Total KM traveled (odometer)
     * - KM remaining to Uranus
     * - Resource summary
     * - Chad and morale
     *
     * ALWAYS looks the same for a given session state.
     */
    private function renderJourneyView(array $session): array {
        $message = $this->buildJourneyMessage($session);

        return [
            'message' => $message,
            'image_url' => $this->gameEngine->getContextImageUrl('day', $session),
            'keyboard' => $this->gameEngine->getJourneyKeyboard($session),
            'parse_mode' => 'HTML',
        ];
    }

    /**
     * Build the journey message from session state.
     * Extracted so it can be reused/tested independently.
     */
    private function buildJourneyMessage(array $session): string {
        $message = "📅 <b>Day {$session['progress']['day']}</b>\n\n";

        // Waypoint status
        if (isset($session['at_waypoint']) && $session['at_waypoint']) {
            $wp = $session['at_waypoint'];
            $message .= "📍 <b>Docked at {$wp['name']}</b>\n";
            if ($wp['type'] === 'station') {
                $message .= "🛰️ Station Services Available\n";
            }
            $message .= "\n";
        } else {
            $message .= "🚀 Cruising through space...\n\n";
        }

        // Progress
        $totalTraveled = $session['stats']['total_distance_traveled'] ?? $session['progress']['distance'];
        $remaining = TOTAL_DISTANCE - $session['progress']['distance'];

        $message .= "📍 Total KM Travelled: " . $this->gameEngine->formatDistance($totalTraveled) . "\n";
        $message .= "🎯 KM to Uranus: " . $this->gameEngine->formatDistance($remaining) . "\n\n";

        // Resources
        $message .= $this->gameEngine->formatResourcesSummary($session);

        // Chad status
        $message .= "\n👥 Chad: {$session['resources']['chad']}/5 | ";
        $message .= "😊 Morale: {$session['resources']['morale']}%";

        return $message;
    }

    /**
     * STATUS VIEW - Detailed chad and ship information
     */
    private function renderStatusView(array $session): array {
        return [
            'message' => $this->gameEngine->formatStatus($session, null, null, true),
            'image_url' => $this->gameEngine->getContextImageUrl('status', $session),
            'keyboard' => $this->gameEngine->getStatusKeyboard($session),
            'parse_mode' => 'HTML',
        ];
    }

    /**
     * MAP VIEW - Solar system navigation map
     */
    private function renderMapView(array $session): array {
        return [
            'message' => $this->gameEngine->generateMap($session),
            'image_url' => null,  // Map is ASCII art
            'keyboard' => $this->gameEngine->getMapKeyboard(),
            'parse_mode' => null,  // Plain text for ASCII alignment
        ];
    }

    /**
     * EVENT VIEW - Active event with choices
     */
    private function renderEventView(array $session): array {
        $eventId = $session['pending_event'];
        $event = $this->gameEngine->getEventById($eventId);

        if (!$event) {
            // Fallback to journey if event not found
            return $this->renderJourneyView($session);
        }

        if (isset($event['chad_reactions'])) {
            $message = $this->gameEngine->formatEnhancedEvent($event, null, null);
            $keyboard = ['inline_keyboard' => $this->gameEngine->createEnhancedEventKeyboard($event)];
        } else {
            $message = $this->buildStandardEventMessage($event);
            $keyboard = $this->buildStandardEventKeyboard($event);
        }

        return [
            'message' => $message,
            'image_url' => null,
            'keyboard' => $keyboard,
            'parse_mode' => 'HTML',
        ];
    }

    private function buildStandardEventMessage(array $event): string {
        $message = "🚨 <b>EVENT!</b> 🚨\n\n";
        $message .= "<b>" . $event['title'] . "</b>\n\n";
        $message .= $event['description'];
        return $message;
    }

    private function buildStandardEventKeyboard(array $event): array {
        $rows = [];
        foreach ($event['choices'] as $choice) {
            $rows[] = [[
                'text' => $choice['text'],
                'callback_data' => "event|{$event['id']}|{$choice['id']}"
            ]];
        }
        return ['inline_keyboard' => $rows];
    }

    /**
     * SHOP VIEW - Purchase supplies
     */
    private function renderShopView(array $session): array {
        return [
            'message' => $this->gameEngine->formatShop($session, null, null),
            'image_url' => $this->gameEngine->getContextImageUrl('shop', $session),
            'keyboard' => $this->gameEngine->getShopKeyboard($session),
            'parse_mode' => 'HTML',
        ];
    }

    /**
     * GAME OVER VIEW
     */
    private function renderGameOverView(array $session): array {
        $message = "💀 <b>MISSION FAILED</b>\n\n";
        $message .= ($session['game_over_reason'] ?? 'Your journey has ended.') . "\n\n";
        $message .= "📅 You survived <b>{$session['progress']['day']} days</b>\n";
        $message .= "📍 Reached <b>" . $this->gameEngine->formatDistance($session['progress']['distance']) . "</b>\n";
        $message .= "🎯 Progress: <b>" . $this->gameEngine->getProgressPercent($session) . "%</b>\n\n";
        $message .= "👥 Chad survived: <b>{$session['resources']['chad']}</b>/" . MAX_CREW . "\n\n";
        $message .= "<i>Better luck next time, Captain.</i>";

        return [
            'message' => $message,
            'image_url' => $this->gameEngine->getContextImageUrl('gameover', $session),
            'keyboard' => $this->gameEngine->getGameOverKeyboard(),
            'parse_mode' => 'HTML',
        ];
    }

    /**
     * VICTORY VIEW
     */
    private function renderVictoryView(array $session): array {
        $message = "🏆 <b>VICTORY! YOU REACHED URANUS!</b> 🪐\n\n";
        $message .= "After <b>{$session['progress']['day']} days</b> of travel, ";
        $message .= "your chad has successfully colonised Uranus!\n\n";
        $message .= "👥 Chad survived: <b>{$session['resources']['chad']}</b>/" . MAX_CREW . "\n";
        $message .= "😊 Final morale: <b>{$session['resources']['morale']}%</b>\n\n";
        $message .= "🎯 Journey Efficiency: <b>" . $this->calculateEfficiency($session) . "%</b>\n\n";
        $message .= "<i>You are now a citizen of Uranus. Congratulations!</i>";

        return [
            'message' => $message,
            'image_url' => $this->gameEngine->getContextImageUrl('victory', $session),
            'keyboard' => $this->gameEngine->getVictoryKeyboard(),
            'parse_mode' => 'HTML',
        ];
    }

    /**
     * NO SESSION VIEW
     */
    private function renderNoSessionView(): array {
        return [
            'message' => "🚀 You don't have an active journey.\n\nStart a new game with /game!",
            'image_url' => null,
            'keyboard' => [
                'inline_keyboard' => [
                    [['text' => '🚀 New Journey', 'callback_data' => 'menu|startnewgame']],
                ]
            ],
            'parse_mode' => 'HTML',
        ];
    }

    private function calculateEfficiency(array $session): float {
        $totalTraveled = $session['stats']['total_distance_traveled'] ?? $session['progress']['distance'];
        if ($totalTraveled == 0) return 100;
        return round(($session['progress']['distance'] / $totalTraveled) * 100, 1);
    }
}
```

---

## Session State Schema

### All views render from this structure:

```php
$session = [
    'user_id' => 123456,
    'session_id' => 'abc123',
    'status' => 'in_space',  // 'pre_launch', 'in_space', 'at_waypoint'
    'difficulty' => 'normal',
    'preferred_chat' => 'group',  // or 'private'

    'progress' => [
        'day' => 45,
        'distance' => 780000000,  // Position from Earth
        'speed' => 20000000,
    ],

    'resources' => [
        'chad' => 5,
        'fuel' => 156,
        'food' => 340,
        'hull' => 85,
        'morale' => 65,
        'credits' => 50,
    ],

    'stats' => [
        'total_distance_traveled' => 815000000,  // Odometer
        'events_encountered' => 12,
        'chad_lost' => 0,
        'fuel_consumed' => 500,
        'food_consumed' => 1200,
    ],

    'at_waypoint' => [  // null if not at waypoint
        'name' => 'Europa Outpost',
        'distance' => 780000000,
        'symbol' => '🛰️',
        'type' => 'station',
    ],

    'pending_event' => 'asteroid_field',  // null if no event

    'waypoints_reached' => ['Earth Orbit', 'Moon', 'Mars Orbit', ...],

    'events_completed' => ['asteroid_field', 'space_dysentery', ...],

    'game_over_reason' => null,  // or 'Out of fuel', 'Chad died', etc.
];
```

---

## Button Routing Table

```php
$buttonRoutes = [
    // Navigation (no action, just view switch)
    'journey' => [
        'action' => null,
        'view' => 'journey',
    ],
    'status' => [
        'action' => null,
        'view' => 'status',
    ],
    'map' => [
        'action' => null,
        'view' => 'map',
    ],
    'shop' => [
        'action' => null,
        'view' => 'shop',
    ],

    // Actions (mutate state, then show view)
    'continue' => [
        'action' => 'advance_day',
        'view' => 'journey',  // or 'event' if triggered, or 'game_over', or 'victory'
    ],
    'rest' => [
        'action' => 'rest',
        'view' => 'journey',  // or 'game_over' if ran out of resources
    ],
    'hunt' => [
        'action' => 'hunt',
        'view' => 'journey',  // or 'event' if hunt triggered event
    ],
    'return' => [
        'action' => 'return_to_waypoint',
        'view' => 'journey',  // or 'game_over' if ran out of resources
    ],

    // Shop actions
    'shop_fuel' => [
        'action' => 'shop_purchase',
        'params' => ['item' => 'fuel', 'quantity' => 100],
        'view' => 'shop',
    ],
    'shop_food' => [
        'action' => 'shop_purchase',
        'params' => ['item' => 'food', 'quantity' => 50],
        'view' => 'shop',
    ],
    'shop_launch' => [
        'action' => 'shop_launch',
        'view' => 'journey',
    ],

    // Event actions
    'event_{eventId}_{choiceId}' => [
        'action' => 'event_choice',
        'params' => ['event_id' => $eventId, 'choice_id' => $choiceId],
        'view' => 'journey',  // or 'game_over' if choice killed chad
    ],
];
```

---

## Migration Plan

### Phase 1: Create New Architecture (Parallel)
1. Create `GameController` class
2. Create `ActionManager` class
3. Create `ViewManager` class
4. **DO NOT** modify existing code yet

### Phase 2: Implement Views
1. Implement `ViewManager::renderJourneyView()` using new day summary format
2. Implement `ViewManager::renderStatusView()`
3. Implement `ViewManager::renderMapView()`
4. Implement `ViewManager::renderEventView()`
5. Add tests for each view (same session → same output)

### Phase 3: Implement Actions
1. Implement `ActionManager::execute()` for each action
2. Update GameEngine methods to store state in session (not just return)
3. Add tests for each action (state mutations)

### Phase 4: Wire Up Controller
1. Implement `GameController::handle()`
2. Implement `selectView()` logic
3. Add routing table

### Phase 5: Gradual Migration
1. Add feature flag: `USE_NEW_ARCHITECTURE = false`
2. In `uranus_game_bot.php`, add:
   ```php
   if (USE_NEW_ARCHITECTURE) {
       $controller->handle($button, $context);
   } else {
       // Old code
       $this->handleContinue(...);
   }
   ```
3. Test with flag enabled
4. Fix bugs
5. Enable for all users
6. Remove old code

### Phase 6: Cleanup
1. Remove old `handle*()` methods
2. Remove `showDayResult()`, `formatDayResultWithImage()`
3. Remove feature flag
4. Update documentation

---

## Benefits

### 1. Consistency
- Journey View ALWAYS looks the same
- No more "different content depending on button"

### 2. Testability
```php
testJourneyView() {
    $session = createMockSession(['day' => 45, 'distance' => 780000000]);
    $view = ViewManager::render('journey', $session);
    assertContains('Day 45', $view['message']);
}
```

### 3. Maintainability
- Clear separation: Actions, Views, Routing
- Easy to add new views/actions
- No tangled dependencies

### 4. Debuggability
```php
// Can test views without running actions
$view = ViewManager::render('journey', $debugSession);

// Can test actions without rendering views
$result = ActionManager::execute('advance_day', $debugSession);
```

### 5. Flexibility
- Easy to add new views (e.g., "Leaderboard View")
- Easy to add new actions (e.g., "Repair Ship")
- Easy to change routing logic

---

## Open Questions

### Q1: Action Messages
When an action executes, it may have a "flash message" (e.g., "You rested for 2 days").

**Options:**
- A: Store in session temporarily: `$session['flash_message']`
- B: Display as toast/alert before view
- C: Incorporate into view message at top

**Recommendation:** Option A (flash message in session, cleared after display)

### Q2: Event Outcomes
After processing an event choice, should we show:
- A: Journey View (standard)
- B: Journey View with event outcome at top
- C: Temporary "Event Outcome View" that advances to Journey

**Recommendation:** Option B (Journey View with outcome)

### Q3: Keyboard Context
Some keyboards need context (e.g., event choices need event ID).

**Options:**
- A: Pass session to keyboard builder (reads from session)
- B: Extract needed data from session, pass explicitly
- C: Keyboards are view-specific, handle internally

**Recommendation:** Option A (keyboards read from session)

---

## Summary

**Core Change:** Day summary format becomes the ONE TRUE Journey View.

**Architecture:** Three clear layers (Controller, Actions, Views) with session as single source of truth.

**Migration:** Gradual, with feature flag and parallel implementation.

**Benefits:** Consistency, testability, maintainability, clarity.

**Next Step:** Review this design, answer open questions, then implement Phase 1.

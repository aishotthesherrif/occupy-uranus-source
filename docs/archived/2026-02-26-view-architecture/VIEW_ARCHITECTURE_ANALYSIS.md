# View Architecture Analysis

**Issue Identified:** View content depends on routing path rather than being a pure function of game state.

---

## Current Architecture Problems

### Problem 1: Inconsistent Journey View

**Scenario A:** User presses "▶️ Continue" button
```
handleContinue()
  → gameEngine->advanceDay()
  → showDayResult()
     → formatDayResultWithImage()  ← Special day summary format
     → getJourneyKeyboard()
```

**Scenario B:** User presses "🏠 Back to Journey" button
```
handleJourney()
  → formatViewWithImage('journey')  ← Standard journey format
  → getJourneyKeyboard()
```

**Result:** Same "Journey View" but different content!
- After "Continue": Shows day summary, distance traveled, event summary
- After "Back": Shows standard journey view

### Problem 2: Mixed Responsibilities

**Example: `handleContinue()`** (lines 765-812)
```php
private function handleContinue(...) {
    $session = $this->sessionManager->loadSession($userId);  // Data fetching

    if ($session['status'] === 'shopping') {                 // Business logic
        return $this->sendMessage(...);                      // View rendering
    }

    $result = $this->gameEngine->advanceDay($session);       // Action execution

    return $this->showDayResult($chatId, $result, ...);      // View selection
}
```

This function does FIVE things:
1. Load data
2. Validate state
3. Execute action
4. Select which view to show
5. Render and send

### Problem 3: `showDayResult()` is a View Router

**Lines 1924-1967:**
```php
private function showDayResult($chatId, array $result, ...) {
    // Decision logic - which view to show?
    if (isset($result['event'])) {
        // Show Event View
        return $this->sendMessage(..., $rendered['message'], ...);
    }

    if (!empty($result['session']['pending_event'])) {
        // Show Event View (fallback)
        return $this->sendMessage(...);
    }

    // Show Journey View (with special day result content)
    $dayResultData = $this->gameEngine->formatDayResultWithImage(...);
    return $this->sendMessage(...);
}
```

This function **decides which view to show** based on the result of an action, not based on explicit view selection.

### Problem 4: Map View Inconsistency

**Map View** (lines 844-871) is completely different:
- Uses `generateMap()` instead of `formatViewWithImage('map')`
- Uses `sendMessagePlain()` instead of `sendMessage()`
- Manually handles message deletion and tracking
- Doesn't use standard keyboard helper

Why? Historical reasons, not architectural ones.

---

## What Should Be True (But Isn't)

### Principle 1: Views Should Be Pure Functions

```php
// SHOULD BE:
JourneyView::render(session) → always same output for same session state

// CURRENTLY:
showDayResult(session) → different output depending on calling context
handleJourney(session) → different output than showDayResult
```

### Principle 2: Actions and Views Should Be Separate

```php
// SHOULD BE:
Action: advanceDay(session) → new session + events
View: JourneyView::render(session) → message + keyboard

// CURRENTLY:
handleContinue() does BOTH in one function
```

### Principle 3: View Selection Should Be Explicit

```php
// SHOULD BE:
Router: "continue" → AdvanceDayAction → if event: EventView, else: JourneyView

// CURRENTLY:
showDayResult() implicitly decides based on action result
```

---

## Proposed Architecture

### 1. View Layer (Pure Rendering)

```php
class ViewRenderer {
    /**
     * Render a view from session state.
     * Always returns the same output for the same input.
     */
    public function render(string $viewName, array $session, array $context = []): array {
        switch ($viewName) {
            case 'journey':
                return $this->renderJourneyView($session);
            case 'status':
                return $this->renderStatusView($session);
            case 'map':
                return $this->renderMapView($session);
            case 'event':
                return $this->renderEventView($session, $context['event']);
            case 'shop':
                return $this->renderShopView($session);
            case 'game_over':
                return $this->renderGameOverView($session, $context);
            case 'victory':
                return $this->renderVictoryView($session);
            default:
                throw new InvalidArgumentException("Unknown view: $viewName");
        }
    }

    private function renderJourneyView(array $session): array {
        // ALWAYS the same for a given session state
        $data = $this->gameEngine->formatViewWithImage('journey', $session);
        $keyboard = $this->gameEngine->getJourneyKeyboard($session);

        return [
            'message' => $data['message'],
            'image_url' => $data['image_url'] ?? null,
            'keyboard' => $keyboard,
            'parse_mode' => 'HTML',
        ];
    }

    private function renderEventView(array $session, array $event): array {
        $message = $this->gameEngine->formatEnhancedEvent($event);
        $keyboard = $this->gameEngine->createEnhancedEventKeyboard($event);

        return [
            'message' => $message,
            'image_url' => null,
            'keyboard' => ['inline_keyboard' => $keyboard],
            'parse_mode' => 'HTML',
        ];
    }

    // ... other view renderers
}
```

### 2. Action Layer (State Mutations)

```php
class GameActions {
    /**
     * Execute an action, returning new session and metadata.
     */
    public function execute(string $action, array $session, array $params = []): array {
        switch ($action) {
            case 'advance_day':
                return $this->advanceDay($session);
            case 'process_event':
                return $this->processEvent($session, $params['event_id'], $params['choice_id']);
            case 'rest':
                return $this->rest($session);
            case 'shop_purchase':
                return $this->shopPurchase($session, $params['item'], $params['quantity']);
            default:
                throw new InvalidArgumentException("Unknown action: $action");
        }
    }

    private function advanceDay(array $session): array {
        $result = $this->gameEngine->advanceDay($session);

        return [
            'session' => $result['session'],
            'events' => ['day_advanced' => true],
            'triggered_event' => $result['event'] ?? null,
            'game_over' => $result['game_over'] ?? false,
            'victory' => $result['victory'] ?? false,
        ];
    }

    // ... other actions
}
```

### 3. Router Layer (Button → Action → View)

```php
class GameRouter {
    public function route(string $button, int $userId, array $context): void {
        // 1. Load session
        $session = $this->sessionManager->loadSession($userId);

        if ($session === null) {
            $this->showView('no_session', $context);
            return;
        }

        // 2. Execute action (if button triggers an action)
        $actionResult = $this->executeActionIfNeeded($button, $session);

        if ($actionResult) {
            $session = $actionResult['session'];
            $this->sessionManager->saveSession($userId, $session);
        }

        // 3. Determine view to show
        $view = $this->selectView($button, $session, $actionResult);

        // 4. Render and send
        $this->showView($view, $context, $session, $actionResult);
    }

    private function selectView(string $button, array $session, ?array $actionResult): string {
        // Priority 1: Action result triggers a specific view
        if ($actionResult) {
            if ($actionResult['game_over']) {
                return 'game_over';
            }
            if ($actionResult['victory']) {
                return 'victory';
            }
            if ($actionResult['triggered_event']) {
                return 'event';
            }
        }

        // Priority 2: Button explicitly requests a view
        $viewMap = [
            'continue' => 'journey',  // After action, show journey
            'journey' => 'journey',
            'status' => 'status',
            'map' => 'map',
            'shop' => 'shop',
        ];

        return $viewMap[$button] ?? 'journey';
    }
}
```

---

## Benefits of This Architecture

### 1. Consistency
```php
// BEFORE: Journey View looks different depending on how you got there
handleContinue() → showDayResult() → formatDayResultWithImage()
handleJourney() → formatViewWithImage('journey')

// AFTER: Journey View ALWAYS looks the same
ViewRenderer::render('journey', $session) → same output every time
```

### 2. Testability
```php
// BEFORE: Can't test view rendering without executing actions
testJourneyView() {
    // Have to call handleContinue() which does advanceDay()
    // Can't test JUST the view
}

// AFTER: Test views in isolation
testJourneyView() {
    $session = createMockSession();
    $view = ViewRenderer::render('journey', $session);
    assertEquals($expected, $view['message']);
}
```

### 3. Reusability
```php
// BEFORE: Want to show Journey View from new context? Copy-paste code.

// AFTER: Just call the view renderer
function showJourneyFromAnywhere($session) {
    $view = ViewRenderer::render('journey', $session);
    sendToUser($view);
}
```

### 4. Clear Separation of Concerns
```
Router: "Which button was pressed?"
  ↓
Action: "What changed in game state?"
  ↓
ViewSelector: "Which view should the user see?"
  ↓
ViewRenderer: "What does that view look like?"
  ↓
Messenger: "Send it to Telegram"
```

---

## Migration Path

### Phase 1: Extract View Renderers (No Breaking Changes)
1. Create `ViewRenderer` class
2. Implement `renderJourneyView()`, `renderStatusView()`, etc.
3. Update `handleJourney()`, `handleStatus()` to use them
4. `showDayResult()` still exists but calls ViewRenderer internally

### Phase 2: Extract Actions
1. Create `GameActions` class
2. Move `advanceDay()`, `rest()`, etc. into it
3. Handlers call `GameActions->execute()` then ViewRenderer

### Phase 3: Unified Router
1. Create `GameRouter` class
2. Consolidate all handle*() methods
3. Single entry point: `route($button, $userId, $context)`

### Phase 4: Clean Up
1. Remove old `showDayResult()`, `formatDayResultWithImage()`
2. Remove duplicated code
3. Simplify button handling

---

## Example: "Continue" Button Flow

### BEFORE (Current):
```
User clicks "Continue"
  → handleCallbackQuery()
     → handleActionCallback('continue')
        → handleContinue()
           → advanceDay()
           → showDayResult()
              → IF event: renderEventMessage() + sendMessage()
              → IF no event: formatDayResultWithImage() + sendMessage()
```

**Problems:**
- `showDayResult()` decides which view based on action result
- Journey View content is different than `handleJourney()`

### AFTER (Proposed):
```
User clicks "Continue"
  → Router::route('continue', userId)
     → Action: GameActions->execute('advance_day', session)
        → Returns: {session, triggered_event: event|null}
     → ViewSelector: if event → 'event', else → 'journey'
     → ViewRenderer::render('journey', session)  ← ALWAYS SAME
     → Messenger::send(view)
```

**Benefits:**
- Journey View is ALWAYS rendered the same way
- Clear separation: Action (advance day) + View (show journey)
- Can test view rendering independently

---

## Questions for Implementation

1. **Should day result be a separate view?**
   - Option A: "Journey View" always looks the same (remove day result)
   - Option B: Create explicit "DayResultView" distinct from "JourneyView"
   - Option C: Journey View has optional "flash message" parameter

2. **How to handle view transitions?**
   - Option A: Action result declares "next view" explicitly
   - Option B: Router logic decides based on action result
   - Option C: Hybrid (action suggests, router can override)

3. **What about contextual data?**
   - Example: Event View needs the event object
   - Option A: Pass in render() context parameter
   - Option B: Store in session temporarily
   - Option C: View pulls from session by ID

4. **Migration strategy?**
   - Option A: Big refactor (risky)
   - Option B: Gradual extraction (safer)
   - Option C: New code follows pattern, old code stays

---

## Recommendation

**Start with Phase 1: Extract View Renderers**

This is:
- Low risk (no breaking changes)
- High value (immediate consistency)
- Easy to test (just compare outputs)
- Foundation for future improvements

Specifically:
1. Create `ViewRenderer` class with `renderJourneyView()`, `renderStatusView()`, `renderMapView()`
2. Update `handleJourney()`, `handleStatus()`, `handleMap()` to use it
3. Make `showDayResult()` call `ViewRenderer::renderJourneyView()` (unify the output)
4. Commit and test

Then evaluate: Is this good enough? Or continue to Phase 2?

---

## Summary

**The Core Issue:** View content is coupled to HOW you navigated there, not WHAT state you're in.

**The Solution:** Views should be pure functions of game state.

**The Benefit:** Consistency, testability, maintainability.

**The Path:** Gradual extraction starting with view renderers.

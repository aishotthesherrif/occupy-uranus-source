# Architecture Analysis: Bot vs Mini-App Separation of Concerns

**Created:** 2026-03-07
**Purpose:** Identify architectural issues preventing unified abstraction layer
**Status:** Discussion Phase - No implementation yet

---

## Current Architecture Map

### **Bot Data Flow (uranus_game_bot.php → GameController)**

```
1. Telegram Webhook
   ↓ (callback_query or command)
2. uranus_game_bot.php (handler)
   ↓ (button name, userId, params)
3. GameController.route()
   ├─ Load session from SessionManager
   ├─ Execute action via ActionManager (if needed)
   ├─ Save session
   └─ Render view via ViewManager
   ↓ (returns view_data structure)
4. uranus_game_bot.php
   ↓ (sends formatted message)
5. TelegramAPI.sendMessage()
   └─ User sees Telegram message with inline keyboard
```

### **Mini-App Data Flow (CURRENT - PROBLEMATIC)**

```
1. Telegram Mini-App (JavaScript)
   ↓ (fetch POST with action + params)
2. api/mini-app/action.php
   ├─ Load session from SessionManager
   ├─ Execute action via ActionManager
   └─ Save session
   ↓ (returns JSON response)
3. Mini-App JavaScript
   ├─ Update client state
   ├─ DataBinder.bindSession()
   └─ Show flash message
   └─ User sees updated HTML UI
```

**PROBLEM:** Mini-app bypasses ViewManager and gets raw ActionManager output

---

## Data Structure Analysis

### **GameEngine Return Format**

**Purpose:** Pure game logic results

```php
// Standard success result
[
    'success' => true,
    'session' => $updatedSession,
    'message' => 'Minimal text, NO formatting',

    // Optional outcome flags:
    'game_over' => true,
    'game_over_reason' => 'No fuel',
    'victory' => true,
    'event' => [...],  // Event data if triggered
    'hull_repaired' => 20,
    'vibes_gained' => 15,
    'resource_changes' => [...],
]

// Failure result
[
    'success' => false,
    'message' => 'Error description',
    'session' => $session,  // Unchanged
]
```

**Key Properties:**
- Transport-agnostic (no HTML, no keyboards)
- Minimal messages (brief descriptions, not formatted)
- Outcome flags for routing decisions
- Optional metadata (hull_repaired, vibes_gained, etc.)

### **ActionManager Return Format**

**Purpose:** Orchestration + routing decisions (BOT-SPECIFIC)

```php
[
    'session' => $updatedSession,
    'next_view' => 'journey',        // ← BOT UI CONCERN
    'context' => [                   // ← BOT VIEW CONTEXT
        'flash' => $flashMessage,
        'event' => $event,
        'waypoint' => $waypoint,
    ],
    'flash' => $flashMessage,        // ← SHORT SUMMARY
]
```

**PROBLEM:** Contains bot UI routing logic
- `next_view` assumes Telegram message-based UI flow
- `context` is ViewManager input structure
- Not reusable for mini-app (client-side routing)

### **ViewManager Return Format**

**Purpose:** Telegram-formatted presentation (BOT-ONLY)

```php
[
    'message' => '<b>HTML</b> formatted text',  // ← TELEGRAM HTML
    'image_url' => 'https://...',
    'keyboard' => [                             // ← TELEGRAM INLINE KEYBOARD
        'inline_keyboard' => [...]
    ],
    'parse_mode' => 'HTML',
]
```

**PROBLEM:** Telegram-specific, not reusable for mini-app

### **Mini-App API Response Format (CURRENT)**

**File:** `api/mini-app/action.php`

```php
[
    'success' => true,
    'session' => $updatedSession,
    'flash_message' => $message,  // ← Plain text
    'event' => $event,            // ← If triggered
]
```

**PROBLEM:** Manually constructed from ActionManager output
- Doesn't use ViewManager
- No standardized presentation layer
- Inconsistent with bot architecture

### **Mini-App Expected Response (CLIENT SIDE)**

**File:** `mini-app/js/app.js`

Client expects:
```javascript
{
    success: true,
    session: { /* full session */ },
    flash_message: "Action result text",
    event: { /* event data if triggered */ }
}
```

Client then:
1. Updates `this.session`
2. Calls `DataBinder.bindSession(session)` to update UI
3. Shows flash message if present
4. Handles events if present (Phase 3+)

---

## Separation of Concerns Violations

### **Issue #1: ActionManager Returns UI-Routing Data**

**Current:**
```php
return [
    'session' => $session,
    'next_view' => 'journey',  // ← UI routing decision
    'context' => [...],        // ← View-specific data
    'flash' => $message,
];
```

**Why it's a problem:**
- `next_view` is a bot UI concern (message-based navigation)
- Mini-app has client-side routing (no "views" in same sense)
- Couples game logic layer to presentation layer

**Root cause:** ActionManager is trying to be both:
1. Game action orchestrator (good)
2. View router for bot UI (bad - should be higher layer)

### **Issue #2: ViewManager is Telegram-Specific**

**Current:**
- Builds HTML messages for Telegram
- Creates inline keyboards (Telegram API structure)
- Not reusable for mini-app JSON responses

**Why it's a problem:**
- Mini-app needs JSON, not Telegram HTML
- Mini-app client handles its own UI rendering
- No shared presentation abstraction

### **Issue #3: GameController is Bot-Only**

**Current:**
```php
GameController.route(button, userId, params)
    → returns ['view_data' => [...], 'session' => ...]
```

**Why it's a problem:**
- Assumes Telegram webhook context
- Returns Telegram-formatted view data
- Not reusable for mini-app API

### **Issue #4: Inconsistent Response Formats**

**Bot:** `view_data` structure (message, keyboard, image)
**Mini-app:** `success`, `session`, `flash_message`

No unified contract between implementations.

---

## Atomic Units of Game State

Based on mini-app data binding analysis (`DataBinder.bindSession()`):

### **Atomic Data Units (Directly Bound to UI)**

1. **Progress Data**
   - `session.progress.day`
   - `session.progress.distance`
   - `session.progress.speed`
   - `session.progress.speed_multiplier`

2. **Resource Data**
   - `session.resources.fuel`
   - `session.resources.food`
   - `session.resources.hull`
   - `session.resources.vibes`
   - `session.resources.chad`
   - `session.resources.eth`

3. **Location Data**
   - `session.status` (in_space, at_waypoint, at_station)
   - `session.at_waypoint` (waypoint object or null)
   - `session.waypoints_reached` (array of names)

4. **Crew Data**
   - `session.chad.members[]` (roster array)
   - Each member: name, status, role, personality_type, traits

### **Computed/Derived Data (Calculated Client-Side or Server-Side)**

1. **Progress Metrics**
   - Remaining distance = `TOTAL_DISTANCE - distance`
   - Progress percent = `(distance / TOTAL_DISTANCE) * 100`
   - Effective speed = `speed * speed_multiplier`

2. **Resource Metrics**
   - Fuel days = `fuel / FUEL_PER_DAY`
   - Food days = `food / (chad * FOOD_PER_CREW)`
   - Fuel percent = `(fuel / MAX_FUEL) * 100`
   - Food percent = `(food / MAX_FOOD) * 100`

3. **Warnings**
   - Low fuel warning = `fuel_days < 10`
   - Critical fuel alert = `fuel_days < 5`
   - Low food warning = `food_days < 10`
   - Critical food alert = `food_days < 5`
   - Low hull = `hull < 30`
   - Low morale = `vibes < 30`

### **Transient/Event Data (Not Persisted)**

1. **Flash Messages**
   - Action results (hunt, rest, continue)
   - Event outcomes
   - Resource changes
   - Warnings

2. **Event Data (When Active)**
   - `pending_event` (event ID or null)
   - Event object (title, description, choices)

---

## Presentation Layer Needs

### **Bot Presentation Requirements**

1. **Message Formatting**
   - HTML formatting (bold, italic, code blocks)
   - Emoji integration
   - Multi-line structure
   - Resource summaries
   - Progress bars (ASCII)

2. **Navigation**
   - Inline keyboards (buttons)
   - Callback data (button actions)
   - View transitions (edit message)

3. **Media**
   - Image URLs (contextual to game state)
   - Image caching (file_id)

### **Mini-App Presentation Requirements**

1. **Data Format**
   - JSON response
   - Session object (full state)
   - Flash messages (plain text)
   - Event data (if triggered)

2. **Navigation**
   - Client-side routing (JavaScript)
   - Button visibility (based on status)
   - No server-side view routing

3. **Media**
   - Background images (CSS)
   - No dynamic image URLs

### **Shared Requirements**

1. **Game State**
   - Session data (same structure)
   - Action execution
   - Event handling
   - Game over / victory detection

2. **Formatting Primitives**
   - ETH formatting (3 decimals)
   - Distance formatting (B/M/km)
   - Resource with days
   - Velocity display

---

## Best Practices Research Needed

### **Questions to Research**

1. **Telegram Bot Games Architecture**
   - How do successful Telegram games structure their code?
   - Common patterns for bot + mini-app hybrid games?
   - Recommended abstraction layers?

2. **Game State Management**
   - Client-side vs server-side state authority?
   - Session locking for concurrent access?
   - Polling vs WebSocket for mini-app updates?

3. **API Design**
   - RESTful vs RPC style for game actions?
   - Response format conventions?
   - Error handling patterns?

4. **Separation Patterns**
   - MVC vs MVP vs MVVM for games?
   - Domain-driven design for game logic?
   - Presentation layer abstractions?

### **Research Sources**

- Telegram Bot API documentation
- Telegram Mini App best practices
- Game architecture patterns (general)
- PHP MVC frameworks (for patterns)
- Existing open-source Telegram games

---

## Proposed Solution Architecture (DRAFT)

### **Layer Structure**

```
┌─────────────────────────────────────────────────────┐
│           PRESENTATION LAYER (Transport)             │
│  Bot: TelegramAPI, ViewManager                      │
│  Mini-App: JSON API, Client DataBinder             │
└──────────────┬──────────────────────────────────────┘
               │
               ↓
┌─────────────────────────────────────────────────────┐
│          FACADE LAYER (Unified Interface)           │
│  GameFacade: Single entry point                     │
│  - executeAction(action, userId, params)            │
│  - Returns: ActionResult (transport-agnostic)       │
└──────────────┬──────────────────────────────────────┘
               │
               ↓
┌─────────────────────────────────────────────────────┐
│       ORCHESTRATION LAYER (Action Flow)             │
│  ActionOrchestrator (refactored ActionManager)      │
│  - Pure game action orchestration                   │
│  - Returns: GameOutcome (no view routing)           │
└──────────────┬──────────────────────────────────────┘
               │
               ↓
┌─────────────────────────────────────────────────────┐
│          LOGIC LAYER (Game Rules)                   │
│  GameEngine: Pure game logic                        │
│  - Returns: GameResult (minimal data)               │
└─────────────────────────────────────────────────────┘
```

### **Key Changes**

1. **GameFacade (NEW)**
   - Unified entry point for bot and mini-app
   - Handles session lifecycle (load/save)
   - Returns transport-agnostic ActionResult
   - Translates GameOutcome → ActionResult

2. **ActionOrchestrator (REFACTORED FROM ActionManager)**
   - Remove `next_view`, `context`, `flash`
   - Return pure game outcome data
   - Outcome types: `CONTINUE_JOURNEY`, `SHOW_EVENT`, `GAME_OVER`, `VICTORY`, `SHOW_WAYPOINT`, `AT_STATION`
   - Include metadata for presentation layer

3. **ViewManager (STAYS BOT-SPECIFIC)**
   - Keeps Telegram HTML formatting
   - Bot translates ActionResult → ViewManager input

4. **Mini-App Response Formatter (NEW)**
   - Translates ActionResult → JSON for mini-app
   - Simple, client handles UI

---

## ActionResult Structure (PROPOSED)

### **Unified Result Object**

```php
[
    'success' => true,
    'session' => $updatedSession,
    'outcome' => 'CONTINUE_JOURNEY',  // Enum-like string
    'data' => [
        // Outcome-specific data
        'summary' => 'Brief action summary',
        'details' => 'Detailed results',
        'changes' => [
            'fuel' => ['before' => 100, 'after' => 95, 'delta' => -5],
            'food' => ['before' => 200, 'after' => 185, 'delta' => -15],
        ],
    ],
    'flags' => [
        'game_over' => false,
        'victory' => false,
        'event_triggered' => false,
        'waypoint_reached' => false,
    ],
    // Optional fields based on outcome:
    'event' => [...],      // If event triggered
    'waypoint' => [...],   // If at waypoint
    'reason' => '...',     // If game over
]
```

### **Outcome Types**

1. **CONTINUE_JOURNEY** - Normal day advancement
2. **SHOW_EVENT** - Event triggered, needs user choice
3. **GAME_OVER** - Game ended (loss)
4. **VICTORY** - Game ended (win)
5. **SHOW_WAYPOINT** - At waypoint threshold (stop/skip choice)
6. **AT_STATION** - Stopped at station (services available)
7. **ACTION_COMPLETE** - Generic action completed (hunt, rest)

### **Bot Translation**

```php
$result = $gameFacade->executeAction('continue', $userId, []);

if ($result['outcome'] === 'SHOW_EVENT') {
    $view = 'event';
    $context = ['event' => $result['event']];
} elseif ($result['outcome'] === 'GAME_OVER') {
    $view = 'game_over';
    $context = ['reason' => $result['reason']];
} else {
    $view = 'journey';
    $context = ['flash' => $result['data']['summary']];
}

$viewData = $viewManager->render($view, $session, $context);
```

### **Mini-App Translation**

```php
$result = $gameFacade->executeAction('continue', $userId, []);

return [
    'success' => $result['success'],
    'session' => $result['session'],
    'flash_message' => $result['data']['summary'] ?? null,
    'event' => $result['event'] ?? null,
    'outcome' => $result['outcome'],  // Client decides what to show
];
```

---

## Open Questions for Discussion

1. **Outcome Granularity**
   - Are 7 outcome types enough?
   - Should we have sub-types (e.g., `GAME_OVER_NO_FUEL` vs `GAME_OVER_NO_CREW`)?

2. **Response Data Structure**
   - Is `data` field too generic?
   - Should we have stricter schemas per outcome type?

3. **Session Locking**
   - How do we prevent race conditions if user uses bot and mini-app simultaneously?
   - Last-write-wins? Optimistic locking? Warning?

4. **Polling Frequency**
   - Is 10s polling interval acceptable?
   - Should mini-app support push updates (WebSocket)?

5. **Error Handling**
   - How should mini-app handle session expiration?
   - Retry logic? Redirect to bot? Error screen?

6. **Flash Message Format**
   - Bot uses HTML formatting
   - Mini-app uses plain text
   - Should `summary` be plain text and bot adds formatting?

7. **Implementation Phases**
   - Refactor all at once or incremental?
   - Start with mini-app API or bot refactor?

---

## Next Steps

1. **Research Phase**
   - Look up Telegram bot game architecture best practices
   - Review successful open-source examples
   - Document findings

2. **Design Phase**
   - Finalize ActionResult structure
   - Define clear outcome types
   - Design GameFacade interface

3. **Implementation Phase**
   - Create GameFacade
   - Refactor ActionManager → ActionOrchestrator
   - Update bot to use new facade
   - Update mini-app API to use new facade

4. **Testing Phase**
   - Test bot functionality (should be unchanged)
   - Test mini-app functionality
   - Test concurrent access scenarios

---

**Status:** Discussion in progress
**Next Action:** Research Telegram bot game best practices

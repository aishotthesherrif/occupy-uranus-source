# Frontend Patterns Analysis: What the Mini-App Tells Us

**Created:** 2026-03-07
**Purpose:** Analyze mini-app frontend to inform abstraction layer design
**Status:** Discussion Phase

---

## Key Finding: Mini-App is Self-Contained

The mini-app frontend reveals a **completely self-sufficient presentation layer** that only needs raw session data.

---

## Mini-App Data Flow Pattern

### **What Mini-App Receives from API**

```javascript
// Current response from api/mini-app/action.php
{
    success: true,
    session: { /* complete session object */ },
    flash_message: "Plain text message",
    event: { /* event data if triggered */ }
}
```

### **What Mini-App Does with It**

```javascript
// app.js lines 176-204
const response = await this.api.executeAction(action, params);

if (response.success) {
    // 1. Store session
    this.session = response.session;

    // 2. Update ALL UI from session
    this.dataBinder.bindSession(this.session);

    // 3. Show flash message (if present)
    if (response.flash_message) {
        this.showFlashMessage(response.flash_message);
    }
}
```

**Pattern:** Mini-app is **declarative** - it binds session state to UI, doesn't need pre-formatted views.

---

## DataBinder: The Key Insight

### **DataBinder Structure**

```javascript
class DataBinder {
    bindSession(session) {
        this.bindHeader(session);       // Day counter
        this.bindProgress(session);     // Distance, velocity, location
        this.bindResources(session);    // 5 gauges (fuel, food, hull, morale, chad)
        this.bindCrew(session);         // Crew roster cards
        this.updateButtons(session);    // Button visibility
    }
}
```

### **What This Reveals**

1. **Mini-app only needs raw session data**
   - No pre-formatted messages
   - No view routing decisions
   - No presentation logic from server

2. **All formatting happens client-side**
   - `Formatters.formatDistance()` - JavaScript version
   - `Formatters.formatEth()` - JavaScript version
   - `Formatters.formatVelocity()` - JavaScript version
   - Colors, warnings, animations - all client-side

3. **UI updates are atomic**
   - Each piece of session data maps to a DOM element
   - Updates are independent (can update fuel without touching food)
   - No "views" in the Telegram sense

---

## Comparison: Bot vs Mini-App Presentation

### **Bot (Telegram) Pattern**

```
User Action
    ↓
ActionManager returns: {next_view, context, flash}
    ↓
ViewManager.render(next_view, session, context)
    ↓
Returns: {message: "<b>HTML</b>", keyboard: [...], image_url: "..."}
    ↓
TelegramAPI sends formatted message
```

**Characteristics:**
- Server renders full HTML message
- Server decides which view to show
- Server builds inline keyboard
- Message is immutable once sent (can only edit)

### **Mini-App Pattern**

```
User Action
    ↓
API returns: {session, flash_message}
    ↓
DataBinder.bindSession(session)
    ↓
DOM updates (fuel gauge, day counter, etc.)
    ↓
showFlashMessage(flash_message)
```

**Characteristics:**
- Client renders all UI from session data
- Client decides what to display (based on session.status)
- No server-side view logic
- UI is mutable (smooth updates, animations)

---

## What Mini-App Needs from Backend

### **Required Data**

1. **Complete session object**
   ```javascript
   {
       progress: { day, distance, speed, speed_multiplier },
       resources: { fuel, food, hull, vibes, chad, eth },
       status: "in_space" | "at_waypoint" | "at_station",
       at_waypoint: { name, type, icon, distance } | null,
       chad: { members: [...] },
       waypoints_reached: [...]
   }
   ```

2. **Flash message (optional)**
   - Plain text, no HTML formatting
   - Action results: "You hunted 50 food"
   - Event outcomes: "Hull damaged by asteroid"
   - Warnings: "Low fuel warning"

3. **Event data (when triggered)**
   ```javascript
   {
       id: "asteroid_field",
       title: "Asteroid Field",
       description: "...",
       choices: [...]
   }
   ```

### **NOT Needed**

- ❌ HTML formatting (client does this)
- ❌ Inline keyboards (client has buttons)
- ❌ Image URLs (client uses CSS backgrounds)
- ❌ View routing decisions (client has routing)
- ❌ Pre-computed display strings (client computes)

---

## Mini-App Formatter Functions

### **Client-Side Formatting (formatters.js)**

```javascript
// Distance formatting
formatDistance(2_800_000_000) → "2.80B km"
formatDistance(150_000_000) → "150.0M km"

// ETH formatting (stored as float, displayed as decimal)
formatEth(15.5) → "15.5"

// Resource with days
formatResourceWithDays(815, 163) → "815 units (163d)"

// Velocity with multiplier
formatVelocity(20_000_000, 2.0) → "40.0M km/d ⚡2.0×"

// Resource status
getResourceStatusColor(3) → "var(--red)"    // Critical
getResourceStatusColor(8) → "var(--orange)" // Warning
getResourceStatusColor(15) → "var(--green)" // OK

// Warning emoji
getWarningEmoji(3) → "🚨"
getWarningEmoji(8) → "⚠️"
getWarningEmoji(15) → ""
```

**Pattern:** JavaScript formatters **mirror** PHP FormatHelpers exactly.

---

## Button Visibility Logic (Client-Side)

```javascript
// data-binder.js lines 274-298
updateButtons(session) {
    // Continue button - always visible
    continueBtn.style.display = 'flex';

    // Hunt button - only in space
    huntBtn.style.display = session.status === 'in_space' ? 'flex' : 'none';

    // Rest button - only at station
    restBtn.style.display = session.status === 'at_station' ? 'flex' : 'none';

    // Shop button - only at station
    shopBtn.style.display = session.status === 'at_station' ? 'flex' : 'none';
}
```

**Pattern:** Client controls button visibility based on `session.status`.

Server doesn't need to tell client which buttons to show.

---

## What This Means for Abstraction Layer

### **Core Insight: Two Different Presentation Models**

1. **Bot = Server-Side Rendering (SSR)**
   - Server builds complete view
   - Server decides navigation flow
   - Client (Telegram) just displays

2. **Mini-App = Client-Side Rendering (CSR)**
   - Server sends raw data
   - Client builds UI from data
   - Client decides navigation flow

### **Implication: ActionManager's `next_view` is Bot-Specific**

```php
// ActionManager returns (current - BOT-CENTRIC):
[
    'session' => $session,
    'next_view' => 'journey',  // ← Bot needs this
    'context' => [...],        // ← Bot needs this
    'flash' => $message,       // ← Both need this (different formats)
]
```

**Problem:** `next_view` and `context` are bot UI concerns.

Mini-app doesn't have "views" - it has client-side state:
- `status: 'in_space'` → show journey UI
- `status: 'at_station'` → show station UI
- `pending_event` → show event modal

### **Solution: Separate "What Happened" from "What to Show"**

Backend should return **what happened** (game outcome):
```php
[
    'session' => $session,
    'outcome' => 'DAY_ADVANCED',    // What happened
    'summary' => 'Day completed',    // Brief text
    'details' => [...],              // Outcome-specific data
]
```

Each presentation layer decides **what to show**:
- **Bot:** Translates outcome → view name → ViewManager
- **Mini-App:** Ignores outcome, just binds session to UI

---

## The Real Contract Between Backend and Frontend

### **What Backend MUST Provide**

1. **Authoritative session state**
   - Complete, consistent session object
   - All fields required for UI binding

2. **Action outcome type**
   - Enum: `DAY_ADVANCED`, `EVENT_TRIGGERED`, `GAME_OVER`, etc.
   - Allows client to make decisions

3. **Human-readable summary**
   - Plain text description
   - Bot adds HTML formatting, mini-app shows as-is

4. **Outcome-specific metadata**
   - Event data (if event triggered)
   - Game over reason (if game over)
   - Resource changes (for detailed display)

### **What Backend Should NOT Provide**

1. ❌ **View routing decisions** (`next_view`)
   - Bot needs it, mini-app doesn't
   - Keep in bot-specific layer

2. ❌ **Pre-formatted messages** (HTML)
   - Bot-specific presentation
   - Mini-app formats client-side

3. ❌ **UI component instructions** (`context`)
   - Bot-specific ViewManager input
   - Mini-app has DataBinder

---

## Proposed Response Format (Unified)

### **Backend Returns (Transport-Agnostic)**

```php
[
    'success' => true,
    'session' => $completeSession,
    'outcome' => 'DAY_ADVANCED',     // Enum
    'summary' => 'Day 42 complete',  // Plain text
    'details' => [
        'fuel_consumed' => 5,
        'food_consumed' => 15,
        'distance_traveled' => 20_000_000,
    ],
    'event' => null,                 // Event data if triggered
    'flags' => [
        'game_over' => false,
        'victory' => false,
        'waypoint_reached' => false,
    ],
]
```

### **Bot Consumes It**

```php
// Bot layer translates outcome to view
$result = $gameFacade->executeAction('continue', $userId, []);

$view = match($result['outcome']) {
    'DAY_ADVANCED' => 'journey',
    'EVENT_TRIGGERED' => 'event',
    'GAME_OVER' => 'game_over',
    'AT_WAYPOINT' => 'waypoint',
    default => 'journey',
};

$context = [
    'flash' => $result['summary'],
    'event' => $result['event'],
];

$viewData = $viewManager->render($view, $session, $context);
```

### **Mini-App Consumes It**

```javascript
// Mini-app just binds session and shows flash
const result = await api.executeAction('continue', {});

this.session = result.session;
this.dataBinder.bindSession(this.session);

if (result.summary) {
    this.showFlashMessage(result.summary);
}

// Handle special outcomes
if (result.outcome === 'EVENT_TRIGGERED') {
    this.showEventModal(result.event);
}
```

---

## Key Architectural Decisions Revealed

### **1. Session is Source of Truth for UI State**

Mini-app proves that **session.status** is enough to control UI:
- `in_space` → show journey buttons
- `at_station` → show station buttons
- `at_waypoint` → show waypoint choice

**No need for server to dictate view routing.**

### **2. Formatting is Presentation Layer Concern**

Mini-app has **JavaScript formatters** that mirror **PHP FormatHelpers**.

**Implication:** FormatHelpers should be:
- Used by bot ViewManager (server-side formatting)
- NOT used by mini-app (has its own JS formatters)
- NOT in ActionManager (that's game logic layer)

### **3. Flash Messages Should Be Plain Text**

Bot adds HTML formatting:
```php
$message = "<b>Day 42</b>\n⛽ Fuel: -5 units";
```

Mini-app shows plain text:
```javascript
"Day 42\nFuel: -5 units"
```

**Solution:** Backend returns plain text, bot adds HTML tags.

### **4. Client-Side vs Server-Side Routing**

**Bot:** Server decides view (message-based UI)
**Mini-App:** Client decides what to show (stateful UI)

**Both patterns are valid.** Backend shouldn't force one pattern.

---

## Recommended Abstraction Layer

### **GameFacade API**

```php
interface GameFacadeInterface {
    public function executeAction(
        string $action,
        int $userId,
        array $params = []
    ): ActionResult;
}

class ActionResult {
    public bool $success;
    public array $session;        // Complete session
    public string $outcome;       // Enum: DAY_ADVANCED, EVENT_TRIGGERED, etc.
    public string $summary;       // Plain text summary
    public array $details;        // Outcome-specific data
    public ?array $event;         // Event if triggered
    public array $flags;          // game_over, victory, etc.
}
```

### **Bot Adapter**

```php
class TelegramBotAdapter {
    public function handleAction($action, $userId, $params) {
        $result = $this->gameFacade->executeAction($action, $userId, $params);

        // Translate outcome to view
        $view = $this->outcomeToView($result->outcome);
        $context = $this->buildViewContext($result);

        // Render view
        return $this->viewManager->render($view, $result->session, $context);
    }

    private function outcomeToView(string $outcome): string {
        return match($outcome) {
            'DAY_ADVANCED' => 'journey',
            'EVENT_TRIGGERED' => 'event',
            'GAME_OVER' => 'game_over',
            'VICTORY' => 'victory',
            'AT_WAYPOINT' => 'waypoint',
            'AT_STATION' => 'station',
            default => 'journey',
        };
    }
}
```

### **Mini-App Adapter**

```php
class MiniAppAdapter {
    public function handleAction($action, $userId, $params) {
        $result = $this->gameFacade->executeAction($action, $userId, $params);

        // Return JSON for mini-app
        return [
            'success' => $result->success,
            'session' => $result->session,
            'flash_message' => $result->summary,
            'outcome' => $result->outcome,
            'event' => $result->event,
        ];
    }
}
```

---

## Summary: What Frontend Analysis Teaches Us

1. **Mini-app is self-sufficient**
   - Only needs raw session data
   - All formatting client-side
   - No view routing from server

2. **Bot and mini-app have different UI models**
   - Bot: Server-side rendering (views)
   - Mini-app: Client-side rendering (data binding)

3. **ActionManager's current structure is bot-specific**
   - `next_view` and `context` are bot concerns
   - Mini-app doesn't need or use them

4. **Proper abstraction: outcome + session**
   - Backend returns what happened (outcome enum)
   - Backend returns complete state (session)
   - Each client decides what to show

5. **Formatters belong in presentation layer**
   - Bot: PHP FormatHelpers → ViewManager
   - Mini-app: JS Formatters → DataBinder
   - Not in game logic layer

---

**Next Steps:**
1. Define outcome enum types
2. Design GameFacade interface
3. Refactor ActionManager to return outcomes (not views)
4. Create bot and mini-app adapters

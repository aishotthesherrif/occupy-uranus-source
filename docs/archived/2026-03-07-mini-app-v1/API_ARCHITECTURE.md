# Mini-App API Architecture

**Last Updated:** 2026-03-07
**Status:** Design Phase - Architecture Analysis Complete
**Purpose:** Document API design decisions and architecture for mini-app integration

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Current Bot Architecture](#current-bot-architecture)
3. [ViewManager Analysis](#viewmanager-analysis)
4. [Component Mapping](#component-mapping)
5. [GameEngine Data Structures](#gameengine-data-structures)
6. [API Design Decision](#api-design-decision)
7. [Proposed API Layer](#proposed-api-layer)
8. [Implementation Plan](#implementation-plan)

---

## Executive Summary

### Key Findings

**Good News:**
- GameEngine already returns structured data (not HTML)
- ViewManager is cleanly separated from logic
- Components are standardized via FormatHelpers
- API layer can be thin wrapper around existing code

**Architecture Decision:**
- **Hybrid approach** - Add thin API adapter that exposes GameEngine data
- Bot continues using ViewManager for Telegram-specific rendering
- Mini-app receives structured JSON and renders in JavaScript
- Both clients share game logic, differ only in presentation

**No major refactoring required** - existing architecture is API-ready.

---

## Current Bot Architecture

### Three-Layer Design

```
┌─────────────────────────────────────────┐
│         GameEngine (Logic)              │
│  • advanceDay()                         │
│  • processEventChoice()                 │
│  • rest()                               │
│  • purchaseItem()                       │
│  Returns: structured outcome arrays     │
└─────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────┐
│      ActionManager (Orchestration)      │
│  • Calls GameEngine                     │
│  • Determines next view                 │
│  • Builds context                       │
│  Returns: {session, next_view, context} │
└─────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────┐
│       ViewManager (Presentation)        │
│  • render(viewName, session, context)   │
│  • Formats using FormatHelpers          │
│  Returns: {message, keyboard, image}    │
└─────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────┐
│         Bot (Transport)                 │
│  • sendMessage() to Telegram API        │
└─────────────────────────────────────────┘
```

### Layer Responsibilities

**GameEngine** - Pure game logic
- Resource consumption calculations
- Event selection and effect application
- Win/loss condition checking
- Returns structured data (arrays with flags and values)

**ActionManager** - Flow orchestration
- Calls GameEngine methods
- Interprets outcomes (game_over, victory, event, etc.)
- Determines which view to show next
- Builds view-specific context

**ViewManager** - Telegram presentation
- Formats session data into HTML messages
- Builds inline keyboards
- Selects appropriate images
- Returns Telegram-ready output

**Bot** - Telegram transport
- Handles webhook/polling
- Routes commands to GameController
- Sends messages via Telegram API
- Manages chat state

### Current Flow Example: Continue Button

```php
// 1. Bot receives callback
$callback = 'continue';

// 2. GameController handles button
$result = $gameController->handleButton($userId, $callback);

// 3. GameController calls ActionManager
$actionResult = $actionManager->execute('advance_day', $session);
// Returns: ['session' => ..., 'next_view' => 'journey', 'flash' => '...']

// 4. GameController calls ViewManager
$viewData = $viewManager->render('journey', $session, $context);
// Returns: ['message' => HTML, 'keyboard' => [...], 'image_url' => '...']

// 5. Bot sends message
$this->sendMessage($chatId, $viewData['message'], $viewData['image_url'], $viewData['keyboard']);
```

---

## ViewManager Analysis

### Location & Size
- **File:** `classes/ViewManager.php`
- **Size:** ~1800 lines
- **Dependencies:** GameEngine (for some formatters), FormatHelpers

### Architecture Pattern

```php
public function render(string $viewName, ?array &$session, array $context = []): array
```

**Returns (consistent across all views):**
```php
[
    'message' => string,      // Formatted HTML
    'image_url' => ?string,   // Optional image URL
    'keyboard' => array,      // Telegram inline keyboard
    'parse_mode' => 'HTML'
]
```

### Structure (4 sections)

#### 1. View Renderers (14 views)
Private methods that render specific views:
- `renderJourneyView()` - Main game screen
- `renderStatusView()` - Detailed status
- `renderEventView()` - Random events
- `renderShopView()` - Station shop
- `renderWaypointView()` - Waypoint arrival
- Plus 9 more...

Each takes `$session` + optional `$context`, returns standardized array.

#### 2. Keyboard Builders (~15 methods)
Pure functions that build Telegram inline keyboards:
- `getJourneyKeyboard()` - Main action buttons
- `getShopKeyboard()` - Purchase buttons
- `createEnhancedEventKeyboard()` - Event choice buttons
- Context-aware (e.g., disables purchase if insufficient ETH)

#### 3. Formatters (~20 methods)
Convert game state to formatted text:
- `formatResourcesSummary()` - Resource display
- `formatResourceChanges()` - Before/after deltas
- `formatShop()` - Shop listing
- **Many delegate to FormatHelpers** for consistency

#### 4. Image Handling (5 methods)
- `getContextImageUrl()` - Main entry point
- `findStaticImage()`, `selectRandomZoneImage()` - Selection logic
- Uses aesthetic preferences, distance zones, event types

### Key Design Decisions

#### Pure View Functions
Views are **deterministic** - same input always produces same output.

```php
// Comment from ViewManager.php:100-104
"This is ALWAYS the same for a given session state, regardless of
how the user navigated here (Continue button, Back button, etc.)."
```

#### Context Parameter
Used for transient display data that isn't persisted in session:
- Flash messages (event outcomes, action results)
- Event data for event view
- Chat IDs for view-specific logic
- Resource change deltas

#### Session Mutation Exception
**Only ONE view mutates session:** `renderStationView()`
- Tracks which station content user has seen
- Documented exception to pure-view principle
- Required for progressive story disclosure

#### Incomplete Migration
Some formatters still live in GameEngine (noted in comments as "will move in future refactor"):
- `formatJourney()` - Journey view text
- `formatDetailedStatus()` - Status view text
- `generateMap()` - ASCII map
- `formatWaypointView()` - Waypoint text
- `formatStationView()` - Station text

### Strengths for API Adaptation

**Why ViewManager works well for dual-client architecture:**

1. **Transport-Agnostic Output**
   - Returns structured data, not side effects
   - Easy to serialize different formats

2. **Clear Separation**
   - Logic (GameEngine) → pure calculations
   - Presentation (ViewManager) → formatting only
   - No mixing of concerns

3. **Context Pattern**
   - Handles transient display data cleanly
   - Doesn't pollute session with UI-only state

4. **Standardized Components**
   - FormatHelpers provides consistent formatting
   - Reusable across both bot and API

### Challenges for API Adaptation

1. **HTML Formatting**
   - All text uses Telegram HTML (`<b>`, `<i>`, etc.)
   - Mini-app needs different format (or parses HTML)

2. **Keyboard Structure**
   - Keyboards are Telegram-specific callback_data
   - Mini-app needs button handlers, not callbacks

3. **Image URLs**
   - Returns URLs assuming bot will send via Telegram
   - Mini-app can probably use as-is

4. **Session Mutation**
   - `renderStationView()` has side effect
   - API clients expect pure functions

5. **No Intermediate Format**
   - Goes straight to display format
   - Would benefit from structured data → formatter pattern

---

## Component Mapping

### Bot Components → Mini-App Components

Based on analysis in `docs/VIEW_COMPONENTS.md` and `docs/mini-app/MINI_APP_COMPONENTS.md`:

| Bot Component | Bot Format | Mini-App Component | Data Needed |
|---------------|------------|-------------------|-------------|
| Resource Summary | HTML text | `.resource-card` grid | current, max, percent, daysRemaining |
| Progress Bar | 16-block █░ | `.progress-bar` + `.ship-icon` | progress %, distance, waypoints |
| Journey Status | HTML text | `.distance-info` + `.location-label` | distance, remaining, location |
| Chad Roster | HTML list | `.chad-card` grid | name, role, emoji, alive/deceased |
| Flash Message | HTML text | `.flash-message` | icon, message text |
| Velocity Display | HTML text | Header `.day-counter` | speed, multiplier |
| Waypoint Info | HTML text | `.waypoint` markers | passed, current, future states |

### Example: Resource Summary

**Bot (ViewManager):**
```php
FormatHelpers::formatResources($session, 'full')
// Returns HTML string:
// "📦 Resources:\n⛽ Fuel: 450 | 🍖 Food: 1313 | 🛡️ Hull: 100%\n..."
```

**Mini-App (Needs):**
```javascript
{
  fuel: { current: 450, max: 1200, percent: 38, daysRemaining: 90 },
  food: { current: 1313, max: 2000, percent: 66, daysRemaining: 88 },
  hull: { percent: 100 },
  chad: { current: 5, max: 5 },
  vibes: { percent: 100 },
  eth: { value: 450.5 }
}
```

**Insight:** API should return structured data, not formatted strings.

### Standardized Formatters (FormatHelpers)

Located in `classes/FormatHelpers.php`, these provide consistent formatting across views:

- `formatEth($amount)` - 3 decimal places
- `formatDistance($km)` - Auto-scales to B/M/km
- `formatVelocityDisplay($speed, $multiplier)` - Boost indicator
- `formatResourceWithDays($current, $speed)` - Days remaining calculation
- `formatProgressBar($percent)` - 16-block visual
- `formatResources($session, $format)` - 3 formats: full, compact, minimal

**For API:** These can compute values that both clients use differently:
- Bot: Formats to HTML for display
- Mini-app: Uses calculated values for rendering

---

## GameEngine Data Structures

### Method Return Patterns

All GameEngine methods follow a consistent pattern:

```php
[
    'success' => bool,           // Action succeeded?
    'session' => array,          // Modified session state
    'message' => string,         // Optional formatted message
    // ... outcome-specific fields
]
```

### advanceDay()

**Normal Success:**
```php
[
    'success' => true,
    'session' => array,
    'message' => string,
]
```

**Event Triggered:**
```php
[
    'success' => true,
    'session' => array,          // With pending_event set
    'message' => string,
    'event' => [
        'id' => string,
        'title' => string,
        'description' => string,
        'type' => string,        // 'discovery', 'crisis', etc.
        'choices' => [
            [
                'id' => string,
                'text' => string,
                'outcomes' => [...]
            ]
        ]
    ]
]
```

**Game Over:**
```php
[
    'game_over' => true,
    'reason' => string,          // 'All chad lost', 'Out of fuel', etc.
    'session' => array,
    'message' => string,
]
```

**Victory:**
```php
[
    'victory' => true,
    'session' => array,
    'message' => string,
]
```

**Waypoint Reached:**
```php
[
    'success' => true,
    'session' => array,
    'message' => string,
    'waypoint' => [              // When crossing waypoint threshold
        'name' => string,
        'distance' => int,
        'services' => array,
    ]
]
```

### processEventChoice()

**Success:**
```php
[
    'success' => true,
    'message' => string,         // Formatted outcome
    'session' => array,          // With pending_event cleared
    'resource_changes' => [      // Delta only (changed resources)
        'fuel' => [
            'before' => 500,
            'after' => 450,
            'delta' => -50
        ],
        'hull' => [
            'before' => 100,
            'after' => 80,
            'delta' => -20
        ]
    ]
]
```

### rest()

**Success:**
```php
[
    'success' => true,
    'message' => string,
    'session' => array,
    'hull_repaired' => int,      // Amount restored
    'vibes_gained' => int,       // Amount restored
]
```

**Game Over (Starvation):**
```php
[
    'success' => false,
    'game_over' => true,
    'reason' => string,
    'message' => string,
    'session' => array,
]
```

### purchaseItem()

**Success:**
```php
[
    'success' => true,
    'item' => string,            // 'fuel', 'food', 'repair'
    'quantity_requested' => int,
    'quantity_added' => int,     // Actual (can be less if capped)
    'cost' => float,             // ETH spent
    'capped' => bool,            // Hit resource maximum?
    'session' => array,
]
```

**Insufficient ETH:**
```php
[
    'success' => false,
    'reason' => 'insufficient_eth',
    'item' => string,
    'cost' => float,
    'eth_available' => float,
    'session' => array,
]
```

### Key Insights

1. **Already Structured** - GameEngine doesn't return HTML, it returns typed data
2. **Consistent Pattern** - All methods use similar structure with success flag
3. **Outcome-Specific** - Additional fields based on what happened
4. **Session Updated** - Always includes modified session in result
5. **Optional Messages** - Formatted strings included but not required

**This means:** GameEngine is already 80% API-ready. Just needs outcome classification.

---

## API Design Decision

### Options Considered

#### Option A: Expose GameEngine Directly
Minimal changes. Wrap GameEngine methods as REST endpoints.

**Pros:**
- Fastest to implement
- No refactoring required
- Direct exposure of existing logic

**Cons:**
- Clients must interpret raw GameEngine responses
- No outcome classification
- Session management not included

#### Option B: Create GameFacade with Unified Interface
Normalize all action results to consistent structure.

**Pros:**
- Clean, consistent API contract
- All responses follow same pattern
- Easier for clients to consume

**Cons:**
- Requires new abstraction layer
- Duplicates some logic
- More code to maintain

#### Option C: Hybrid (SELECTED)
Keep GameEngine as-is, add thin API adapter.

**Pros:**
- Minimal changes to existing code
- Adds outcome classification for clients
- Handles session persistence
- Both bot and mini-app use same data
- Bot keeps ViewManager
- Fastest path to working API

**Cons:**
- API responses include legacy 'message' fields
- Not as "pure" as Option B

### Why Hybrid?

**Reasons for choosing Option C:**

1. **GameEngine already good** - Returns structured data, not UI-specific
2. **Minimal risk** - No refactoring of tested game logic
3. **Quick to implement** - Just add classification + session handling
4. **Backward compatible** - Bot continues working unchanged
5. **Future-proof** - Can evolve to Option B later if needed

---

## Proposed API Layer

### Architecture

```
┌─────────────────────────────────────────┐
│            GameAPI                      │
│  • advanceDay(userId)                   │
│  • processEventChoice(userId, ...)      │
│  • rest(userId)                         │
│  • purchaseItem(userId, item)           │
│  Adds: outcome classification           │
│  Handles: session load/save             │
└─────────────────────────────────────────┘
                    │
                    ▼
┌─────────────────────────────────────────┐
│         GameEngine (unchanged)          │
│  Returns: structured data               │
└─────────────────────────────────────────┘
```

### GameAPI Class (Pseudocode)

```php
class GameAPI {
    private GameEngine $engine;
    private SessionManager $sessions;

    public function advanceDay(int $userId): array {
        // Load session
        $session = $this->sessions->loadSession($userId);
        if (!$session) {
            return ['error' => 'NO_SESSION'];
        }

        // Call GameEngine
        $result = $this->engine->advanceDay($session);

        // Add outcome classification
        $result['outcome'] = $this->getOutcomeType($result);

        // Save session
        $this->sessions->saveSession($userId, $result['session']);

        return $result;
    }

    private function getOutcomeType(array $result): string {
        if (!empty($result['game_over'])) return 'GAME_OVER';
        if (!empty($result['victory'])) return 'VICTORY';
        if (!empty($result['event'])) return 'EVENT_TRIGGERED';
        if (!empty($result['waypoint'])) return 'WAYPOINT_REACHED';
        return 'DAY_ADVANCED';
    }

    // Similar for processEventChoice, rest, purchaseItem...
}
```

### Outcome Types

**All possible outcome classifications:**

| Outcome | Triggered By | Next Action |
|---------|-------------|-------------|
| `DAY_ADVANCED` | advanceDay normal | Show journey view |
| `EVENT_TRIGGERED` | advanceDay with event | Show event choices |
| `WAYPOINT_REACHED` | advanceDay at station | Show waypoint decision |
| `GAME_OVER` | Any action, death | Show game over screen |
| `VICTORY` | advanceDay at Uranus | Show victory screen |
| `EVENT_RESOLVED` | processEventChoice | Show post-event view |
| `RESTED` | rest | Show station view |
| `PURCHASED` | purchaseItem | Show shop view |
| `LAUNCHED` | purchaseItem('launch') | Show journey view |

### Example: advanceDay Endpoint

**Request:**
```http
POST /api/game/advanceDay
Content-Type: application/json

{
  "userId": 12345
}
```

**Response (Normal Day):**
```json
{
  "outcome": "DAY_ADVANCED",
  "success": true,
  "session": {
    "progress": { "day": 43, "distance": 860000000 },
    "resources": { "fuel": 815, "food": 1200, ... }
  },
  "message": "🚀 Cruising through space..."
}
```

**Response (Event Triggered):**
```json
{
  "outcome": "EVENT_TRIGGERED",
  "success": true,
  "session": {
    "progress": { "day": 43, "distance": 860000000 },
    "pending_event": "evt_asteroid"
  },
  "event": {
    "id": "evt_asteroid",
    "title": "Asteroid Field",
    "description": "Dense field of rocks ahead!",
    "type": "crisis",
    "choices": [
      { "id": "navigate", "text": "Navigate through it" },
      { "id": "go_around", "text": "Take the long route" }
    ]
  },
  "message": "🚀 Cruising through space..."
}
```

**Response (Game Over):**
```json
{
  "outcome": "GAME_OVER",
  "game_over": true,
  "reason": "All chad lost",
  "session": {
    "resources": { "chad": 0, ... },
    "status": "game_over"
  },
  "message": "💀 Your last chad member has perished..."
}
```

### Client Consumption

**Bot (unchanged):**
```php
$result = $api->advanceDay($userId);

if ($result['outcome'] === 'EVENT_TRIGGERED') {
    $view = $this->viewManager->render('event', $result['session'], [
        'event' => $result['event']
    ]);
} else {
    $view = $this->viewManager->render('journey', $result['session']);
}

$this->sendMessage($chatId, $view['message'], $view['image_url'], $view['keyboard']);
```

**Mini-App (JavaScript):**
```javascript
const result = await api.advanceDay();

switch (result.outcome) {
    case 'DAY_ADVANCED':
        updateResourceDisplay(result.session.resources);
        updateProgressBar(result.session.progress);
        showFlashMessage('Day advanced');
        break;

    case 'EVENT_TRIGGERED':
        showEventModal(result.event);
        break;

    case 'GAME_OVER':
        showGameOverScreen(result.reason);
        break;
}
```

---

## Implementation Plan

### Phase 1: Core API Wrapper

**Goal:** Create GameAPI class with outcome classification

**Tasks:**
1. Create `classes/GameAPI.php`
2. Implement `advanceDay()` with outcome classification
3. Implement `processEventChoice()`
4. Implement `rest()`
5. Implement `purchaseItem()`
6. Add session load/save handling
7. Add error handling

**Deliverable:** PHP class that bot and API endpoints can use

**Estimated Effort:** 2-3 hours

### Phase 2: REST Endpoints

**Goal:** Expose GameAPI via HTTP endpoints

**Tasks:**
1. Update `api/mini-app/action.php` to use GameAPI
2. Add outcome classification to responses
3. Test with existing mini-app
4. Update API documentation
5. Add error responses for edge cases

**Deliverable:** Working REST API using GameAPI

**Estimated Effort:** 1-2 hours

### Phase 3: Bot Integration (Optional)

**Goal:** Migrate bot to use GameAPI instead of calling GameEngine directly

**Tasks:**
1. Update ActionManager to call GameAPI
2. Remove outcome classification logic from ActionManager (now in GameAPI)
3. Test all bot flows
4. Verify no regressions

**Deliverable:** Bot using same API as mini-app

**Estimated Effort:** 2-3 hours

**Note:** This is optional - bot can keep using GameEngine directly if preferred.

### Phase 4: Mini-App Updates

**Goal:** Update mini-app to consume new outcome types

**Tasks:**
1. Update `api.js` to handle outcome field
2. Add switch/case for outcome handling
3. Update UI transitions based on outcome
4. Test all game flows

**Deliverable:** Mini-app using outcome-based flow control

**Estimated Effort:** 2-3 hours

---

## Related Documentation

### Architecture & Design
- `docs/ARCHITECTURE.md` - Bot architecture overview
- `docs/VIEW_COMPONENTS.md` - Component analysis and standardization
- `docs/FRONTEND_PATTERNS_ANALYSIS.md` - Frontend patterns exploration

### Mini-App Implementation
- `docs/mini-app/MINI_APP_V1_IMPLEMENTATION.md` - Implementation plan
- `docs/mini-app/MINI_APP_COMPONENTS.md` - UI component catalog
- `docs/mini-app/MINI_APP_DATA_REFERENCE.md` - Data structure reference

### API Reference
- `api/mini-app/README.md` - Current API endpoint documentation

### Code Reference
- `classes/GameEngine.php` - Core game logic
- `classes/ViewManager.php` - Presentation layer
- `classes/ActionManager.php` - Flow orchestration
- `classes/FormatHelpers.php` - Standardized formatting

---

## Appendix: Design Rationale

### Why Not Refactor GameEngine?

**Q:** Why not make GameEngine return a more standardized format?

**A:**
- GameEngine already returns structured data (arrays with typed fields)
- Current format is well-tested and used by bot
- Refactoring introduces risk without clear benefit
- Adding classification layer is safer and faster

### Why Keep ViewManager Separate?

**Q:** Why not have ViewManager consume API responses?

**A:**
- ViewManager is presentation-only (as it should be)
- API should be presentation-agnostic
- Bot and mini-app have different rendering needs
- Keeping them separate maintains clean architecture

### Why Not Use ActionManager Logic in API?

**Q:** ActionManager already determines next view - why not use it?

**A:**
- ActionManager is bot-specific (knows about bot views)
- API shouldn't know about Telegram views
- Outcome classification is simpler and more general
- Keeps API independent of bot implementation

### Why Include 'message' Field in API?

**Q:** If mini-app doesn't use formatted messages, why include them?

**A:**
- Backward compatibility with existing code
- Useful for debugging
- Bot can use them if desired
- Minimal overhead (just a string field)
- Can be deprecated later if not needed

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-03-07 | Initial architecture analysis and design |

---

**Next Steps:**
1. Review and approve this design
2. Implement Phase 1 (GameAPI class)
3. Update mini-app endpoint to use GameAPI
4. Test end-to-end with mini-app client

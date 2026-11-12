# API Architecture Validation & Mini-App Changes

**Last Updated:** 2026-03-07
**Purpose:** Validate hybrid API approach and identify needed mini-app improvements

---

## Executive Summary

### Validation Status: APPROVED with Modifications

The hybrid API approach is sound, but **current mini-app implementation is incomplete**. It only handles the happy path and doesn't respond to different game outcomes.

### Critical Gap Identified

**ActionManager already determines outcomes** (`next_view`) but **action.php doesn't expose it**. The mini-app receives:
- Session data
- Flash messages
- Event data (sometimes)

But NOT:
- Outcome classification (game over, victory, event, waypoint)
- Context data (reason for game over, waypoint choices)
- Next view hints

**Result:** Mini-app can't properly respond to game state changes.

---

## Current Implementation Analysis

### API Flow (Current)

```
Client → action.php → ActionManager.execute() → GameEngine
                            ↓
                      Returns:
                      - session
                      - next_view ← IGNORED!
                      - context   ← IGNORED!
                      - flash
                            ↓
                      action.php extracts:
                      - session
                      - flash_message
                      - event (if present)
                            ↓
                      Client receives partial data
```

### action.php (lines 67-92)

```php
// Execute action
$result = $actionManager->execute($action, $session, $params);

// Build response
$response = [
    'success' => true,
    'session' => $session,
];

// Add flash message if present
if (isset($result['message'])) {
    $response['flash_message'] = $result['message'];
}

// Add event data if present
if (isset($result['event'])) {
    $response['event'] = $result['event'];
}

// ❌ MISSING: next_view is never sent!
// ❌ MISSING: context is never sent!
// ❌ MISSING: outcome classification!
```

### Mini-App Client (app.js lines 171-205)

```javascript
async executeAction(action, params) {
    const response = await this.api.executeAction(action, params);

    if (response.success) {
        // Update session
        this.session = response.session;

        // Update UI
        this.dataBinder.bindSession(this.session);

        // Show flash message
        if (response.flash_message) {
            this.showFlashMessage(response.flash_message);
        }

        // ❌ NO handling for game over
        // ❌ NO handling for victory
        // ❌ NO handling for events
        // ❌ NO handling for waypoint decisions
    }
}
```

**Current behavior:** All actions just update session and show a message. No branching logic.

---

## What's Missing: Visual Evidence

### Scenario 1: Event Triggered

**What should happen:**
1. User clicks Continue
2. Random event triggers
3. Modal appears with event description and choices
4. User selects choice
5. Event resolves, outcome shown

**What actually happens:**
1. User clicks Continue
2. Session updates (pending_event is set)
3. Flash message shows "Day complete!"
4. **Event modal never appears** (not implemented)
5. User can click Continue again (would fail - event pending)

### Scenario 2: Game Over

**What should happen:**
1. User runs out of fuel
2. Game over screen appears
3. Shows death reason and final stats
4. Offers "New Game" option

**What actually happens:**
1. User runs out of fuel
2. Session updates (status = 'game_over')
3. Flash message shows "Day complete!"
4. **No game over screen** (not implemented)
5. UI still shows journey view
6. All buttons remain clickable (actions would fail)

### Scenario 3: Victory

**What should happen:**
1. User reaches Uranus
2. Victory screen appears
3. Fireworks, confetti, celebration
4. Shows final stats and leaderboard position

**What actually happens:**
1. User reaches Uranus
2. Session updates
3. Flash message shows "Day complete!"
4. **No victory screen** (not implemented)

### Scenario 4: Waypoint Decision

**What should happen:**
1. User reaches Jupiter Station
2. Decision modal appears: "Stop" or "Skip"
3. User chooses
4. If stop: Show station services
5. If skip: Speed boost applied, continue

**What actually happens:**
1. User reaches Jupiter Station
2. Session updates (at_waypoint set, pending_station_arrival set)
3. Flash message shows "Day complete!"
4. **No decision modal** (not implemented)
5. User stuck - can't progress

---

## Root Cause Analysis

### Problem 1: API Contract Incomplete

**ActionManager returns rich data:**
```php
[
    'session' => [...],
    'next_view' => 'game_over',     // Not exposed!
    'context' => [                   // Not exposed!
        'reason' => 'Out of fuel'
    ],
    'flash' => 'Message'
]
```

**But action.php only sends:**
```json
{
    "success": true,
    "session": {...},
    "flash_message": "Message"
}
```

**The client can't tell what happened.**

### Problem 2: Client Assumes Happy Path

```javascript
if (response.success) {
    // Always assumes normal day progression
    this.dataBinder.bindSession(this.session);
    this.showFlashMessage(response.flash_message);
}
```

**No branching logic for:**
- Events (show event modal)
- Game over (show game over screen)
- Victory (show victory screen)
- Waypoints (show decision modal)

### Problem 3: Missing UI Components

The mini-app HTML has:
- Journey view (main screen) ✅
- Resource cards ✅
- Chad roster ✅
- Flash messages ✅

But NOT:
- Event modal ❌
- Game over screen ❌
- Victory screen ❌
- Waypoint decision modal ❌
- Shop interface ❌
- Detailed status view ❌

---

## Proposed Solution

### Phase 1: Fix API Contract

**Update action.php to expose outcome:**

```php
// Execute action
$result = $actionManager->execute($action, $session, $params);

// Save updated session
$session = $result['session'];
$sessionManager->saveSession($userId, $session);

// Classify outcome
$outcome = classifyOutcome($result);

// Build response
$response = [
    'success' => true,
    'outcome' => $outcome,           // NEW!
    'session' => $session,
    'context' => $result['context'] ?? [],  // NEW!
    'flash_message' => $result['flash'] ?? null,
];

sendJsonResponse($response, 200);
```

**Add outcome classification function:**

```php
function classifyOutcome(array $result): string {
    $view = $result['next_view'] ?? 'journey';

    // Map view names to outcome types
    return match($view) {
        'game_over' => 'GAME_OVER',
        'victory' => 'VICTORY',
        'event' => 'EVENT_TRIGGERED',
        'waypoint' => 'WAYPOINT_REACHED',
        'station' => 'AT_STATION',
        'journey' => 'DAY_ADVANCED',
        'post_event' => 'EVENT_RESOLVED',
        default => 'DAY_ADVANCED',
    };
}
```

### Phase 2: Update Client Flow Control

**Update app.js executeAction:**

```javascript
async executeAction(action, params) {
    const response = await this.api.executeAction(action, params);

    if (response.success) {
        // Update session
        this.session = response.session;
        this.dataBinder.bindSession(this.session);

        // Handle outcome
        switch (response.outcome) {
            case 'DAY_ADVANCED':
                this.showFlashMessage(response.flash_message);
                break;

            case 'EVENT_TRIGGERED':
                this.showEventModal(response.context.event);
                break;

            case 'WAYPOINT_REACHED':
                this.showWaypointModal(response.context.waypoint);
                break;

            case 'GAME_OVER':
                this.showGameOverScreen(response.context.reason);
                break;

            case 'VICTORY':
                this.showVictoryScreen();
                break;

            case 'AT_STATION':
                this.showStationView(response.context.station);
                break;

            case 'EVENT_RESOLVED':
                this.showEventOutcome(response.flash_message);
                break;
        }

        this.telegram.hapticFeedback('success');
    }
}
```

### Phase 3: Build Missing UI Components

**3.1 Event Modal**

```html
<div id="eventModal" class="modal" style="display: none;">
    <div class="modal-content">
        <h2 id="eventTitle"></h2>
        <p id="eventDescription"></p>
        <div id="eventChoices"></div>
    </div>
</div>
```

**JavaScript handler:**

```javascript
showEventModal(event) {
    const modal = document.getElementById('eventModal');
    document.getElementById('eventTitle').textContent = event.title;
    document.getElementById('eventDescription').textContent = event.description;

    const choicesContainer = document.getElementById('eventChoices');
    choicesContainer.innerHTML = '';

    event.choices.forEach(choice => {
        const btn = document.createElement('button');
        btn.className = 'btn-3d';
        btn.textContent = choice.text;
        btn.onclick = () => this.handleEventChoice(event.id, choice.id);
        choicesContainer.appendChild(btn);
    });

    modal.style.display = 'flex';
}

async handleEventChoice(eventId, choiceId) {
    await this.executeAction('process_event', {
        event_id: eventId,
        choice_id: choiceId
    });

    document.getElementById('eventModal').style.display = 'none';
}
```

**3.2 Game Over Screen**

```html
<div id="gameOverScreen" class="full-screen" style="display: none;">
    <div class="game-over-content">
        <h1>MISSION FAILED</h1>
        <p id="gameOverReason"></p>
        <div id="gameOverStats"></div>
        <button class="btn-3d-main" onclick="window.location.reload()">
            NEW GAME
        </button>
    </div>
</div>
```

**JavaScript handler:**

```javascript
showGameOverScreen(reason) {
    const screen = document.getElementById('gameOverScreen');
    document.getElementById('gameOverReason').textContent = reason;

    // Format final stats
    const stats = this.session.stats;
    const statsHtml = `
        <p>Day ${this.session.progress.day}</p>
        <p>Distance: ${this.formatDistance(this.session.progress.distance)}</p>
        <p>Chad Lost: ${stats.chad_lost || 0}</p>
    `;
    document.getElementById('gameOverStats').innerHTML = statsHtml;

    screen.style.display = 'flex';

    // Stop polling (game over)
    this.stopPolling();
}
```

**3.3 Victory Screen**

```html
<div id="victoryScreen" class="full-screen" style="display: none;">
    <div class="victory-content">
        <h1>URANUS OCCUPIED!</h1>
        <p>You have successfully completed the mission!</p>
        <div id="victoryStats"></div>
        <button class="btn-3d-main" onclick="window.location.reload()">
            NEW GAME
        </button>
    </div>
</div>
```

**3.4 Waypoint Decision Modal**

```html
<div id="waypointModal" class="modal" style="display: none;">
    <div class="modal-content">
        <h2 id="waypointName"></h2>
        <p id="waypointDescription"></p>
        <div class="waypoint-choices">
            <button id="btnStopAtWaypoint" class="btn-3d">
                STOP
            </button>
            <button id="btnSkipWaypoint" class="btn-3d">
                SKIP (Speed Boost)
            </button>
        </div>
    </div>
</div>
```

**JavaScript handler:**

```javascript
showWaypointModal(waypoint) {
    const modal = document.getElementById('waypointModal');
    document.getElementById('waypointName').textContent = waypoint.name;
    document.getElementById('waypointDescription').textContent =
        `You've arrived at ${waypoint.name}. Stop to refuel and repair, or skip for a speed boost?`;

    document.getElementById('btnStopAtWaypoint').onclick = () => {
        this.handleWaypointDecision('stop', waypoint.id);
    };

    document.getElementById('btnSkipWaypoint').onclick = () => {
        this.handleWaypointDecision('skip', waypoint.id);
    };

    modal.style.display = 'flex';
}

async handleWaypointDecision(decision, waypointId) {
    await this.executeAction(decision === 'stop' ? 'stop_at_waypoint' : 'skip_waypoint', {
        waypoint_id: waypointId
    });

    document.getElementById('waypointModal').style.display = 'none';
}
```

---

## Architecture Improvements

### Improvement 1: Use ActionManager's next_view

**Instead of creating new classification logic**, reuse what ActionManager already provides:

```php
// action.php
$result = $actionManager->execute($action, $session, $params);

$response = [
    'success' => true,
    'outcome' => mapViewToOutcome($result['next_view']),
    'session' => $result['session'],
    'context' => $result['context'] ?? [],
    'flash_message' => $result['flash'] ?? null,
];

function mapViewToOutcome(string $view): string {
    return match($view) {
        'game_over' => 'GAME_OVER',
        'victory' => 'VICTORY',
        'event' => 'EVENT_TRIGGERED',
        'waypoint' => 'WAYPOINT_REACHED',
        'station' => 'AT_STATION',
        'post_event' => 'EVENT_RESOLVED',
        default => 'DAY_ADVANCED',
    };
}
```

**Why this is elegant:**
- Reuses existing bot logic
- No duplication
- ActionManager already does the hard work
- Just need to translate view names to outcome enum

### Improvement 2: Standardize Context Structure

**ActionManager already provides context per view:**

```php
// Game Over
'context' => [
    'reason' => 'Out of fuel'
]

// Event
'context' => [
    'event' => [
        'id' => 'evt_asteroid',
        'title' => 'Asteroid Field',
        'choices' => [...]
    ]
]

// Waypoint
'context' => [
    'waypoint' => [
        'id' => 'jupiter',
        'name' => 'Jupiter Gas Mine',
        'services' => [...]
    ]
]
```

**This is already perfect for the mini-app.** Just expose it as-is.

### Improvement 3: Session-Based UI State

**Problem:** Polling might update UI while modal is open.

**Solution:** Track UI state separately:

```javascript
class OccupyUranusApp {
    constructor() {
        // ...
        this.uiState = 'journey'; // 'journey', 'event', 'game_over', 'victory', 'waypoint'
    }

    startPolling() {
        this.pollInterval = setInterval(async () => {
            // Skip if UI is in modal state
            if (this.uiState !== 'journey') return;

            // Skip if processing an action
            if (this.isProcessingAction) return;

            // Poll...
        }, POLL_INTERVAL);
    }

    showEventModal(event) {
        this.uiState = 'event';
        // Show modal...
    }

    hideEventModal() {
        this.uiState = 'journey';
        // Hide modal...
    }
}
```

### Improvement 4: Idempotent Action Buttons

**Problem:** User might double-click Continue button.

**Current solution:** `isProcessingAction` flag (already implemented).

**Enhancement:** Disable based on session state:

```javascript
updateActionButtons() {
    const continueBtn = document.getElementById('btn-continue');
    const restBtn = document.getElementById('btn-rest');

    // Disable Continue if event pending
    if (this.session.pending_event) {
        continueBtn.disabled = true;
        continueBtn.title = 'Resolve event first';
    }

    // Disable Rest if not at station
    if (this.session.status !== 'at_station') {
        restBtn.disabled = true;
        restBtn.style.display = 'none';
    }
}
```

Call after every session update.

---

## Implementation Checklist

### Phase 1: API Contract (2 hours)

- [ ] Add `classifyOutcome()` helper to action.php
- [ ] Expose `outcome` field in response
- [ ] Expose `context` field in response
- [ ] Test all outcome types
- [ ] Update API documentation

### Phase 2: Client Flow Control (1 hour)

- [ ] Add outcome switch/case to `executeAction()`
- [ ] Add placeholder methods for each outcome
- [ ] Test branching logic
- [ ] Add UI state tracking

### Phase 3: Event UI (3 hours)

- [ ] Create event modal HTML
- [ ] Create event modal CSS
- [ ] Implement `showEventModal()`
- [ ] Implement `handleEventChoice()`
- [ ] Add event animations
- [ ] Test event flow end-to-end

### Phase 4: Game Over UI (2 hours)

- [ ] Create game over screen HTML
- [ ] Create game over screen CSS
- [ ] Implement `showGameOverScreen()`
- [ ] Format final stats display
- [ ] Add "New Game" button handler
- [ ] Test game over scenarios

### Phase 5: Victory UI (2 hours)

- [ ] Create victory screen HTML
- [ ] Create victory screen CSS (celebration theme)
- [ ] Implement `showVictoryScreen()`
- [ ] Add confetti/fireworks effect
- [ ] Format leaderboard position
- [ ] Test victory scenario

### Phase 6: Waypoint UI (3 hours)

- [ ] Create waypoint modal HTML
- [ ] Create waypoint modal CSS
- [ ] Implement `showWaypointModal()`
- [ ] Implement stop/skip handlers
- [ ] Add new action types to ActionManager (if needed)
- [ ] Test waypoint flow

### Phase 7: Polish (2 hours)

- [ ] Add transitions between states
- [ ] Improve button state management
- [ ] Add loading states for modals
- [ ] Test polling with modal interactions
- [ ] Test all flows end-to-end

**Total Estimated Effort:** 15 hours

---

## Validation Verdict

### ✅ Hybrid API Approach: APPROVED

**Reasons:**
1. GameEngine already returns structured data
2. ActionManager already determines outcomes
3. Minimal new code required
4. Bot and mini-app can share logic

### ⚠️ Current Mini-App: NEEDS WORK

**Missing:**
1. Outcome handling
2. Event UI
3. Game over UI
4. Victory UI
5. Waypoint UI

**But:** Architecture is sound, just needs implementation.

### 🎯 Recommended Path Forward

**Option A: Quick Fix (2 hours)**
- Just expose outcome in API
- Update mini-app to handle outcomes with alerts
- No new UI, just functional

**Option B: Proper Implementation (15 hours)**
- Full UI for all outcomes
- Polished experience
- Production-ready

**Option C: Incremental (5 hours first pass)**
- Phase 1-2: Fix API + flow control (3 hours)
- Phase 3: Event UI only (3 hours)
- Leave game over/victory/waypoint for later

**Recommendation:** **Option C** - Get events working first, they're most common.

---

## Breaking Changes Required

### API Response (Breaking Change)

**Before:**
```json
{
    "success": true,
    "session": {...},
    "flash_message": "...",
    "event": {...}
}
```

**After:**
```json
{
    "success": true,
    "outcome": "EVENT_TRIGGERED",
    "session": {...},
    "context": {
        "event": {...}
    },
    "flash_message": "..."
}
```

**Migration:** Mini-app currently doesn't handle events anyway, so no backward compatibility needed.

### New Action Types Needed

**Current:** `continue`, `hunt`, `rest`

**Need to add:**
- `process_event` (with params: event_id, choice_id)
- `stop_at_waypoint` (with params: waypoint_id)
- `skip_waypoint` (with params: waypoint_id)
- `purchase_item` (with params: item)
- `launch` (when leaving station)

**These already exist in ActionManager**, just need to wire up in action.php.

---

## Questions for Discussion

1. **Incremental vs Full Implementation?**
   - Implement all UIs now, or incrementally?
   - Events first seems logical (most common)

2. **Modal vs Full Screen?**
   - Events: Modal (can see game state behind)
   - Game Over/Victory: Full screen (game ended)
   - Waypoints: Modal (decision point)
   - Agreed?

3. **Polling During Modals?**
   - Should polling pause when modal is open?
   - Recommendation: Yes (avoid state conflicts)

4. **New Game Button?**
   - Reload page, or call new API endpoint?
   - Recommendation: Just reload (bot handles /start)

5. **GameAPI Still Needed?**
   - ActionManager already does outcome classification
   - Just need to expose it properly
   - Do we still create GameAPI wrapper?
   - Recommendation: **Optional** - can be added later for bot migration

---

## Conclusion

**The hybrid API design is validated and sound.** The issue isn't the architecture—it's that the current mini-app is a minimal POC that only handles the happy path.

**Good news:** ActionManager already has the logic we need. We just need to:
1. Expose it in the API response
2. Build the client UI to consume it

**No new abstractions needed**—just complete the implementation.

---

## Related Documentation

- `API_ARCHITECTURE.md` - Full architecture analysis
- `MINI_APP_V1_IMPLEMENTATION.md` - Original implementation plan
- `MINI_APP_COMPONENTS.md` - UI component catalog
- `../VIEW_COMPONENTS.md` - Bot component mapping

---

**Next Steps:**
1. Decide on implementation approach (A/B/C)
2. Update action.php to expose outcome + context
3. Update app.js with outcome handling
4. Build event modal UI (first priority)
5. Test event flow end-to-end

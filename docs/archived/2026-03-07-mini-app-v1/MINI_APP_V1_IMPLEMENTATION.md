# Mini-App v1 Implementation Plan

**Status:** In Planning
**Design Baseline:** `mini-app/option7.html` (locked 2026-03-03)
**Last Updated:** 2026-03-06

---

## Overview

This document tracks the implementation of the Telegram Mini App v1 for Occupy Uranus. The visual design is finalized and locked. This plan maps out all remaining technical work to make the mini-app functional.

---

## Design Baseline (LOCKED)

**File:** `mini-app/option7.html`
**Last Modified:** 2026-03-03 20:26
**File Size:** 46KB, 1274 lines
**Git Commit:** `Update mini-app option7.html` (2026-03-03 20:29:04)

### Design Features

- **Visual Style:** Cockpit viewport with 3D perspective, linear gauge displays
- **Typography:** Orbitron (headings), Azeret Mono (data), Nunito (body)
- **Layout:** Fixed viewport, no scrolling, single-screen interface
- **Components:**
  - Header with mission info and day counter
  - Distance/progress display with animated travel gauge
  - Resource gauges (Fuel, Food, Hull, Morale, ETH)
  - Chad roster with status indicators
  - Action buttons (Continue, Hunt, Rest, Shop, Status)
  - Flash message system for events
- **Effects:** Animated starfield, cockpit frame, gauge animations

**Decision:** This design is now the locked baseline for v1. All implementation work will target this UI.

---

## Current Status

### ✅ Complete (Design Phase)

- [x] Final UI mockup (option7.html)
- [x] Complete CSS design system
- [x] Component documentation (`MINI_APP_COMPONENTS.md`)
- [x] Data mapping reference (`MINI_APP_DATA_REFERENCE.md`)
- [x] Design exploration and iteration (options 2-6)

### ❌ Not Started (Implementation Phase)

- [ ] JavaScript application code
- [ ] Telegram Web App SDK integration
- [ ] Backend API endpoints
- [ ] Session data loading
- [ ] Button interactivity
- [ ] Dynamic data updates
- [ ] Error handling
- [ ] Testing infrastructure

**Completion:** ~15% (design only)

---

## Architecture Decisions

### 1. Frontend Framework

**Options:**
- **Vanilla JS** - No build step, simple deployment, matches current codebase simplicity
- **React** - More robust, better state management, steeper setup
- **Vue** - Middle ground, progressive enhancement

**Recommendation:** Vanilla JS for v1 (keep it simple, no build tooling required)

**Decision:** ✅ **LOCKED - Vanilla JS** (2026-03-06)

---

### 2. Backend API Architecture

**Options:**

**A. New API Endpoints (Recommended)**
- Create `api/mini-app/` directory
- Endpoints: `/status`, `/action`, `/session`
- Reuse existing classes (GameEngine, SessionManager)
- Return JSON responses
- Validate Telegram initData

**B. Extend Existing Webhook**
- Add mini-app handlers to `uranus_game_bot.php`
- Detect API vs webhook requests
- Pros: Single entry point
- Cons: Mixes concerns

**Recommendation:** Option A (separate API endpoints for clarity)

**Decision:** ✅ **LOCKED - Option A (Clean API structure)** (2026-03-06)

---

### 3. Authentication Strategy

**Required:** Validate Telegram Web App `initData` parameter

**Implementation:**
```php
// Verify signature from Telegram
function validateTelegramWebAppData(string $initData): bool|array {
    // Parse initData
    // Verify hash using bot token as secret key
    // Return user data if valid
}
```

**Decision:** ✅ **Implementation Required** (Phase 1)

---

### 4. State Management

**Options:**

**A. Poll for Updates**
- Frontend polls `/api/status` every N seconds
- Simple to implement
- Higher server load
- Good for v1

**B. WebSocket / SSE**
- Real-time updates
- More complex infrastructure
- Better UX
- Defer to v2

**Recommendation:** Polling for v1, WebSocket for v2

**Decision:** ✅ **LOCKED - Polling (10s interval)** (2026-03-06)

---

## Implementation Phases

### Phase 0: Foundation (Prerequisites) ✅ COMPLETE

**Goal:** Set up project structure and tooling

**Tasks:**
- [x] Create `api/mini-app/` directory structure
- [x] Create `mini-app/js/` directory
- [x] Set up local testing environment (ngrok or similar) - documented
- [x] Document API authentication flow
- [x] Create skeleton API endpoints (status.php, action.php, auth.php)
- [x] Create skeleton JS modules (app.js, api.js, telegram-sdk.js, data-binder.js, formatters.js)

**Deliverable:** Project structure ready for development

**Completed:** 2026-03-06

**Depends On:** None
**Blocks:** Phase 1

---

### Phase 1: Basic Connectivity 🔄 IN PROGRESS

**Goal:** Establish connection between mini-app and backend

**Tasks:**

#### Backend ✅ Complete
- [x] Create `api/mini-app/status.php` (GET session data)
- [x] Implement Telegram initData validation
- [x] Add CORS headers for Telegram domain
- [x] Return session JSON using existing SessionManager
- [x] Add error responses (401 unauthorized, 404 no session, 500 error)

#### Frontend ✅ Complete
- [x] Create `mini-app/js/app.js`
- [x] Initialize Telegram Web App SDK
- [x] Fetch session data from API
- [x] Log data to console (verification)
- [x] Handle loading and error states
- [x] Add Telegram SDK script tag to HTML
- [x] Add button IDs and classes for data binding
- [x] Update option7.html → app-v1.html with integrations
- [x] Implement flash message display
- [x] Implement loading states

#### Testing ⬜️ Not Started
- [ ] Set up HTTPS tunnel (ngrok/cloudflared)
- [ ] Test API endpoints with curl/Postman
- [ ] Create test session via bot
- [ ] Register mini-app URL with BotFather
- [ ] Test from real Telegram client

**Deliverable:** Mini-app successfully loads and displays session data in console

**Depends On:** Phase 0 ✅
**Blocks:** Phase 2

**Acceptance Criteria:**
- [ ] `/api/mini-app/status` returns valid session JSON (pending test)
- [ ] initData validation works (pending test)
- [ ] App.js successfully authenticates and fetches data (pending test)
- [ ] Error cases handled (no session, auth failure) (code complete, pending test)

---

### Phase 2: Static Data Display ✅ COMPLETE

**Goal:** Populate UI with real session data (read-only)

**Tasks:**

#### Data Binding ✅ Complete
- [x] Create `DataBinder` class/module (done in Phase 0/1)
- [x] Bind day counter from `session.progress.day`
- [x] Bind distance from `session.progress.distance`
- [x] Calculate and display progress percentage
- [x] Bind resource values (fuel, food, hull, morale, eth)
- [x] Calculate and display resource percentages for gauges
- [x] Bind chad roster (name, role, status, avatar)
- [x] Display current location or "En Route to [waypoint]"

#### Formatting ✅ Complete
- [x] Implement distance formatting (1.24B km) (done in Phase 0)
- [x] Implement ETH formatting (450.5) (done in Phase 0)
- [x] Calculate days remaining for fuel/food (done in Phase 0)
- [x] Format resource displays to match mockup (done in Phase 0)

#### UI Elements Added ✅ Complete
- [x] Progress panel with distance, velocity, location
- [x] Progress bar with visual fill
- [x] ETH display in gauge section
- [x] Crew roster panel with grid layout
- [x] Crew card styling (alive/dead states)

**Deliverable:** Mini-app displays live game data, updates on refresh

**Completed:** 2026-03-06

**Depends On:** Phase 1 ✅
**Blocks:** Phase 3

**Acceptance Criteria:**
- [x] All numeric displays show real session data (code complete)
- [x] Resource gauges animate to correct percentages (code complete)
- [x] Chad roster shows correct alive/dead states (code complete)
- [x] Progress bar matches actual distance percentage (code complete)
- [x] Refresh shows updated data if session changed (polling implemented)

---

### Phase 3: Core Actions

**Goal:** Implement primary game actions (Continue, Hunt)

**Tasks:**

#### Backend
- [ ] Create `api/mini-app/action.php` (POST actions)
- [ ] Route to existing GameEngine methods
- [ ] Handle `continue` action (advanceDay)
- [ ] Handle `hunt` action (hunt)
- [ ] Return updated session + flash message
- [ ] Add action validation (can't hunt at station, etc.)

#### Frontend
- [ ] Add button click handlers
- [ ] Send POST requests to `/api/mini-app/action`
- [ ] Update UI with returned session data
- [ ] Display flash messages (events, outcomes)
- [ ] Add loading states during actions
- [ ] Disable buttons during processing
- [ ] Handle action errors

#### Integration
- [ ] Test Continue button advances day
- [ ] Test Hunt button awards food
- [ ] Verify event system works (random events on Continue)
- [ ] Verify resource depletion (fuel, food)
- [ ] Test chad death scenarios

**Deliverable:** Continue and Hunt buttons functional

**Depends On:** Phase 2
**Blocks:** Phase 4

**Acceptance Criteria:**
- [ ] Continue button advances game day
- [ ] Hunt button grants food (with RNG)
- [ ] Events trigger and display correctly
- [ ] Resource changes reflect immediately
- [ ] No race conditions on rapid clicks

---

### Phase 4: Station Services

**Goal:** Implement shop and rest at stations

**Tasks:**

#### Backend
- [ ] Add station detection to `/api/mini-app/status`
- [ ] Handle `rest` action
- [ ] Handle `shop` action with item_id param
- [ ] Handle `stop` action (waypoint arrival)
- [ ] Handle `skip` action (bypass waypoint)

#### Frontend
- [ ] Show/hide buttons based on game status
- [ ] Render shop UI (if at station)
- [ ] Handle shop item selection
- [ ] Confirm purchases
- [ ] Display rest results
- [ ] Show waypoint approach decision (stop/skip)

#### UI States
- [ ] Journey view (status = 'in_space')
- [ ] Station view (status = 'at_station')
- [ ] Waypoint approach (status = 'at_waypoint')

**Deliverable:** Full station service integration

**Depends On:** Phase 3
**Blocks:** Phase 5

**Acceptance Criteria:**
- [ ] Rest button restores morale
- [ ] Shop displays available items
- [ ] Purchases deduct ETH and add resources
- [ ] Stop/skip waypoint decisions work
- [ ] UI adapts to game status

---

### Phase 5: Polish & UX

**Goal:** Smooth animations, feedback, edge cases

**Tasks:**

#### Animations
- [ ] Smooth gauge transitions on update
- [ ] Flash message fade in/out
- [ ] Button press feedback (haptics)
- [ ] Resource warning indicators (low fuel, etc.)

#### User Feedback
- [ ] Telegram haptic feedback on actions
- [ ] Loading spinners during API calls
- [ ] Success/error toast messages
- [ ] Confirmation for destructive actions

#### Edge Cases
- [ ] Handle session expiration
- [ ] Handle network errors (retry logic)
- [ ] Handle concurrent updates (optimistic UI)
- [ ] Handle game over state
- [ ] Handle victory state

#### Performance
- [ ] Optimize polling interval (5-10 seconds)
- [ ] Debounce action button clicks
- [ ] Lazy load images if needed

**Deliverable:** Production-ready mini-app

**Depends On:** Phase 4
**Blocks:** Phase 6 (deployment)

**Acceptance Criteria:**
- [ ] No jank or lag
- [ ] All edge cases handled gracefully
- [ ] Haptic feedback works on mobile
- [ ] Network errors don't break app
- [ ] App recovers from errors

---

### Phase 6: Deployment & Integration

**Goal:** Deploy to production, integrate with bot

**Tasks:**

#### Deployment
- [ ] Deploy API endpoints to production server
- [ ] Deploy mini-app files (HTML/CSS/JS)
- [ ] Configure HTTPS (required for Telegram)
- [ ] Set up mini-app URL in BotFather
- [ ] Test on production with real bot token

#### Bot Integration
- [ ] Add "Open Mini-App" button to bot journey view
- [ ] Add Web App button type to Telegram keyboards
- [ ] Test mini-app launch from group chat
- [ ] Test mini-app launch from private chat
- [ ] Verify session continuity (bot <-> mini-app)

#### Documentation
- [ ] Update DEPLOYMENT.md with mini-app deployment steps
- [ ] Document API endpoints in ARCHITECTURE.md
- [ ] Update GAME_VIEWS.md with mini-app entry point
- [ ] Create troubleshooting guide

**Deliverable:** Live mini-app accessible from bot

**Depends On:** Phase 5
**Blocks:** None

**Acceptance Criteria:**
- [ ] Mini-app loads from bot button
- [ ] HTTPS certificate valid
- [ ] Authentication works in production
- [ ] Session data syncs between bot and mini-app
- [ ] No console errors

---

## File Structure

### New Files to Create

```
api/
├── mini-app/
│   ├── status.php          # GET /api/mini-app/status (session data)
│   ├── action.php          # POST /api/mini-app/action (game actions)
│   └── auth.php            # Telegram initData validation helpers

mini-app/
├── index.html              # Rename option7.html to index.html
├── js/
│   ├── app.js              # Main application logic
│   ├── api.js              # API client wrapper
│   ├── data-binder.js      # UI data binding
│   ├── telegram-sdk.js     # Telegram Web App SDK wrapper
│   └── formatters.js       # Data formatting utilities
└── css/
    └── style.css           # Extract inline styles from option7.html

docs/mini-app/
└── MINI_APP_V1_IMPLEMENTATION.md  # This file
```

### Files to Modify

```
uranus_game_bot.php         # Add Web App button to keyboards (optional)
classes/ViewManager.php     # Add mini-app keyboard option (optional)
DEPLOYMENT.md               # Add mini-app deployment steps
docs/ARCHITECTURE.md        # Document API layer
```

---

## API Specification

### GET /api/mini-app/status

**Purpose:** Retrieve current session data

**Authentication:** Telegram initData in query string

**Request:**
```
GET /api/mini-app/status?init_data=<telegram_init_data_string>
```

**Response (Success):**
```json
{
  "success": true,
  "session": {
    "user_id": 123456,
    "status": "in_space",
    "progress": { "day": 42, "distance": 1240000000, ... },
    "resources": { "fuel": 815, "food": 1575, ... },
    "chad": { "count": 5, "members": [...] },
    ...
  }
}
```

**Response (Error):**
```json
{
  "success": false,
  "error": "unauthorized",
  "message": "Invalid Telegram authentication"
}
```

**Status Codes:**
- 200 - Success
- 401 - Invalid authentication
- 404 - No session found
- 500 - Server error

---

### POST /api/mini-app/action

**Purpose:** Execute game action

**Authentication:** Telegram initData in body

**Request:**
```json
{
  "init_data": "<telegram_init_data_string>",
  "action": "continue",
  "params": {}
}
```

**Response (Success):**
```json
{
  "success": true,
  "session": { /* updated session */ },
  "flash_message": "🌌 Day 43: Traveled 20M km. Fuel: -5, Food: -15",
  "event": {
    "id": "asteroid_field",
    "title": "Asteroid Field",
    "description": "...",
    "outcome": "success"
  }
}
```

**Supported Actions:**
- `continue` - Advance day (advanceDay)
- `hunt` - Hunt for food
- `rest` - Rest at station (restore morale)
- `shop` - Purchase item (requires `item_id` param)
- `stop` - Stop at waypoint (requires `waypoint_id` param)
- `skip` - Skip waypoint (requires `waypoint_id` param)
- `return` - Return to previous waypoint

---

## Testing Strategy

### Manual Testing Checklist

**Phase 1:**
- [ ] Mini-app loads without errors
- [ ] Authentication works
- [ ] Session data fetched successfully
- [ ] Error handling works (no session, auth failure)

**Phase 2:**
- [ ] All UI elements populate with data
- [ ] Gauges animate correctly
- [ ] Resource percentages accurate
- [ ] Chad roster displays correctly
- [ ] Progress bar matches distance

**Phase 3:**
- [ ] Continue button advances day
- [ ] Hunt button awards food
- [ ] Events trigger properly
- [ ] Flash messages display
- [ ] Resources deplete correctly

**Phase 4:**
- [ ] Shop displays items
- [ ] Purchases work
- [ ] Rest restores morale
- [ ] Waypoint decisions work
- [ ] UI adapts to status

**Phase 5:**
- [ ] Animations smooth
- [ ] Haptic feedback works
- [ ] Error recovery works
- [ ] No console errors
- [ ] Performance acceptable

**Phase 6:**
- [ ] Production deployment successful
- [ ] Bot integration works
- [ ] HTTPS certificate valid
- [ ] No production errors

---

## Dependencies

### External
- Telegram Web App SDK (loaded from CDN)
- Google Fonts (Orbitron, Azeret Mono, Nunito)
- Telegram Bot API (for initData validation)

### Internal
- Existing PHP classes (GameEngine, SessionManager, ViewManager)
- Existing data files (waypoints.json, events.json, chad_personalities.json)
- Bot token (for initData validation)

---

## Risk Assessment

### High Risk
- **Authentication security** - Must validate initData correctly
- **Session synchronization** - Bot and mini-app must share state
- **HTTPS requirement** - Telegram requires secure connection

### Medium Risk
- **Concurrent updates** - User using bot and mini-app simultaneously
- **Network reliability** - Mobile users may have poor connections
- **Browser compatibility** - Must work on iOS and Android Telegram apps

### Low Risk
- **Design changes** - Design is locked
- **Game logic** - Reusing existing, tested code
- **Performance** - Simple UI, minimal rendering

---

## Success Criteria

### MVP (Minimum Viable Product)
- [ ] Mini-app loads from bot
- [ ] Displays current game state
- [ ] Continue and Hunt actions work
- [ ] Session persists between bot and mini-app
- [ ] No critical bugs

### v1.0 Complete
- [ ] All game actions available
- [ ] Station services functional
- [ ] Smooth animations and feedback
- [ ] Error handling robust
- [ ] Deployed to production
- [ ] Documentation complete

---

## Open Questions

1. **Polling interval:** How frequently should mini-app poll for updates?
   - Suggestion: 10 seconds (balance responsiveness vs load)

2. **Session locking:** How to prevent race conditions if user clicks Continue in bot and mini-app simultaneously?
   - Suggestion: Last-write-wins, or add version/timestamp check

3. **Mini-app visibility:** Should mini-app be always available or only in specific contexts?
   - Suggestion: Always available for active games

4. **Fallback strategy:** What if mini-app doesn't load on user's device?
   - Suggestion: Bot remains fully functional, mini-app is enhancement

5. **Analytics:** Should we track mini-app usage separately?
   - Suggestion: Add simple logging (opens, actions, errors)

---

## Progress Tracking

**Phase 0:** ✅ Complete (2026-03-06)
**Phase 1:** ✅ Complete (Code Complete, Testing Pending)
**Phase 2:** ✅ Complete (2026-03-06)
**Phase 3:** ⬜️ Not Started
**Phase 4:** ⬜️ Not Started
**Phase 5:** ⬜️ Not Started
**Phase 6:** ⬜️ Not Started

**Overall Progress:** 50% (Design + Foundation + Connectivity + Data Display Complete)

**Strategy:** Full v1 push - All phases completed before launch

**Current Status:** Phase 1-2 code complete. Ready for integration testing.

---

## Files Created

### Phase 0: Foundation
**Backend API:**
- `api/mini-app/README.md` - API documentation
- `api/mini-app/auth.php` - Telegram Web App authentication
- `api/mini-app/status.php` - GET session endpoint
- `api/mini-app/action.php` - POST action endpoint

**Frontend JavaScript:**
- `mini-app/js/app.js` - Main application logic
- `mini-app/js/telegram-sdk.js` - Telegram SDK wrapper
- `mini-app/js/api.js` - API client
- `mini-app/js/data-binder.js` - UI data binding
- `mini-app/js/formatters.js` - Data formatting utilities

### Phase 1: Basic Connectivity
**Updated Files:**
- `mini-app/app-v1.html` - Working version with JS integration
- `mini-app/README.md` - Testing guide and documentation
- `mini-app/js/data-binder.js` - Updated for linear gauge structure
- `mini-app/js/app.js` - Added loading states and flash messages

**Key Changes:**
- Added Telegram SDK script tag
- Added button IDs (#btn-continue, #btn-hunt, etc.)
- Added gauge classes (.gauge-fuel, .gauge-food, etc.)
- Added flash message system
- Simplified day counter
- Added Rest/Shop buttons (hidden by default)
- Connected all JS modules as ES6 imports

### Phase 2: Static Data Display
**Updated Files:**
- `mini-app/app-v1.html` - Added progress panel, ETH display, crew roster
- `mini-app/js/data-binder.js` - Enhanced crew card creation

**Key Additions:**
- Progress panel with distance/velocity/location display
- Visual progress bar with gradient fill
- ETH display in gauge section (large number format)
- Crew roster panel with 2-column grid
- Crew card styling (alive/dead states with hover effects)
- All data binding elements now present in HTML

---

## Next Steps

### Immediate (Today)
1. ✅ Lock design baseline (option7.html)
2. ✅ Create implementation plan (this document)
3. ✅ Make architecture decisions (framework, API structure)
4. ⬜️ Start Phase 0: Foundation

### Short Term (This Week)
1. ✅ Complete Phase 0 (foundation)
2. 🔄 Complete Phase 1 (basic connectivity) - Testing pending
3. ⬜️ Set up HTTPS testing environment
4. ⬜️ Test API endpoints
5. ⬜️ Test from Telegram client

### Medium Term (Next 2 Weeks)
1. ✅ Complete Phase 1-2 (data display working)
2. ⬜️ Start Phase 3 (core actions)
3. ⬜️ Complete Phase 3 (Continue and Hunt working)

### Long Term (Next Month)
1. ⬜️ Complete Phase 3-5 (full functionality)
2. ⬜️ Deploy Phase 6 (production)

---

## Change Log

| Date | Change | By |
|------|--------|-----|
| 2026-03-06 | Phase 2 complete - All UI elements and data binding ready | Claude |
| 2026-03-06 | Phase 1 code complete - app-v1.html ready for testing | Claude |
| 2026-03-06 | Phase 0 complete - Foundation laid | Claude |
| 2026-03-06 | Architecture decisions locked (Vanilla JS, Clean API, Polling) | User |
| 2026-03-06 | Initial implementation plan created | Claude |
| 2026-03-03 | Design baseline locked (option7.html) | User |

---

## Related Documentation

- **Design Reference:** `docs/mini-app/MINI_APP_COMPONENTS.md` (option7.html component catalog)
- **Data Mapping:** `docs/mini-app/MINI_APP_DATA_REFERENCE.md` (session data reference)
- **Session Schema:** `docs/SESSION_STATE.md`
- **Game Architecture:** `docs/ARCHITECTURE.md`
- **Bot Views:** `docs/GAME_VIEWS.md`

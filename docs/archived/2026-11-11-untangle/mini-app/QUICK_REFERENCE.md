# Mini-App Quick Reference

**Last Updated:** 2026-03-06
**Status:** Phase 1-2 Complete (50%)

---

## Current State

### ✅ What's Done

**Backend (100%)**
- Authentication with HMAC-SHA256 signature verification
- GET `/api/mini-app/status.php` - Returns session JSON
- POST `/api/mini-app/action.php` - Executes game actions
- CORS headers configured for Telegram iframe
- Error handling (401, 404, 500)

**Frontend (100%)**
- Telegram SDK integration
- API client with authentication
- Data binding system (all UI elements)
- Button event handlers (Continue, Hunt, Rest, Shop, Status)
- 10-second polling for updates
- Loading states (button disable during actions)
- Flash message system (5s auto-hide)
- Haptic feedback on button clicks

**UI Elements (100%)**
- Day counter (mechanical drum display)
- Progress panel (distance, velocity, progress bar, location)
- Resource gauges (fuel, food, hull, morale, chad)
- ETH display (large format with glow)
- Crew roster (2-column grid, dynamic cards)
- Flash message panel
- Action buttons (Continue, Hunt, Rest, Shop, Status)
- Viewscreen (image placeholder)

### ⬜️ What's Not Done

**Testing (0%)**
- HTTPS tunnel setup
- BotFather registration
- API endpoint verification
- Session data loading test
- Button action test
- Data binding verification

**Features (Phases 3-6)**
- Shop UI (item selection, purchase)
- Rest confirmation dialog
- Detailed status view
- Map visualization
- Waypoint decisions (stop/skip)
- Return to waypoint
- Loading spinners (beyond button disable)
- Error recovery UI
- Production deployment

---

## File Structure

```
mini-app/
├── app-v1.html           ⭐ Main application (1407 lines)
├── option7.html          🔒 Design baseline (locked)
├── README.md             📖 Testing guide
└── js/
    ├── app.js            🎮 Main controller
    ├── telegram-sdk.js   📱 Telegram SDK wrapper
    ├── api.js            🌐 API client
    ├── data-binder.js    🔗 UI data binding
    └── formatters.js     🎨 Data formatting

api/mini-app/
├── auth.php              🔐 Authentication
├── status.php            📊 GET session
├── action.php            ⚡ POST actions
└── README.md             📖 API docs

docs/mini-app/
├── MINI_APP_V1_IMPLEMENTATION.md  📋 Master plan
├── PHASE_1_2_COMPLETION.md        ✅ Completion summary
├── QUICK_REFERENCE.md             📌 This file
├── MINI_APP_COMPONENTS.md         🎨 Component catalog
└── MINI_APP_DATA_REFERENCE.md     📊 Data mapping
```

---

## Data Flow

### Session Loading (Phase 1-2)
```
1. User opens mini-app from Telegram
2. Telegram SDK initializes
3. app.js extracts initData
4. GET /api/mini-app/status?init_data=...
5. auth.php validates HMAC-SHA256 signature
6. status.php loads session from SessionManager
7. JSON returned to frontend
8. data-binder.js binds session to UI
9. All gauges animate to positions
10. Crew roster populates
11. Polling starts (10s interval)
```

### Action Execution (Phase 3 - Not Yet Tested)
```
1. User clicks button (Continue, Hunt, etc.)
2. Haptic feedback triggered
3. Buttons disabled (loading state)
4. POST /api/mini-app/action with initData
5. auth.php validates signature
6. action.php routes to ActionManager
7. GameEngine executes action
8. Updated session + flash message returned
9. UI re-rendered with new data
10. Flash message displayed (5s)
11. Buttons re-enabled
```

---

## API Quick Reference

### Authentication
**Header:** None required
**Body/Query:** `init_data` (Telegram Web App initData string)
**Validation:** HMAC-SHA256 with bot token as secret
**Expiration:** 24 hours

### GET /api/mini-app/status.php
**Query:** `?init_data=<telegram_init_data>`
**Response:**
```json
{
  "success": true,
  "session": { /* full session object */ }
}
```

### POST /api/mini-app/action.php
**Body:**
```json
{
  "init_data": "<telegram_init_data>",
  "action": "continue",
  "params": {}
}
```
**Response:**
```json
{
  "success": true,
  "session": { /* updated session */ },
  "flash_message": "Day 43: Traveled 20M km...",
  "event": { /* event data if triggered */ }
}
```

### Supported Actions
- `continue` - Advance day (GameEngine::advanceDay)
- `hunt` - Hunt for food (GameEngine::hunt)
- `rest` - Rest at station (GameEngine::rest)
- `shop` - Purchase item (params: `item_id`)
- `stop` - Stop at waypoint (params: `waypoint_id`)
- `skip` - Skip waypoint (params: `waypoint_id`)

---

## Button Reference

| Button | ID | Handler | API Action | Status |
|--------|----|---------| -----------|--------|
| Continue | `#btn-continue` | `handleContinue()` | `continue` | Code complete |
| Hunt | `#btn-hunt` | `handleHunt()` | `hunt` | Code complete |
| Rest | `#btn-rest` | `handleRest()` | `rest` | Code complete |
| Shop | `#btn-shop` | `handleShop()` | N/A | Placeholder |
| Status | `#btn-status` | `handleStatus()` | N/A | Placeholder |

**Button Visibility:**
- Continue: Always visible
- Hunt: Hidden at stations (`status !== 'at_station'`)
- Rest: Hidden in space (`status !== 'at_station'`)
- Shop: Hidden in space (`status !== 'at_station'`)
- Status: Always visible (placeholder)

---

## Data Binding Reference

### Session → UI Mapping

| Session Field | UI Element | Format Example |
|---------------|-----------|----------------|
| `progress.day` | `.day-display` | `42` |
| `progress.distance` | `.current-distance` | `1.24B km` |
| `TOTAL_DISTANCE - distance` | `.remaining-distance` | `1.56B km to go` |
| `(distance / total) * 100` | `.progress-fill` width | `44%` |
| `progress.speed * multiplier` | `.velocity-display` | `20.0M km/d ⚡2×` |
| `status` / `at_waypoint` | `.location-label` | `En Route to Saturn` |
| `resources.fuel` | `.gauge-fuel` | `815 / 1200 (163d)` |
| `resources.food` | `.gauge-food` | `1575 / 3500 (105d)` |
| `resources.hull` | `.gauge-hull` | `85%` |
| `resources.vibes` | `.gauge-morale` | `72%` |
| `resources.chad` | `.gauge-chad` | `5 / 5` |
| `resources.eth` | `.eth-value` | `450.5` |
| `chad.members[]` | `.crew-roster` | Dynamic cards |

### Formatters

```javascript
// Distance
Formatters.formatDistance(1240000000) // "1.24B km"

// ETH
Formatters.formatEth(45050) // "450.5"

// Velocity
Formatters.formatVelocity(20000000, 2.0) // "20.0M km/d ⚡2.0×"

// Days Remaining
Formatters.calculateDaysRemaining(815, 5) // 163

// Resource Warning
Formatters.getWarningEmoji(4) // "🚨" (< 5 days)
Formatters.getWarningEmoji(8) // "⚠️" (< 10 days)
Formatters.getWarningEmoji(20) // "" (>= 10 days)
```

---

## Testing Checklist

### Setup
- [ ] Install ngrok: `brew install ngrok`
- [ ] Start PHP server: `php -S localhost:8080`
- [ ] Start ngrok tunnel: `ngrok http 8080`
- [ ] Copy HTTPS URL (e.g., `https://abc123.ngrok.io`)
- [ ] Register with @BotFather → Menu Button → URL
- [ ] Create test session: `/game` in bot

### Phase 1 Tests
- [ ] Mini-app loads without errors
- [ ] Console shows: `[App] Initializing...`
- [ ] Console shows: `[Telegram SDK] Initialized`
- [ ] Console shows: `[App] Session loaded:`
- [ ] No 401 unauthorized errors
- [ ] No 404 no session errors
- [ ] No CORS errors

### Phase 2 Tests
- [ ] Day counter shows correct value
- [ ] Distance shows formatted value (e.g., "1.24B km")
- [ ] Progress bar fills to correct percentage
- [ ] Velocity shows with boost indicator if applicable
- [ ] Location label shows current status
- [ ] Fuel gauge animates to correct position
- [ ] Food gauge animates to correct position
- [ ] Hull gauge animates to correct position
- [ ] Morale gauge animates to correct position
- [ ] Chad gauge animates to correct position
- [ ] ETH displays formatted value
- [ ] Crew roster populates with all members
- [ ] Alive crew cards have green border
- [ ] Dead crew cards are grayscaled

### Phase 3 Tests (Not Yet Ready)
- [ ] Continue button advances day
- [ ] Hunt button increases food
- [ ] Flash message appears after action
- [ ] UI updates after action
- [ ] Polling shows updates after 10s

---

## Common Issues

### "Failed to load game"
**Symptoms:** Alert on load, no data in console
**Causes:**
- API endpoints not accessible
- Telegram SDK not loaded
- CORS errors
- initData validation failed

**Debug:**
1. Open browser console (F12)
2. Check Network tab for failed requests
3. Verify Telegram SDK loaded: `console.log(window.Telegram)`
4. Check initData present: `console.log(window.Telegram.WebApp.initData)`

### Gauges not animating
**Symptoms:** Gauges stuck at 0% or static
**Causes:**
- Session data not loaded
- Data binding not executing
- JavaScript errors

**Debug:**
1. Check console for `[App] Session loaded:` message
2. Verify session structure: `console.log(window.app.session)`
3. Check for JavaScript errors in console
4. Inspect gauge elements: document.querySelector('.gauge-fuel')

### Buttons not working
**Symptoms:** Clicking buttons does nothing
**Causes:**
- Event listeners not attached
- Button IDs incorrect
- JavaScript errors

**Debug:**
1. Check console for `[App] Event listeners registered`
2. Verify button exists: `document.getElementById('btn-continue')`
3. Check button disabled state
4. Look for click handler errors in console

---

## Browser Console Commands

```javascript
// Check if app initialized
console.log(window.app);

// Check current session
console.log(window.app.session);

// Manually load session
await window.app.loadSession();

// Manually trigger action
await window.app.executeAction('continue', {});

// Check Telegram SDK
console.log(window.Telegram.WebApp);

// Check initData
console.log(window.Telegram.WebApp.initData);

// Force UI update
window.app.dataBinder.bindSession(window.app.session);

// Stop/start polling
window.app.stopPolling();
window.app.startPolling();
```

---

## Next Steps

### To Test Phase 1-2
1. Set up HTTPS tunnel
2. Register with BotFather
3. Open mini-app from Telegram
4. Check console for session data
5. Verify all UI elements display correctly

### To Build Phase 3
1. Verify action.php routing works
2. Test Continue button advances day
3. Test Hunt button increases food
4. Verify events trigger correctly
5. Test flash message display

### To Build Phase 4
1. Create shop UI modal
2. Add rest confirmation dialog
3. Implement waypoint decision flow
4. Test station service integration

---

## Key Decisions

**Locked:**
- ✅ Vanilla JS (no build tools)
- ✅ Clean API structure (separate endpoints)
- ✅ Polling (10s interval, not WebSocket)
- ✅ option7.html design (locked baseline)

**Deferred:**
- ⏸️ Shop UI implementation (Phase 4)
- ⏸️ Map visualization (Phase 5)
- ⏸️ Aesthetic switcher (Phase 5)
- ⏸️ WebSocket real-time updates (v2)

---

## Contact Points

**Implementation Plan:** `docs/mini-app/MINI_APP_V1_IMPLEMENTATION.md`
**Completion Summary:** `docs/mini-app/PHASE_1_2_COMPLETION.md`
**API Documentation:** `api/mini-app/README.md`
**Component Catalog:** `docs/mini-app/MINI_APP_COMPONENTS.md`
**Data Reference:** `docs/mini-app/MINI_APP_DATA_REFERENCE.md`

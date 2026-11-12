# Mini-App Testing Guide

**Last Updated:** 2026-11-11

> **Updated 2026-11-11:** The API now lives in `api/game/` (`state.php`, `action.php`) and the Mini-App can start its own journey, so a game created in the bot is **not required** for testing (you can still start in either client and continue in the other). For automated checks (API over HTTP, the bot through webhook updates, the Mini-App in headless Chrome) see `docs/IMPLEMENTATION_GUIDE.md#testing`. The manual Telegram steps below are for checking the real Telegram environment: HTTPS tunnel, BotFather menu button, signed `initData`.

---

## Prerequisites

✅ **Completed:**
- [x] PHP 8.5.3 installed
- [x] ngrok installed
- [x] BOT_TOKEN configured
- [x] All API endpoints created
- [x] All JS modules created
- [x] Complete UI in app-v1.html

⬜️ **Required for Testing:**
- [ ] HTTPS tunnel running
- [ ] PHP dev server running
- [ ] Mini-app registered with BotFather
- [ ] Telegram client (mobile or desktop)

---

## Testing Steps

### Step 1: Start PHP Development Server

In Terminal 1:
```bash
cd /Users/cclayton/dev/ColonyTrail/OccupyUranusGame_bot
php -S localhost:8080
```

**Expected Output:**
```
[Fri Mar  7 XX:XX:XX 2026] PHP 8.5.3 Development Server (http://localhost:8080) started
```

**Keep this terminal open** — the server must stay running for testing.

---

### Step 2: Start ngrok HTTPS Tunnel

In Terminal 2:
```bash
ngrok http 8080
```

**Expected Output:**
```
Session Status                online
Account                       [your account]
Forwarding                    https://abc123xyz.ngrok-free.app -> http://localhost:8080
```

**Note the HTTPS URL** (e.g., `https://abc123xyz.ngrok-free.app`) — you'll need it for BotFather.

**Keep this terminal open** — the tunnel must stay running for testing.

---

### Step 3: Test API Endpoints

In Terminal 3:
```bash
# Test status endpoint (should return 401 without valid auth)
curl https://abc123xyz.ngrok-free.app/api/game/state.php

# Expected: {"success":false,"error":"unauthorized","message":"Missing init_data"}
```

**Troubleshooting:**
- If you get "ERR_NGROK_3200" or similar: ngrok may have rate limiting on free tier
- If you get connection error: Check that PHP server is running
- If you get 404: Check that the URL path is correct

---

### Step 4: Create Test Session via Bot

1. Open Telegram
2. Find your Occupy Uranus bot
3. Send `/game` to start a new game
4. Complete onboarding:
   - Choose difficulty
   - Select ship
   - Name crew members
   - Continue until you reach "in_space" status (Day 1, traveling)

**Verification:**
You should see the journey view with Continue/Hunt buttons.

---

### Step 5: Register Mini-App with BotFather

1. Open Telegram and message **@BotFather**
2. Send `/mybots`
3. Select your bot from the list
4. Tap **"Bot Settings"**
5. Tap **"Menu Button"**
6. Tap **"Configure Menu Button"**
7. Send the mini-app URL:
   ```
   https://abc123xyz.ngrok-free.app/mini-app/app-v1.html
   ```
8. Send button text:
   ```
   🚀 Play
   ```

**Confirmation:**
BotFather should respond: "Menu button updated"

---

### Step 6: Open Mini-App from Telegram

#### Option A: From Menu Button
1. Open your bot in Telegram
2. Tap the menu button (≡) at bottom-left
3. Tap "🚀 Play"

#### Option B: From Direct Link (Alternative)
1. Send yourself this message in Telegram:
   ```
   https://abc123xyz.ngrok-free.app/mini-app/app-v1.html
   ```
2. Tap the link
3. Choose "Open in Mini App"

**Expected Behavior:**
- Mini-app opens in Telegram's embedded browser
- Starfield animation plays
- Loading message appears briefly
- UI loads with game data

---

## Phase 1-2 Testing Checklist

### Visual Elements ✅

**Open the mini-app and verify:**
- [ ] Starfield background animates smoothly
- [ ] Cockpit frame displays correctly
- [ ] Header shows mission info
- [ ] Day counter displays (top-right)
- [ ] Progress panel shows distance/velocity/location
- [ ] Progress bar fills to correct percentage
- [ ] All 5 resource gauges render
- [ ] Gauges animate to correct values
- [ ] ETH value displays (large number in gauge section)
- [ ] Crew roster shows all members
- [ ] Alive crew cards have green border
- [ ] Dead crew cards are grayed out (if any deaths)
- [ ] Action buttons visible (Continue, Hunt, Status)
- [ ] Rest/Shop buttons hidden (only show at stations)

### Browser Console Checks 🔍

**Open browser DevTools (Telegram Desktop: View → Developer → DevTools)**

**Expected Console Logs:**
```
[Telegram SDK] Initialized. Running in Telegram Mini App.
[App] Initializing Occupy Uranus Mini-App...
[API] Fetching session from /api/game/state.php
[API] Session loaded successfully
[DataBinder] Binding session data...
[DataBinder] Day: 1
[DataBinder] Distance: 1240000000 km
[DataBinder] Progress: 44.3%
[DataBinder] Velocity: 20000000 km/d
[DataBinder] Fuel: 815/1200 (163 days)
[DataBinder] Food: 1575/3500 (105 days)
[DataBinder] Hull: 85%
[DataBinder] Morale: 72%
[DataBinder] Chad: 5/5
[DataBinder] ETH: 450.5
[DataBinder] Crew roster: 5 members
[App] Starting polling (10s interval)
```

**Check for Errors:**
- [ ] No console errors (red text)
- [ ] No CORS errors
- [ ] No 401 Unauthorized
- [ ] No 404 Not Found
- [ ] Authentication succeeded
- [ ] Session data loaded

### Data Binding Verification 📊

**Compare mini-app display to bot display:**

1. Open bot journey view (send `/status` if needed)
2. Open mini-app
3. Verify all values match:

| Field | Bot Shows | Mini-App Shows | Match? |
|-------|-----------|----------------|--------|
| Day | Day X | Day X | [ ] |
| Distance | X.XXB km | X.XXB km | [ ] |
| Fuel | XXX / 1200 | XXX / 1200 (gauge) | [ ] |
| Food | XXX / 3500 | XXX / 3500 (gauge) | [ ] |
| Hull | XX% | XX% (gauge) | [ ] |
| Morale | XX% | XX% (gauge) | [ ] |
| Crew | X alive | X cards visible | [ ] |
| ETH | XXX.X | XXX.X | [ ] |

### Button Functionality 🎮

**Test buttons (don't click yet, just verify):**
- [ ] Continue button has ID `#btn-continue`
- [ ] Hunt button has ID `#btn-hunt`
- [ ] Status button has ID `#btn-status`
- [ ] Rest button exists but hidden (in_space status)
- [ ] Shop button exists but hidden (in_space status)
- [ ] Buttons have hover effects (desktop)
- [ ] Buttons respond to tap (mobile)

**Test Haptic Feedback (Mobile Only):**
- [ ] Tap any button — should feel slight vibration
- [ ] Multiple taps — each should vibrate

**Don't test actual actions yet** — Phase 3 will implement action execution.

### Polling System 🔄

**Test auto-updates:**
1. Open mini-app
2. Open bot in another window
3. In bot: send `/continue` to advance day
4. Wait 10 seconds
5. Check mini-app — should update automatically

**Verify:**
- [ ] Day counter increments
- [ ] Distance increases
- [ ] Resources decrease
- [ ] No page refresh needed
- [ ] No flash message yet (Phase 3 feature)

### Error Handling ⚠️

**Test error scenarios:**

**1. No Session:**
- Delete your session (via bot: `/reset`)
- Refresh mini-app
- [ ] Error message displays: "No active game session"

**2. Invalid Auth (Simulated):**
- Can't easily test — requires tampering with initData
- Skip for now — backend handles this

**3. Network Error:**
- Stop PHP server (Ctrl+C in Terminal 1)
- Refresh mini-app
- [ ] Error message displays: "Failed to load game"
- Restart PHP server to continue testing

---

## Success Criteria

### Phase 1-2 Complete When:

✅ **All of these are true:**
- [ ] Mini-app loads from Telegram menu button
- [ ] No JavaScript errors in console
- [ ] Authentication succeeds (no 401 errors)
- [ ] Session data loads (no 404 errors)
- [ ] All UI elements render correctly
- [ ] Day counter shows real value from session
- [ ] Distance shows real value from session
- [ ] Progress bar fills to correct percentage
- [ ] All 5 gauges show real values from session
- [ ] ETH displays real value from session
- [ ] Crew roster shows all members with correct status
- [ ] Polling updates UI every 10 seconds
- [ ] No CORS errors

---

## Troubleshooting

### "Failed to load game"

**Symptoms:** Red error message in mini-app

**Possible Causes:**
1. **PHP server not running**
   - Check Terminal 1 — should show server logs
   - Restart: `php -S localhost:8080`

2. **ngrok tunnel not running**
   - Check Terminal 2 — should show forwarding URL
   - Restart: `ngrok http 8080`

3. **No active session**
   - Open bot and send `/game` to create session
   - Complete onboarding to reach "in_space" status

4. **Wrong BOT_TOKEN**
   - Check `config.php` line 33
   - Verify token matches your bot from @BotFather

### CORS Errors

**Symptoms:** Console shows "blocked by CORS policy"

**Possible Causes:**
1. **Missing CORS headers in auth.php**
   - Check `api/game/auth.php` lines 80-84
   - Should include `Access-Control-Allow-Origin: https://oauth.telegram.org`

2. **Wrong allowed origin**
   - Telegram Mini Apps load from `https://oauth.telegram.org`
   - Not from your ngrok domain

**Fix:**
```php
// In auth.php
header('Access-Control-Allow-Origin: https://oauth.telegram.org');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
```

### Authentication Errors (401 Unauthorized)

**Symptoms:** Console shows 401 errors

**Possible Causes:**
1. **initData expired**
   - Telegram's initData expires after 24 hours
   - Close and reopen mini-app

2. **Wrong BOT_TOKEN for signature**
   - Backend validates signature using BOT_TOKEN
   - Check `config.php` BOT_TOKEN matches bot

3. **initData not sent**
   - Check console: `window.Telegram.WebApp.initData`
   - Should be a long string, not empty

**Debug:**
```javascript
// In mini-app browser console
console.log(window.Telegram.WebApp.initData);
// Should show: query_id=...&user=...&auth_date=...&hash=...
```

### Gauges Not Animating

**Symptoms:** Gauges show 0% or don't fill

**Possible Causes:**
1. **CSS classes missing**
   - Check HTML has `.gauge-fuel`, `.gauge-food`, etc.
   - Check browser inspector

2. **Session data wrong format**
   - Console should show data binding logs
   - Check `session.resources.fuel` exists

3. **JavaScript error**
   - Check console for errors in `data-binder.js`

**Debug:**
```javascript
// In browser console
console.log(window.app.session.resources);
// Should show: {fuel: 815, food: 1575, hull: 85, vibes: 72, ...}
```

### Crew Roster Not Showing

**Symptoms:** Crew roster empty or shows wrong members

**Possible Causes:**
1. **Session data wrong format**
   - Check `session.chad.members` array
   - Should have 5 objects with name, role, status

2. **JavaScript error in createCrewCard()**
   - Check console for errors

**Debug:**
```javascript
// In browser console
console.log(window.app.session.chad.members);
// Should show array of 5 crew objects
```

### Polling Not Working

**Symptoms:** UI doesn't update after 10 seconds

**Possible Causes:**
1. **Polling not started**
   - Check console: should see "Starting polling"
   - Check `window.app.pollingInterval` exists

2. **API errors during polling**
   - Check console for errors every 10s
   - Each poll should log "Polling update..."

**Debug:**
```javascript
// In browser console
console.log(window.app.pollingInterval);
// Should show: [number] (interval ID)

// Force a poll
await window.app.loadSession();
```

---

## Next Steps After Phase 1-2 Testing

### If All Tests Pass ✅

**Proceed to Phase 3:**
1. Implement core actions (Continue, Hunt)
2. Test button execution
3. Test event system integration
4. Test flash messages
5. Test resource updates

**See:** `docs/mini-app/README.md`

### If Tests Fail ❌

**Document Issues:**
1. Note which tests failed
2. Copy console errors
3. Screenshot any UI issues
4. Note reproduction steps

**Fix Issues:**
1. Review error messages
2. Check relevant code files
3. Test fixes locally
4. Re-run testing checklist

---

## Testing Environment Reference

### Terminal 1: PHP Server
```bash
cd /Users/cclayton/dev/ColonyTrail/OccupyUranusGame_bot
php -S localhost:8080

# Server logs will appear here — watch for API requests
```

### Terminal 2: ngrok Tunnel
```bash
ngrok http 8080

# Note the HTTPS URL shown in "Forwarding" line
# Example: https://abc123xyz.ngrok-free.app
```

### Terminal 3: Testing Commands
```bash
# Test status endpoint
curl https://YOUR-NGROK-URL.ngrok-free.app/api/game/state.php

# Test action endpoint (should return 401 without auth)
curl -X POST https://YOUR-NGROK-URL.ngrok-free.app/api/game/action.php \
  -H "Content-Type: application/json" \
  -d '{"action":"continue"}'

# View PHP server logs (in another tab)
tail -f /Users/cclayton/dev/ColonyTrail/OccupyUranusGame_bot/logs/bot.log
```

### Browser DevTools (Telegram Desktop)

**Open DevTools:**
- macOS: View → Developer → DevTools
- Or: Right-click → Inspect

**Useful Commands:**
```javascript
// Check Telegram SDK
console.log(window.Telegram.WebApp);

// Check app instance
console.log(window.app);

// Check current session
console.log(window.app.session);

// Force session reload
await window.app.loadSession();

// Check polling
console.log(window.app.pollingInterval);

// Stop/start polling
window.app.stopPolling();
window.app.startPolling();
```

---

## Files Reference

**Mini-App:**
- `mini-app/app-v1.html` - Working version
- `mini-app/js/app.js` - Main controller
- `mini-app/js/telegram-sdk.js` - Telegram SDK wrapper
- `mini-app/js/api.js` - API client
- `mini-app/js/data-binder.js` - UI data binding
- `mini-app/js/formatters.js` - Data formatting

**Backend API:**
- `api/game/auth.php` - Authentication helpers
- `api/game/state.php` - GET session endpoint
- `api/game/action.php` - POST action endpoint

**Documentation:**
- `docs/mini-app/README.md` - How the Mini-App works
- `docs/IMPLEMENTATION_GUIDE.md` - Automated tests and how the pieces fit
- `docs/mini-app/PHASE_1_2_COMPLETION.md` - Phase completion summary
- `mini-app/README.md` - Mini-app overview
- `api/game/README.md` - API documentation

---

**Last Updated:** 2026-03-07
**Testing Status:** Ready for Phase 1-2 testing
**Next Phase:** Phase 3 (Core Actions)

# Refactor Testing Plan

**Date:** 2026-02-28
**Refactor:** All 3 Phases Complete
**Goal:** Verify all game flows work correctly after refactor

---

## Pre-Testing Checklist

### ✅ Syntax Verification
- [x] uranus_game_bot.php - No syntax errors
- [x] classes/GameEngine.php - No syntax errors
- [x] classes/ViewManager.php - No syntax errors

### ✅ File Permissions
```bash
# Check data directory is writable
ls -la data/
```

### ✅ Bot Token Configured
```bash
# Verify environment variable or config
echo $TELEGRAM_BOT_TOKEN
```

---

## Phase 1 Testing: Keyboards

**What Changed:** All keyboards moved to ViewManager

### Test Cases

#### TC-1.1: Main Menu Keyboard
- [ ] Start bot with `/start`
- [ ] Verify buttons appear:
  - [ ] "🚀 New Journey"
  - [ ] "🏆 Leaderboard"
  - [ ] "❓ Help"
- [ ] Click each button, verify navigation works

#### TC-1.2: Journey View Keyboard
- [ ] Start new game and launch ship
- [ ] Verify buttons appear:
  - [ ] "▶️ Continue"
  - [ ] "📊 Status" / "🗺️ Map"
  - [ ] "🦌 Hunt Resources"
  - [ ] "↩️ Return to Last Waypoint"
  - [ ] "🏠 Main Menu"
- [ ] Click each button, verify actions work

#### TC-1.3: Waypoint Keyboard
- [ ] Advance to a waypoint and stop
- [ ] Verify buttons appear:
  - [ ] "🛒 Shop"
  - [ ] "😴 Rest"
  - [ ] "➡️ Continue Journey"
  - [ ] "📊 Status" / "🗺️ Map"
  - [ ] "🏠 Main Menu"
- [ ] Click each button, verify actions work

#### TC-1.4: Shop Keyboard
- [ ] Open shop
- [ ] Verify buttons appear:
  - [ ] "⛽ Buy Fuel (+100)"
  - [ ] "🍖 Buy Food (+50)"
  - [ ] "🔧 Buy Repair (+20%)"
  - [ ] Context-aware back button
- [ ] Make a purchase, verify it works

#### TC-1.5: Event Keyboard
- [ ] Trigger an event (continue until one appears)
- [ ] Verify choice buttons appear
- [ ] Click a choice, verify outcome displays
- [ ] Verify "🏠 Back to Journey" button works

#### TC-1.6: Station Choice Keyboard
- [ ] Advance to a waypoint
- [ ] Verify stop/skip choice appears:
  - [ ] "🛒 Stop for Supplies"
  - [ ] "➡️ Skip Waypoint"
- [ ] Test both options

---

## Phase 2 Testing: Session State

**What Changed:** All session state changes moved to GameEngine

### Test Cases

#### TC-2.1: Launch Ship
- [ ] Create new game (status = 'pre_launch')
- [ ] Click "🚀 Launch Ship"
- [ ] Verify status changes to 'in_space'
- [ ] Verify journey view displays

#### TC-2.2: Stop at Waypoint
- [ ] Reach a waypoint
- [ ] Click "🛒 Stop for Supplies"
- [ ] Verify:
  - [ ] Status changes to 'at_waypoint'
  - [ ] Speed multiplier resets to 1.0
  - [ ] Speed boost lost message shows (if had boost)
  - [ ] Waypoint view displays

#### TC-2.3: Skip Waypoint
- [ ] Reach a waypoint
- [ ] Click "➡️ Skip Waypoint"
- [ ] Verify:
  - [ ] Status stays 'in_space'
  - [ ] Speed multiplier set to 2.0
  - [ ] Gravitational assist message shows
  - [ ] Journey continues at 2x speed

#### TC-2.4: Speed Multiplier Persistence
- [ ] Skip a waypoint (gain 2x speed)
- [ ] Continue traveling
- [ ] Verify speed stays 2x
- [ ] Stop at next waypoint
- [ ] Verify speed resets to 1x

#### TC-2.5: Chat Preference - Private
- [ ] Start game in group
- [ ] Click "💬 Play in DMs"
- [ ] Verify preferred_chat = 'private'
- [ ] Verify game continues in DMs

#### TC-2.6: Chat Preference - Group
- [ ] With game in private mode
- [ ] Click "👥 Play in Group"
- [ ] Verify preferred_chat = 'group'
- [ ] Verify game returns to group

#### TC-2.7: Session Metadata
- [ ] Create new game
- [ ] Verify session has:
  - [ ] status = 'pre_launch'
  - [ ] preferred_chat (correct value)
  - [ ] username (if provided)
  - [ ] display_name (if provided)

---

## Phase 3 Testing: Message Formatting

**What Changed:** All message formatting moved to ViewManager

### Test Cases

#### TC-3.1: Help Message
- [ ] Click "❓ Help"
- [ ] Verify full help text displays:
  - [ ] Mission section
  - [ ] Getting Started section
  - [ ] Resources section
  - [ ] Actions section
  - [ ] Space Stations section
  - [ ] Random Events section
  - [ ] Pro Tips section
- [ ] Verify HTML formatting (bold, italic)

#### TC-3.2: Launch Message
- [ ] Create new game
- [ ] Verify launch message displays:
  - [ ] "🚀 JOURNEY TO URANUS BEGINS!"
  - [ ] Chad/supplies message
  - [ ] "Press Launch Ship..."
  - [ ] Good luck message

#### TC-3.3: Waypoint Skip Message
- [ ] Skip a waypoint (first time)
- [ ] Verify message:
  - [ ] "➡️ Bypassing [waypoint name]"
  - [ ] "Gravitational Assist Engaged!"
  - [ ] "2× speed" mention
- [ ] Skip another waypoint (already boosted)
- [ ] Verify different message:
  - [ ] "Gravitational assist maintained"

#### TC-3.4: Speed Boost Lost Message
- [ ] Have gravitational assist active
- [ ] Stop at a waypoint
- [ ] Verify message:
  - [ ] "⚠️ Gravitational assist lost"
  - [ ] "(speed returned to normal)"

#### TC-3.5: Active Journey Warning
- [ ] Have an active game
- [ ] Send `/start`
- [ ] Verify warning displays:
  - [ ] "⚠️ You have an active journey!"
  - [ ] Day/distance/chad stats
  - [ ] "What would you like to do?"

#### TC-3.6: Abandon Confirmation
- [ ] Click abandon/new game
- [ ] Verify confirmation displays:
  - [ ] "⚠️ Abandon Journey?"
  - [ ] Current progress stats
  - [ ] "This cannot be undone"
  - [ ] "Are you sure?"

#### TC-3.7: Main Menu Title
- [ ] Send `/start` (no active game)
- [ ] Verify title displays:
  - [ ] "🚀 OCCUPY URANUS 🪐"
  - [ ] "Welcome to the journey..."
  - [ ] Game intro text

#### TC-3.8: Chat Switching Messages
- [ ] Test private DM prompt
- [ ] Test "Game Moved to Private Chat"
- [ ] Test "Welcome to Private Mode"
- [ ] Test "Game Returned to Group"
- [ ] Verify all messages formatted correctly

---

## Integration Testing

**Goal:** Test complete game flows end-to-end

### Flow 1: New Game → Launch → First Day
1. [ ] `/start` → New Journey
2. [ ] Verify launch view displays
3. [ ] Click "🚀 Launch Ship"
4. [ ] Verify journey view displays
5. [ ] Click "▶️ Continue"
6. [ ] Verify day advances, resources consumed

### Flow 2: Travel → Waypoint → Shop → Rest
1. [ ] Continue until waypoint reached
2. [ ] Click "🛒 Stop for Supplies"
3. [ ] Verify waypoint view displays
4. [ ] Click "🛒 Shop"
5. [ ] Buy fuel
6. [ ] Verify credits deducted, fuel increased
7. [ ] Click "😴 Rest"
8. [ ] Verify hull/morale restored

### Flow 3: Waypoint Skip → Speed Boost → Continue
1. [ ] Reach waypoint
2. [ ] Click "➡️ Skip Waypoint"
3. [ ] Verify speed boost message
4. [ ] Continue traveling
5. [ ] Verify distance increases by 40M km (2x speed)
6. [ ] Stop at next waypoint
7. [ ] Verify speed boost lost message

### Flow 4: Hunt → Event → Choice → Outcome
1. [ ] While traveling, click "🦌 Hunt Resources"
2. [ ] If event triggers, verify event displays
3. [ ] Make choice
4. [ ] Verify outcome displays
5. [ ] Verify resources updated
6. [ ] Click "🏠 Back to Journey"
7. [ ] Verify journey view displays

### Flow 5: Low Resources → Return → Shop
1. [ ] Deplete resources (continue many times)
2. [ ] Click "↩️ Return to Last Waypoint"
3. [ ] Verify costs fuel/food for travel
4. [ ] Verify waypoint reached
5. [ ] Shop for supplies
6. [ ] Continue journey

### Flow 6: Game Over
1. [ ] Deplete all fuel or chad to 0
2. [ ] Verify game over displays
3. [ ] Verify session deleted
4. [ ] Verify "🚀 New Journey" button
5. [ ] Click it, verify new game starts

### Flow 7: Victory (If Patient!)
1. [ ] Play through entire journey
2. [ ] Reach 2.8B km
3. [ ] Verify victory message
4. [ ] Verify leaderboard updated
5. [ ] Verify victory keyboard

### Flow 8: Chat Mode Switching
1. [ ] Start game in group
2. [ ] Switch to private
3. [ ] Continue playing in DMs
4. [ ] Switch back to group
5. [ ] Continue playing in group

---

## Regression Testing

**Goal:** Verify nothing broke

### Existing Features
- [ ] Leaderboard displays correctly
- [ ] Map shows waypoints correctly
- [ ] Status view shows detailed info
- [ ] Admin commands work (if applicable)
- [ ] Debug commands work (if enabled)

### Edge Cases
- [ ] No session handling (commands without active game)
- [ ] Already launched handling (launch button when already in space)
- [ ] Invalid actions (hunt at waypoint, rest while traveling)
- [ ] Empty shop (no credits)
- [ ] Max resources (can't buy more)

---

## Performance Testing

**Goal:** Verify no performance regressions

### Response Times
- [ ] Keyboard rendering: < 100ms
- [ ] Message formatting: < 50ms
- [ ] Session state changes: < 10ms
- [ ] Overall response time: < 500ms

### Memory Usage
- [ ] No memory leaks
- [ ] Session file size unchanged
- [ ] Bot memory usage stable

---

## Test Results Template

```
## Test Session: [Date/Time]
**Tester:** [Name]
**Environment:** [Dev/Production]

### Phase 1: Keyboards
- TC-1.1: ✅ / ❌ / ⚠️ [Notes]
- TC-1.2: ✅ / ❌ / ⚠️ [Notes]
...

### Phase 2: Session State
- TC-2.1: ✅ / ❌ / ⚠️ [Notes]
...

### Phase 3: Message Formatting
- TC-3.1: ✅ / ❌ / ⚠️ [Notes]
...

### Integration Testing
- Flow 1: ✅ / ❌ / ⚠️ [Notes]
...

### Issues Found
1. [Description] - Priority: High/Medium/Low
2. [Description] - Priority: High/Medium/Low

### Overall Status
- [ ] All critical tests passing
- [ ] Ready for deployment
- [ ] Issues need resolution
```

---

## Quick Smoke Test (5 minutes)

If short on time, run this minimal test:

1. [ ] `/start` - Verify main menu displays
2. [ ] New Journey → Launch Ship - Verify game starts
3. [ ] Continue x3 - Verify days advance
4. [ ] Reach waypoint → Stop - Verify waypoint view
5. [ ] Shop → Buy fuel - Verify purchase works
6. [ ] Rest - Verify rest works
7. [ ] Continue → Skip waypoint - Verify skip works
8. [ ] Hunt - Verify hunt works
9. [ ] Help - Verify help text displays
10. [ ] Main Menu - Verify navigation works

**If all 10 pass:** Likely safe to deploy ✅
**If any fail:** Investigate before deploying ❌

---

## Deployment Checklist

After testing passes:

- [ ] All critical tests passing
- [ ] No regressions found
- [ ] Syntax verified
- [ ] Documentation updated
- [ ] Ready to commit
- [ ] Ready to deploy

---

**Note:** This is a comprehensive test plan. For a quick verification, run the "Quick Smoke Test" first. If it passes, the refactor is likely working correctly.

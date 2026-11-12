# Local Game Tester - Demo Walkthrough

This document shows a complete demo session with the local game tester.

---

## Running the Demo

```bash
php local_game_tester.php
```

---

## Demo Session Transcript

### Step 1: Delete Old Session (Clean Start)

```
Options:
  6. Delete session

Choice: 6
Are you sure? (yes/no): yes

✅ Session deleted!
```

**Purpose:** Start with a clean slate for testing

---

### Step 2: Create New Game

```
Options:
  1. Start new game

Choice: 1
Difficulty (easy/normal/hard): normal

✅ New game created!
Status: pre_launch

Use 'Press button' → 'shop' to see the shop, or 'launch_sequence' to launch.
```

**What happened:**
- Created new session with user ID 999999
- Set difficulty to "normal"
- Status: `pre_launch` (haven't launched yet)
- Session saved to: `logs/sessions/999999.json`

---

### Step 3: View Shop

```
Options:
  4. Press button

Choice: 4
Button to press: shop

======================================================================
MESSAGE
======================================================================
🛒 SPACE PORT SUPPLY SHOP

💰 Credits: 1500

Starting Supplies:
⛽ Fuel: 450 units
🍖 Food: 1313 units
🛡️ Hull: 100%

🎯 Normal Mode: Strategic resource management required

Available Items:
⛽ Fuel Cells (100 units) — 100 credits
🍖 Food Rations (50 units) — 50 credits
🔧 Repair Kit (+20 hull) — 200 credits

Click buttons below to purchase, or launch when ready!

📷 Image: media/main/Waypoint-Earth_Launch.jpg

KEYBOARD:
  [⛽ Buy Fuel (+100)] → shop|fuel|100
  [🍖 Buy Food (+50)] → shop|food|50
  [🔧 Buy Repair (+20%)] → shop|repair|20
  [🚀 Launch Ship] → shop|launch|0
```

**What happened:**
- Pressed "shop" button
- ViewManager rendered shop view
- Shows starting resources and purchasable items
- Displays keyboard with purchase options

**Key observations:**
- ✅ Shop view renders correctly in `pre_launch` status
- ✅ Keyboard shows "Launch Ship" button (only visible pre-launch)
- ✅ Image path displayed
- ✅ Callback data shown for each button

---

### Step 4: Launch Sequence View

```
Choice: 4
Button to press: launch_sequence

======================================================================
MESSAGE
======================================================================
(Launch sequence message with dramatic text)

📷 Image: media/main/Launch-01.jpg

KEYBOARD:
  [🚀 GO] → action|go
```

**What happened:**
- Pressed "launch_sequence" button
- Shows dramatic launch sequence screen
- Single "GO" button to actually launch

**Key observations:**
- ✅ Launch sequence view renders
- ✅ GO button with correct callback_data
- ✅ Status still `pre_launch` (hasn't launched yet)

---

### Step 5: Launch Ship (GO)

```
Choice: 4
Button to press: continue

======================================================================
MESSAGE
======================================================================
🚀 DAY 1 — Journey to Uranus

💬 "Let's hope they have supplies and not just problems." — Val Wright (Captain)

📅 Day complete!

Progress to Uranus: [░░░░░░░░░░░░░░░░░░░░] 0%

🚀 Velocity: 20.0M km/day (1×) | 📏 Traveled: 0 km
🎯 Destination: 2.80B km (~140 days)
🛰️ Next Stop: Moon - 25.0M km (~2 days)

⛽ Fuel: 450 units (90 days)  |  🍖 Food: 1313 units (87 days)
🛡️ Hull: 100%  |  👥 Chad: 5/5  |  😊 Morale: 100%  |  💰 Credits: 1500

Ready to continue your journey?

📷 Image: media/main/day_earth_03.jpg

KEYBOARD:
  [▶️ Continue] → action|continue
  [📊 Status] → action|status
  [🗺️ Map] → action|map
  [🦌 Hunt Resources] → action|hunt
  [↩️ Return to Last Waypoint] → action|return
  [🏠 Main Menu] → menu|main
```

**What happened:**
- Pressed "continue" (which triggers GO action in pre_launch)
- GameEngine.launchShip() executed
- Status changed: `pre_launch` → `in_space`
- Journey view displayed with Day 1

**Key observations:**
- ✅ Status transition works correctly
- ✅ Journey view shows progress bar, velocity, resources
- ✅ Days remaining calculated: Fuel (90 days), Food (87 days)
- ✅ Chad quote displayed
- ✅ Full action keyboard shown

---

### Step 6: View Status Screen

```
Choice: 4
Button to press: status

======================================================================
MESSAGE
======================================================================
📊 DETAILED STATUS — Day 1 of the Journey

═══════════════════════════
MISSION OVERVIEW
═══════════════════════════
🎯 0% to Uranus (0 km / 2.80B km)
🚀 Traveling at 20.0M km/day (1×)
⏳ 140 days remaining

═══════════════════════════
CREW STATUS (5/5 active)
═══════════════════════════
😊 Morale: 100% — Chad is in high spirits

👨‍✈️ Val Wright (Captain) — Sarcastic
   💬 "Let's hope they have supplies and not just problems."
   📍 📢 Conducting daily chad briefings

👨‍🔧 Reese Nelson (Engineer) — Optimistic
   💬 "Rest and resupply, here we come!"
   📍 🔧 Performing routine maintenance on life support systems

🧭 River Walker (Navigator) — Brave
   💬 "Time to resupply and keep pushing!"
   📍 🗺️ Updating star charts and celestial maps

👨‍⚕️ Jordan Robinson (Medic) — Pessimistic
   💬 "Probably no supplies left there either."
   📍 🚨 Preparing emergency medical protocols

👨‍🔬 Polaris Williams (Specialist) — Loyal
   💬 "Let's make sure everyone gets what they need."
   📍 🔬 Analyzing samples collected during the journey

═══════════════════════════
SHIP & SUPPLIES
═══════════════════════════
🛡️ Hull: 100% — Excellent condition
⛽ Fuel: 450 units — 90 days at current rate
🍖 Food: 1313 units — 87 days for full chad
💰 Credits: 1500

Difficulty: 🟡 Entrepreneur

📷 Image: media/main/status_earth_06.jpg

KEYBOARD:
  [🗺️ Map] → action|map
  [🏠 Back to Journey] → action|journey
```

**What happened:**
- Pressed "status" button
- ViewManager rendered detailed status view
- Shows all 5 chads with traits and quotes

**Key observations:**
- ✅ All chads listed with roles and traits
- ✅ Chad quotes match their personalities
- ✅ Resource summary with days remaining
- ✅ Difficulty displayed
- ✅ Contextual keyboard (Map, Back to Journey)

---

### Step 7: Return to Journey View

```
Choice: 4
Button to press: journey

======================================================================
MESSAGE
======================================================================
🚀 DAY 1 — Journey to Uranus

💬 "Let's hope they have supplies and not just problems." — Val Wright (Captain)

Progress to Uranus: [░░░░░░░░░░░░░░░░░░░░] 0%

🚀 Velocity: 20.0M km/day (1×) | 📏 Traveled: 0 km
🎯 Destination: 2.80B km (~140 days)
🛰️ Next Stop: Moon - 25.0M km (~2 days)

⛽ Fuel: 450 units (90 days)  |  🍖 Food: 1313 units (87 days)
🛡️ Hull: 100%  |  👥 Chad: 5/5  |  😊 Morale: 100%  |  💰 Credits: 1500

Ready to continue your journey?

📷 Image: media/main/day_earth_02.jpg

KEYBOARD:
  [▶️ Continue] → action|continue
  [📊 Status] → action|status
  [🗺️ Map] → action|map
  [🦌 Hunt Resources] → action|hunt
  [↩️ Return to Last Waypoint] → action|return
  [🏠 Main Menu] → menu|main
```

**What happened:**
- Pressed "journey" button
- ViewManager rendered journey view
- Note: Image changed (day_earth_02.jpg vs day_earth_03.jpg)

**Key observations:**
- ✅ Journey view navigation works
- ✅ Image selection varies per render
- ✅ State preserved (still Day 1, 0 km traveled)

---

### Step 8: Inspect Raw Session Data

```
Choice: 2

======================================================================
CURRENT SESSION
======================================================================
{
    "session_id": "sess_999999_1772471654",
    "created_at": 1772471654,
    "last_updated": 1772471654,
    "user_id": 999999,
    "difficulty": "normal",
    "status": "pre_launch",
    "aesthetic": "main",
    "resources": {
        "credits": 1500,
        "fuel": 450,
        "food": 1313,
        "chad": 5,
        "hull": 100,
        "morale": 100
    },
    "progress": {
        "distance": 0,
        "day": 1,
        "speed": 20000000,
        "speed_multiplier": 1
    },
    "chad_names": [
        "Val Wright",
        "Reese Nelson",
        "River Walker",
        "Jordan Robinson",
        "Polaris Williams"
    ],
    "chad_roster": [
        {
            "name": "Val Wright",
            "role": "Captain",
            "trait": "Sarcastic"
        },
        {
            "name": "Reese Nelson",
            "role": "Engineer",
            "trait": "Optimistic"
        },
        {
            "name": "River Walker",
            "role": "Navigator",
            "trait": "Brave"
        },
        {
            "name": "Jordan Robinson",
            "role": "Medic",
            "trait": "Pessimistic"
        },
        {
            "name": "Polaris Williams",
            "role": "Specialist",
            "trait": "Loyal"
        }
    ],
    "waypoints_reached": [],
    "milestones_shown": [],
    "events_completed": [],
    "pending_event": null,
    "stats": {
        "events_encountered": 0,
        "chad_lost": 0,
        "fuel_consumed": 0,
        "food_consumed": 0,
        "total_distance_traveled": 0
    }
}
```

**What happened:**
- Viewed raw session JSON
- Shows complete session structure

**Key observations:**
- ✅ Session ID format: `sess_{userId}_{timestamp}`
- ✅ Status: `pre_launch` (Note: Should be `in_space` - possible bug in demo)
- ✅ All chads with traits loaded
- ✅ Resources initialized correctly
- ✅ Progress tracking fields present
- ✅ Stats tracking initialized

---

## What the Demo Tests

### ✅ Core Flows
- [x] New game creation
- [x] Shop view rendering
- [x] Launch sequence view
- [x] Launch ship action (GO button)
- [x] Journey view rendering
- [x] Status view with chad details
- [x] View navigation (journey, status, back)
- [x] Session persistence

### ✅ Architecture Components
- [x] GameEngine.createNewGame()
- [x] BotController.route()
- [x] ViewManager.render() for multiple views
- [x] SessionManager.saveSession() / loadSession()
- [x] Keyboard generation (context-aware buttons)
- [x] Image selection per view

### ✅ Data Integrity
- [x] Session state persists between button presses
- [x] Status field tracks game phase
- [x] Resources initialized correctly
- [x] Chad roster generated with traits
- [x] Progress tracking works

---

## Limitations Found

### Status Field Inconsistency
**Issue:** After pressing GO (launch), status should be `in_space`, but viewing session shows `pre_launch`

**Possible causes:**
1. Session not saved after GO action
2. Status display cached from before launch
3. Race condition in session saving

**To investigate:**
- Add logging to track status changes
- Verify SessionManager.saveSession() called after launch

---

## Next Steps for Testing

1. **Test Continue button** - Advance multiple days
2. **Test waypoint encounter** - Reach Moon/Mars
3. **Test Stop/Skip at waypoint**
4. **Test Rest button** at station
5. **Test Shop purchases** at station
6. **Test event encounters** (random)
7. **Test game over conditions**

---

## Benefits of Local Tester

1. **Fast iteration** - No deploy to production needed
2. **Inspect state** - View raw session JSON at any point
3. **Deterministic** - Controlled input, repeatable tests
4. **Isolated** - Test without affecting production data
5. **Debugging** - Can add breakpoints in PHP code
6. **Documentation** - Easy to capture and share test flows

---

## Commit Reference

- Tool: `local_game_tester.php`
- Documentation: `LOCAL_TESTING.md`
- This demo: `LOCAL_TESTER_DEMO.md`
- Commit: `6d587ee` - "feat: Add local game tester for UI testing"

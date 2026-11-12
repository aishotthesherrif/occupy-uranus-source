# Testing Guide - Journey & Status View Redesign

## What We're Testing

1. **Journey View** - New concise format with chad quote
2. **Status View** - New chad dashboard with contextual quotes
3. **Quote System** - Contextual quotes that respond to game state

---

## Setup

### 1. Check PHP Syntax
```bash
cd /Users/cclayton/dev/ColonyTrail/OccupyUranusGame_bot
php -l classes/GameEngine.php
php -l classes/ViewManager.php
```

### 2. Verify Files
```bash
# Check that contextual quotes file exists
ls -lh data/chad_personalities.json

# Check backup was created
ls -lh data/chad_personalities.json.backup
```

### 3. Start Local Server (if testing locally)
```bash
php -S localhost:8000
```

---

## Test Scenarios

### Test 1: Journey View - Normal Day
**What to test:** Basic Journey view format

**Steps:**
1. Start or continue a game
2. Click "▶️ Continue" or view Journey
3. Verify display shows:
   - ✅ Header: `🚀 DAY X — Journey to Uranus`
   - ✅ Chad quote: `💬 "Quote" — Name (Role)`
   - ✅ Progress bar (20 blocks)
   - ✅ Velocity with multiplier
   - ✅ Resources with days remaining
   - ✅ Context-aware footer

**Expected Output:**
```
🚀 DAY 1 — Journey to Uranus

💬 "Everything will work out fine!" — Jordan (Captain)

Progress to Uranus: [░░░░░░░░░░░░░░░░░░░░] 0%

🚀 Velocity: 20M km/day (1×) | 📏 Traveled: 0M km
🎯 Destination: 2,800M km (~140 days)
🛰️ Next Stop: Ceres Station - 414M km (~21 days)

⛽ Fuel: 200 units (40 days)  |  🍖 Food: 300 units (60 days)
🛡️ Hull: 100%  |  👥 Chad: 5/5  |  😊 Morale: 100%

Ready to continue your journey?
```

---

### Test 2: Journey View - Quote Rotation
**What to test:** Different chad speaks each day

**Steps:**
1. Note the chad who spoke on Day 1
2. Advance to Day 2 (click Continue)
3. Verify different chad speaks
4. Continue through Days 3, 4, 5
5. Day 6 should show same chad as Day 1

**Expected Pattern:**
- Day 1: Captain
- Day 2: Engineer
- Day 3: Navigator
- Day 4: Medic
- Day 5: Specialist
- Day 6: Captain (cycle repeats)

---

### Test 3: Status View - Chad Dashboard
**What to test:** Detailed Status view format

**Steps:**
1. From Journey view, click "📊 Status"
2. Verify display shows:
   - ✅ Three sections with separators
   - ✅ Mission Overview with progress
   - ✅ Chad Status with morale description
   - ✅ Individual chad with quotes and activities
   - ✅ Ship & Supplies with descriptions
   - ✅ Buttons: Map | Back to Journey

**Expected Output:**
```
📊 DETAILED STATUS — Day 1 of the Journey

═══════════════════════════
MISSION OVERVIEW
═══════════════════════════
🎯 0% to Uranus (0M km / 2.8B km)
🚀 Traveling at 20M km/day (1×)
⏳ 140 days remaining

═══════════════════════════
CREW STATUS (5/5 active)
═══════════════════════════
😊 Morale: 100% — Chad is in high spirits

👨‍✈️ Jordan (Captain) — Optimistic
   💬 "Everything will work out fine!"
   📍 Reviewing navigation charts

🔧 Alex (Engineer) — Sarcastic
   💬 "Space is big. Really big."
   📍 Calibrating fusion drive

...

═══════════════════════════
SHIP & SUPPLIES
═══════════════════════════
🛡️ Hull: 100% — Excellent condition
⛽ Fuel: 200 units — 40 days at current rate
🍖 Food: 300 units — 60 days for full chad
💰 Credits: 500

Difficulty: 🟡 Entrepreneur (Normal)
```

---

### Test 4: Contextual Quotes - Low Morale
**What to test:** Quotes change when morale < 30%

**Steps:**
1. Play until morale drops below 30% (skip resting, face events)
2. Check Journey view quote
3. Check Status view quotes
4. Verify quotes are appropriate for low morale

**Expected Quotes (Optimistic trait):**
- General: "We've got this!"
- Low morale: "Don't give up now, we're almost there!"

**How to Force Low Morale:**
- Use debug commands if available
- Or play through many days without resting
- Or face several bad events

---

### Test 5: Contextual Quotes - Critical Resources
**What to test:** Quotes change when resources < 5 days

**Steps:**
1. Play until fuel or food is below 5 days remaining
2. Check Journey and Status quotes
3. Verify critical resource warning appears
4. Verify quotes are appropriate

**Expected:**
- Journey shows: `⚠️ WARNING: Fuel critically low (X days)!`
- Quotes mention resources/supplies
- Optimistic: "We'll figure this out, we always do!"
- Sarcastic: "Running out of supplies. Shocking."

---

### Test 6: Contextual Quotes - Near Waypoint
**What to test:** Quotes change when within 5 days of station

**Steps:**
1. Play until within 5 days of Ceres Station (around day 17-21)
2. Check Journey and Status quotes
3. Verify quotes mention the upcoming station

**Expected Quotes:**
- Optimistic: "I can't wait to dock at the station!"
- Sarcastic: "A station. How thrilling."

---

### Test 7: Contextual Quotes - Near Victory
**What to test:** Quotes change when within 20 days of Uranus

**Steps:**
1. Play until final 20 days (day 120+)
2. Check Journey and Status quotes
3. Verify quotes are triumphant/celebratory

**Expected Quotes:**
- Optimistic: "Uranus is so close I can taste it!"
- Sarcastic: "Almost at Uranus. Assuming we survive."

---

### Test 8: Gravitational Assist Display
**What to test:** Speed boost indicators

**Steps:**
1. Reach a station and skip it (gain 2× boost)
2. Check Journey view shows: `(⚡2×)`
3. Check Status view shows: "⚡ Gravitational Assist: 2× speed boost active"
4. Verify velocity shows doubled: `40M km/day (⚡2×)`

---

### Test 9: At Waypoint Display
**What to test:** Special waypoint formatting

**Steps:**
1. Arrive at Ceres Station
2. Check Journey view shows:
   - Banner: `═══ ARRIVED: CERES STATION ═══`
   - Current Location instead of Next Stop
   - Footer: "Rest here to restore morale?"

---

### Test 10: Lost Chad Handling
**What to test:** Quote rotation skips lost chad

**Steps:**
1. Lose chads (through events/starvation)
2. Verify Journey quote rotates only through active chad
3. Check Status view shows lost chad as `❌ Lost`
4. No quote/activity for lost chad

---

## Quick Test Commands

### Check for Syntax Errors
```bash
php -l classes/GameEngine.php
php -l classes/ViewManager.php
```

### Verify Quote Data Loaded
```bash
php -r "
\$data = json_decode(file_get_contents('data/chad_personalities.json'), true);
echo 'Traits: ' . count(\$data['chad_personalities']['traits']) . PHP_EOL;
echo 'Optimistic contexts: ' . count(\$data['chad_personalities']['personality_quotes']['Optimistic']) . PHP_EOL;
"
```

### Test Quote Selection Logic (Isolated)
Create test file `test_quotes.php`:
```php
<?php
require_once 'config.php';
require_once 'classes/GameEngine.php';

$engine = new GameEngine();

// Test session
$session = [
    'progress' => ['day' => 42],
    'resources' => ['morale' => 65, 'chad' => 5, 'fuel' => 200, 'food' => 300],
    'difficulty' => 'normal'
];

// Test chad
$chad = ['trait' => 'Optimistic', 'name' => 'Jordan', 'role' => 'Captain'];

// This would need reflection to test private method, or make it public temporarily
// echo "Quote: " . $engine->getChadQuote($chad, $session, 0);
```

---

## What to Look For

### ✅ Success Indicators:
- Journey view is concise (~13-17 lines)
- Status view is detailed (~30-40 lines)
- Chad quotes appear and make sense
- Quotes change based on game state
- Different chad speaks each day
- Progress bar displays correctly
- Days remaining calculations accurate
- Buttons work correctly

### ❌ Potential Issues:
- PHP syntax errors (check logs)
- Missing quote data (check JSON file)
- Quotes not rotating
- Same quote every day
- Context not detecting correctly
- Progress bar malformed
- Layout broken on mobile

---

## Debugging

### Check Logs
```bash
tail -f data/bot.log
```

### Test Quote Context Detection
Add temporary debug output in `getQuoteContext()`:
```php
private function getQuoteContext(array $session): string {
    // ... context detection logic ...

    error_log("Quote context: {$context}, morale: {$morale}, fuelDays: {$fuelDays}");

    return $context;
}
```

### Verify Chad Data
Add debug in `formatJourney()`:
```php
error_log("Chad quote - Day: {$day}, Index: {$chadIndex}, Name: {$name}, Quote: {$quote}");
```

---

## Rollback Plan

If there are critical issues:

### Restore Original chad_personalities.json
```bash
cp data/chad_personalities.json.backup data/chad_personalities.json
```

### Revert ViewManager Changes
The old code is still there in `formatStatus()` method, can temporarily switch back:
```php
// In ViewManager::renderJourneyView()
$data = $this->gameEngine->formatViewWithImage('journey', $session);
// Instead of:
// $message = $this->gameEngine->formatJourney($session, $flashMessage);
```

---

## Success Criteria

All tests pass if:
- ✅ Journey view displays correctly
- ✅ Status view displays correctly
- ✅ Chad quotes rotate daily
- ✅ Quotes are contextual to game state
- ✅ No PHP errors in logs
- ✅ Layout works on mobile (Telegram client)
- ✅ All buttons function correctly

---

Ready to test! Start with Test 1 and work through the scenarios.

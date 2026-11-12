# Journey & Status View Redesign Options

## Current Problem
Both views use the same `formatStatus()` method. Journey just hides chad details, making them nearly identical. Journey should be concise and action-focused; Status should be detailed and informational.

---

## JOURNEY VIEW OPTIONS

### Option A: Minimal "Captain's Log" Style
**Philosophy:** Focus on what just happened and what's next. Quick glance at critical resources.

```
📅 DAY 42 — Mission to Uranus

🚀 Distance Today: 20M km
📍 Progress: 840M km / 2.8B km (30%)
⏳ ETA: 98 days

⚠️ Critical Resources:
⛽ Fuel: 245 units  |  🍖 Food: 180 units
🛡️ Hull: 78%       |  👥 Chad: 5/5  |  😊 Morale: 65%

[Continue] [Status] [Map]
[🛒 Shop] (if at waypoint)
[Rest] (if at waypoint) | [Hunt] (if in space)
```

**Pros:**
- Very scannable
- Focus on actionable info
- Shows "what happened today"
- Clear resource warnings

**Cons:**
- Less atmospheric
- Minimal context about location
- No chad personality

---

### Option B: "Status Report" Style
**Philosophy:** Brief narrative with key stats. Feels like a daily briefing.

```
📊 MISSION STATUS — Day 42

🌌 Location: Asteroid Belt (840M km traveled)
🎯 Destination: 1.96B km to Uranus (30% complete)
🚀 Speed: 20M km/day  |  ⏳ ETA: 98 days

⚡ GRAVITATIONAL ASSIST ACTIVE — 2× Speed Boost
(if speed_multiplier > 1.0)

📦 Ship Condition:
🛡️ Hull: 78%  |  👥 Chad: 5/5  |  😊 Morale: 65%

💼 Supplies:
⛽ Fuel: 245 units  |  🍖 Food: 180 units

💡 Available Actions:
• Continue traveling (1 day)
• View detailed chad status
• Check solar system map
• Rest at waypoint (restores morale)
(contextual based on location)

[Continue] [Status] [Map]
[Shop/Rest/Hunt based on location]
```

**Pros:**
- More narrative/immersive
- Clear action descriptions
- Better context about location
- Shows boost status prominently

**Cons:**
- Longer (more scrolling)
- Action list redundant with buttons
- May feel verbose after many plays

---

## STATUS VIEW OPTIONS

### Option A: "Ship Manifest" Style
**Philosophy:** Detailed, organized report. Everything in categories.

```
🛸 SHIP STATUS REPORT — Day 42
━━━━━━━━━━━━━━━━━━━━━━━━━━━━

📍 MISSION PROGRESS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🎮 Difficulty: 🟡 Entrepreneur (Normal)
📅 Days Elapsed: 42
📏 Distance Traveled: 840M km
🎯 Distance Remaining: 1.96B km
📈 Progress: 30%
🚀 Current Speed: 20M km/day
⏳ Estimated Arrival: Day 140 (98 days)

⚡ Speed Boost: 2× ACTIVE
(if speed_multiplier > 1.0)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🛠️ SHIP SYSTEMS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━
🛡️ Hull Integrity: 78%
⛽ Fuel Reserves: 245 units
🍖 Food Supplies: 180 units
💰 Credits: 150

━━━━━━━━━━━━━━━━━━━━━━━━━━━━
👥 CREW ROSTER (5/5)
😊 Morale: 65%
━━━━━━━━━━━━━━━━━━━━━━━━━━━━

👨‍✈️ Jordan — Captain (Optimist)
   ✅ Active
   Currently: Reviewing navigation charts

🔧 Alex — Engineer (Pragmatist)
   ✅ Active
   Currently: Calibrating fusion drive

🧭 Taylor — Navigator (Cynic)
   ✅ Active
   Currently: Plotting course corrections

⚕️ Morgan — Medic (Stoic)
   ✅ Active
   Currently: Conducting health screenings

🔬 Casey — Specialist (Joker)
   ✅ Active
   Currently: Analyzing cosmic radiation

━━━━━━━━━━━━━━━━━━━━━━━━━━━━

[Map] [Back to Journey]
```

**Pros:**
- Very organized/scannable
- Clear visual separation
- Professional/technical feel
- All info in logical categories

**Cons:**
- Long (lots of scrolling)
- Heavy use of separators
- May feel clinical
- Lost personality

---

### Option B: "Chad Dashboard" Style
**Philosophy:** Focus on chad as characters, resources as supporting info.

```
📊 DETAILED STATUS — Day 42 of the Journey

═══════════════════════════
MISSION OVERVIEW
═══════════════════════════
🎯 30% to Uranus (840M / 2,800M km)
🚀 Traveling at 20M km/day
⏳ 98 days remaining

⚡ Gravitational Assist: 2× speed boost active
(if speed_multiplier > 1.0)

═══════════════════════════
CREW STATUS (5/5 active)
═══════════════════════════
😊 Morale: 65% — Chad is managing well

👨‍✈️ Jordan (Captain) — Optimist
   💬 "Uranus is closer than you think!"
   📍 Reviewing navigation charts

🔧 Alex (Engineer) — Pragmatist
   💬 "Systems nominal... for now."
   📍 Calibrating fusion drive

🧭 Taylor (Navigator) — Cynic
   💬 "Space is big. Really big."
   📍 Plotting course corrections

⚕️ Morgan (Medic) — Stoic
   💬 "Everyone's still breathing."
   📍 Conducting health screenings

🔬 Casey (Specialist) — Joker
   💬 "Did you hear about the black hole's diet?"
   📍 Analyzing cosmic radiation

═══════════════════════════
SHIP & SUPPLIES
═══════════════════════════
🛡️ Hull: 78% — Minor repairs needed
⛽ Fuel: 245 units — 12 days at current rate
🍖 Food: 180 units — 12 days for full chad
💰 Credits: 150

Difficulty: 🟡 Entrepreneur (Normal)

[Map] [Back to Journey]
```

**Pros:**
- Chad personalities shine
- Quotes add flavor/humor
- More engaging to read
- Feels like checking in on friends

**Cons:**
- Chad section dominates
- Resources feel secondary
- Longer text overall
- Quotes may get repetitive

---

## RECOMMENDATIONS

### For Journey View:
**Recommend Option A (Captain's Log)**
- Fastest to scan during gameplay
- Shows what changed today
- Players see this most often, needs to be quick
- Can add flavor text for special moments (events, waypoints)

### For Status View:
**Recommend Option B (Chad Dashboard)**
- Players deliberately click Status to see details
- Emphasizes the chad (core mechanic)
- Personality quotes add replayability
- Resources still visible but not overwhelming
- Better fits the Oregon Trail parody vibe

---

## IMPLEMENTATION NOTES

### Journey View Changes:
1. Create new `formatJourney()` method (separate from `formatStatus()`)
2. Focus on delta (what changed today)
3. Keep it under ~15 lines
4. Optional: Add special messages for:
   - First day
   - Passing waypoints
   - Speed boost changes
   - Critical resource warnings

### Status View Changes:
1. Create new `formatDetailedStatus()` method
2. Keep all existing info from current `formatStatus()`
3. Add personality quotes (random from chad data)
4. Better visual organization with section headers
5. Optional: Add contextual tips based on resources

### Shared Elements:
- Both show gravitational assist banner when active
- Both use same keyboard generation (already separate methods)
- Both pull from same session state (no data structure changes)
- Flash messages only on Journey (not Status)

---

## NEXT STEPS

1. **Choose preferred options** for each view
2. **Design flash message format** - how should event outcomes, rest results, hunt results appear on Journey view?
3. **Create formatters** - implement new methods in GameEngine.php
4. **Update ViewManager** - wire up new formatters
5. **Test visual balance** - ensure readability on mobile
6. **Consider additional contexts:**
   - What about Shop view? (currently has its own formatter)
   - What about Map view? (currently plain text ASCII)
   - Should we add any other specialized views?

# Journey View Implementation

## Summary
Implemented new concise Journey view format based on design decisions. Journey view is now separate from Status view with its own formatter.

---

## Implementation Details

### New Methods Created:

1. **`GameEngine::formatJourney()`** - Main formatter for Journey view
   - Location: GameEngine.php (before formatStatus)
   - Takes session and optional flash message
   - Returns formatted message string

2. **`GameEngine::getNextWaypoint()`** - Helper to find next waypoint
   - Location: GameEngine.php (private helper)
   - Finds the next waypoint ahead of current position
   - Returns waypoint data or null

3. **`GameEngine::getCriticalResourceWarnings()`** - Helper for resource warnings
   - Location: GameEngine.php (private helper)
   - Checks fuel, food, hull, morale
   - Returns array of warning strings
   - Triggers when:
     - Fuel < 10 days remaining
     - Food < 10 days remaining
     - Hull < 30%
     - Morale < 30%

### Updated Methods:

1. **`ViewManager::renderJourneyView()`**
   - Now calls `formatJourney()` instead of `formatViewWithImage('journey')`
   - Passes flash message from context
   - Uses 'day' image context

---

## Format Specification

### Header
```
🚀 DAY {day} — Journey to Uranus
```

### Daily Chad Quote (NEW)
```
💬 "{contextual_quote}" — {chad_name} ({role})

```

**Selection:** Rotates through active chad based on day: `(day - 1) % active_chad`
**Quote:** Uses same contextual quote system as Status view (responds to game state)
**Example:** `💬 "We've got this!" — Jordan (Captain)`

### Flash Message (Optional)
```
{flash_message}

```

### Waypoint Banner (If at waypoint)
```
═══════════════════════════
🛰️ ARRIVED: {WAYPOINT_NAME}
═══════════════════════════

```

### Progress Section
```
Progress to Uranus: [████████████░░░░░░░░] 30%

🚀 Velocity: 20M km/day (1×) | 📏 Traveled: 840M km
🎯 Destination: 1,960M km (~98 days)
🛰️ Next Stop: Ceres Station - 120M km (~6 days)
```

**OR when at waypoint:**
```
🛰️ Current Location: Ceres Station
```

### Critical Warning (If applicable)
```
⚠️ WARNING: Fuel critically low (3 days)!
```

### Resources Section
```
⛽ Fuel: 245 units (12 days)  |  🍖 Food: 180 units (12 days)
🛡️ Hull: 78%  |  👥 Chad: 5/5  |  😊 Morale: 65%
```

### Footer (Context-aware)
```
Ready to continue your journey?
```

**OR when at waypoint:**
```
Rest here to restore morale?
```

---

## Key Features

### 20-Block Progress Bar
- Filled blocks (█) for progress
- Empty blocks (░) for remaining
- Shows percentage

### Velocity Display
- Shows effective speed with multiplier
- Normal: `20M km/day (1×)`
- Boosted: `40M km/day (⚡2×)`

### Days Remaining Calculation
- Fuel: `units / (chad × FUEL_PER_CREW)`
- Food: `units / (chad × FOOD_PER_CREW)`
- Displayed inline with resource values

### Next Waypoint Detection
- Automatically finds next waypoint ahead
- Shows distance and ETA
- Replaces with "Current Location" when at waypoint

### Critical Warnings
- Multiple warnings can display (comma-separated)
- Only shows when thresholds met:
  - Fuel < 10 days: "Fuel critically low (X days)!"
  - Food < 10 days: "Food critically low (X days)!"
  - Hull < 30%: "Hull integrity critical!"
  - Morale < 30%: "Chad morale dangerously low!"

### Context-Aware Footer
- In space: "Ready to continue your journey?"
- At waypoint: "Rest here to restore morale?"

---

## Line Count Comparison

### Before (Status format):
- ~30+ lines with chad details
- ~25 lines without chad details

### After (Journey format):
- Normal: ~12-14 lines
- With flash: ~14-16 lines
- At waypoint: ~15-17 lines
- With warnings: ~13-15 lines

**Result:** ~50% reduction in content length

---

## Examples

### Example 1: Normal Day
```
🚀 DAY 42 — Journey to Uranus

💬 "We've got this!" — Jordan (Captain)

Progress to Uranus: [████████████░░░░░░░░] 30%

🚀 Velocity: 20M km/day (1×) | 📏 Traveled: 840M km
🎯 Destination: 1,960M km (~98 days)
🛰️ Next Stop: Ceres Station - 120M km (~6 days)

⛽ Fuel: 245 units (12 days)  |  🍖 Food: 180 units (12 days)
🛡️ Hull: 78%  |  👥 Chad: 5/5  |  😊 Morale: 65%

Ready to continue your journey?
```

### Example 2: At Waypoint with Speed Boost
```
🚀 DAY 25 — Journey to Uranus

💬 "Rest and resupply, here we come!" — Taylor (Navigator)

═══════════════════════════
🛰️ ARRIVED: CERES STATION
═══════════════════════════

Progress to Uranus: [██████░░░░░░░░░░░░░░] 15%

🚀 Velocity: 40M km/day (⚡2×) | 📏 Traveled: 414M km
🎯 Destination: 2,386M km (~60 days)
🛰️ Current Location: Ceres Station

⛽ Fuel: 245 units (6 days)  |  🍖 Food: 180 units (6 days)
🛡️ Hull: 78%  |  👥 Chad: 5/5  |  😊 Morale: 65%

Rest here to restore morale?
```

### Example 3: With Flash Message and Warning
```
🚀 DAY 68 — Journey to Uranus

💬 "We'll figure this out, we always do!" — Jordan (Captain)

✅ Asteroid field navigated safely!
Lost: ⛽ -10 fuel

Progress to Uranus: [████████████████░░░░] 60%

🚀 Velocity: 20M km/day (1×) | 📏 Traveled: 1,680M km
🎯 Destination: 1,120M km (~56 days)
🛰️ Next Stop: Titan Colony - 260M km (~13 days)

⚠️ WARNING: Fuel critically low (4 days)!
⛽ Fuel: 80 units (4 days)  |  🍖 Food: 180 units (12 days)
🛡️ Hull: 78%  |  👥 Chad: 5/5  |  😊 Morale: 65%

Ready to continue your journey?
```

---

## Next Steps

### Status View Redesign
Status view still uses old `formatStatus()` method. Next phase:
1. Design Status view format (chad-focused, detailed)
2. Update `formatStatus()` or create `formatDetailedStatus()`
3. Wire into ViewManager

### Testing Checklist
- [ ] Journey view displays correctly in all states
- [ ] Progress bar fills correctly (0-100%)
- [ ] Velocity shows correct multiplier
- [ ] Days remaining calculations accurate
- [ ] Next waypoint detection works
- [ ] Current location shows at waypoints
- [ ] Critical warnings trigger at correct thresholds
- [ ] Context-aware footer changes correctly
- [ ] Flash messages display properly
- [ ] Waypoint banner shows at stations

---

## Files Modified

1. `/classes/GameEngine.php`
   - Added `formatJourney()` method
   - Added `getNextWaypoint()` helper
   - Added `getCriticalResourceWarnings()` helper

2. `/classes/ViewManager.php`
   - Updated `renderJourneyView()` to use new formatter

3. `/docs/JOURNEY_VIEW_IMPLEMENTATION.md` (this file)

---

**Status:** ✅ IMPLEMENTED
**Date:** 2026-02-27
**Next:** Status view redesign

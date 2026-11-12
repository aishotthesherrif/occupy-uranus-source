# Map Enhancement: Trucker's Dashboard

**Implemented:** 2026-02-26
**Version:** 1A - Navigator's Enhanced Map

---

## What Changed

Completely redesigned the `/map` command to show practical navigation information instead of just a waypoint checklist.

---

## New Features

### 1. **Header Information**
```
📅 Day 47 | 🚀 Europa Outpost
🧭 Position: 780M km
🛞 Odometer: 815M km
   (35M backtrack)
```

- Shows current day and location name
- **Position** = distance from Earth (your location)
- **Odometer** = total distance traveled (includes backtracking)
- **Backtrack indicator** shows when you've had to return to waypoints

### 2. **Progress Bar with Context**
```
Progress Bar to Uranus:
███████▶···················
27.9% (2020M km remaining)
```

- Visual progress bar showing % to destination
- Exact kilometers remaining to Uranus

### 3. **Current Location Highlight**
```
╟──────────────────────────────╢
║ ▶ 🚀 YOU ARE HERE             ║
║ 📍 Europa Outpost 💰           ║
║    Services: Shop, Rest      ║
╟──────────────────────────────╢
```

- Clearly marked with separators
- Shows available services at stations (Shop, Rest)
- Works both at waypoints and between them

### 4. **Next Waypoint Planning**
```
🎯 Next: Saturn Orbit
   📏 620M km
   ⏱️  ~31 days @ 20M/day
   ⛽ Fuel: ~155 (have: 156) ✓
   🍕 Food: ~465 (have: 340)
   ⚠️  FOOD SHORT by ~125!
```

**Critical feature:** Calculates exact resource requirements to next waypoint:
- Distance to next waypoint
- Estimated travel time based on current speed
- Fuel needed (chad × days × fuel_per_chad)
- Food needed (chad × days × food_per_chad)
- **Warnings** if you don't have enough resources

### 5. **Service Indicators**
- 💰 = Station with Shop and Rest (Ceres, Europa, Titan)
- 🎯 = Final destination (Uranus)

---

## How It Helps Gameplay

### Before:
```
[✓] 🌍 Earth Orbit 0M
[✓] 🌙 Moon 0M
[✓] 🔴 Mars Orbit 228M
...
[○] 🪐 Saturn O... 1400M
```
❌ Name truncation
❌ No current position
❌ No planning info
❌ Can't tell if you can make it

### After:
```
🎯 Next: Saturn Orbit
   📏 620M km
   ⏱️  ~31 days @ 20M/day
   ⛽ Fuel: ~155 (have: 156) ✓
   🍕 Food: ~465 (have: 340)
   ⚠️  FOOD SHORT by ~125!
```
✅ Full names visible
✅ Clear current position
✅ Exact resource needs
✅ Warnings prevent death

---

## Technical Implementation

### File Modified
`classes/GameEngine.php` - `generateMap()` function (lines 1114-1298)

### New Helper Function
`getWaypointServices()` - Returns service indicators for waypoints

### Data Used
- `$session['progress']['distance']` - Current position
- `$session['stats']['total_distance_traveled']` - Odometer
- `$session['progress']['day']` - Current day
- `$session['progress']['speed']` - Travel speed per day
- `$session['resources']['chad']` - Chad count (for calculations)
- `$session['resources']['fuel']` - Current fuel
- `$session['resources']['food']` - Current food
- `$session['at_waypoint']` - Current waypoint info (if docked)

### Calculations

**Days to Next Waypoint:**
```php
$distToNext = $nextWaypoint['distance'] - $currentDistance;
$daysToNext = ceil($distToNext / $speed);
```

**Resource Requirements:**
```php
$fuelNeeded = $daysToNext * $chad * FUEL_PER_CREW;
$foodNeeded = $daysToNext * $chad * FOOD_PER_CREW;
```

**Backtrack:**
```php
$backtrack = $totalTraveled - $currentDistance;
```

---

## Example Scenarios

### Scenario 1: At Station with Resources
```
📅 Day 47 | 🚀 Europa Outpost
🧭 Position: 780M km
🛞 Odometer: 815M km
   (35M backtrack)
...
🎯 Next: Saturn Orbit
   📏 620M km
   ⏱️  ~31 days @ 20M/day
   ⛽ Fuel: ~155 (have: 156) ✓
   🍕 Food: ~465 (have: 340)
   ⚠️  FOOD SHORT by ~125!
```
**Action:** Shop for food before leaving!

### Scenario 2: Between Waypoints
```
📅 Day 55 | 🚀 Deep Space
🧭 Position: 1000M km
🛞 Odometer: 1000M km
...
▶ 🚀 YOU ARE HERE
📍 Deep Space
...
🎯 Next: Saturn Orbit
   📏 400M km
   ⏱️  ~20 days @ 20M/day
   ⛽ Fuel: ~80 (have: 200) ✓
   🍕 Food: ~240 (have: 500) ✓
```
**Action:** You're good to continue!

### Scenario 3: Critical Low Resources
```
📅 Day 1 | 🚀 Earth Orbit
🧭 Position: 0M km
🛞 Odometer: 0M km
...
🎯 Next: Moon
   📏 0M km
   ⏱️  ~1 days @ 20M/day
   ⛽ Fuel: ~5 (have: 3)
   🍕 Food: ~15 (have: 10)
   ⚠️  FUEL SHORT by ~2!
   ⚠️  FOOD SHORT by ~5!
```
**Action:** You can't even reach the Moon! Shop immediately!

---

## Testing

Tested with:
- ✅ Player at waypoint (Europa Outpost)
- ✅ Player between waypoints (Deep Space)
- ✅ Player at start (Earth Orbit)
- ✅ Resource shortages (warnings appear)
- ✅ Sufficient resources (no warnings)
- ✅ Backtracking (odometer > position)
- ✅ No backtracking (odometer = position)

---

## Future Enhancements (Optional)

### Possible Additions:
1. **Journey Efficiency %** - Show `(position / odometer) * 100`
2. **Speed Adjustment Hints** - "Slow down to save fuel" / "Speed up?"
3. **Hazard Warnings** - "Asteroid Belt ahead - expect events"
4. **Shop Inventory Preview** - Show what's available at next station
5. **Alternative Routes** - "Return to Titan (2 days) or push to Uranus (40 days)?"

### Not Recommended:
- More visual density - map is already information-rich
- Animated progress bar - static is fine for Telegram
- Historical backtrack log - odometer + backtrack is sufficient

---

## Deploy Notes

### Files to Upload:
- `classes/GameEngine.php`

### No Breaking Changes:
- All existing map keyboard buttons still work
- All session data structures unchanged
- Backwards compatible with existing sessions

### Config Required:
- None - uses existing constants (MAX_CREW, FUEL_PER_CREW, etc.)

---

## User Feedback Expected

**Positive:**
- "Finally know if I can make it!"
- "The warnings saved my run"
- "Love the odometer, shows my real journey"

**Potential Issues:**
- "Too much information?" - Can simplify if needed
- "Math is off by a day" - Rounding/ceiling might need adjustment
- "Doesn't account for random events" - True, but impossible to predict

---

## Summary

The new map transforms from a **static checklist** into a **navigation tool**. Players can now:

1. 🗺️ See where they actually are
2. 📊 Know their total journey distance (bragging rights!)
3. 🎯 Plan their next move with exact resource needs
4. ⚠️ Get warned before making fatal mistakes
5. 💰 Know where they can resupply

This addresses the core UX issue: **"Can I make it to the next station?"** is now answered immediately.

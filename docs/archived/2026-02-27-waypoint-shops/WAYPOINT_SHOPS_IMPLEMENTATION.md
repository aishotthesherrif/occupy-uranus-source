# All Waypoints as Shops Implementation

**Date:** 2026-02-27
**Status:** Complete

---

## Summary

Expanded the game so that **all waypoints** (not just the 3 stations) now offer:
- Stop/skip choice upon arrival
- Shop access with same inventory and pricing
- Gravitational assist mechanics (2× speed boost for skipping)

---

## Requirements Met

1. ✅ All waypoints have same shop and rest options
2. ✅ All waypoints offer "Stop for Supplies" and "Skip for Gravitational Boost"
3. ✅ Choice appears automatically at every waypoint when reached
4. ✅ Waypoint types preserved for flavor (planet, landmark, hazard, station, destination)
5. ✅ Removed station-specific morale arrival effects
6. ✅ No game balance changes (same prices, inventory, mechanics)
7. ✅ Uranus (destination) still triggers instant victory, no choice
8. ✅ Middle scope: every waypoint gets shop access with same inventory

---

## Waypoints Now With Shops

Previously only 3 stations had shops:
- Ceres Station
- Europa Outpost
- Titan Colony

Now **10 waypoints** have stop/skip choices and shops:
1. Earth Orbit (planet) - 0 km
2. Moon (landmark) - 384,400 km
3. Mars Orbit (planet) - 228M km
4. Asteroid Belt (hazard) - 400M km
5. Ceres Station (station) - 414M km
6. Jupiter Orbit (planet) - 778M km
7. Europa Outpost (station) - 780M km
8. Saturn Orbit (planet) - 1.4B km
9. Titan Colony (station) - 1.42B km
10. Deep Space (hazard) - 2B km

**Uranus Orbit** (destination, 2.8B km) still triggers instant victory, no choice.

---

## Technical Changes

### 1. ActionManager.php
**Changed waypoint detection logic:**
```php
// OLD: Only stations triggered choice
if ($waypoint['type'] === 'station')

// NEW: All waypoints except destination trigger choice
if ($waypoint['type'] !== 'destination')
```

### 2. GameEngine.php

**Removed station-only morale boost:**
```php
// REMOVED (lines 234-238)
if ($waypoint['type'] === 'station') {
    $session['resources']['morale'] += 10;
    $message .= "😊 Morale increased!\n\n";
}
```

**Updated messaging to be waypoint-generic:**
- `formatStationChoice()`: "Station Detected!" → "Waypoint Reached!"
- `getStationChoiceKeyboard()`: "Continue Past Station" → "Skip Waypoint"
- Stop message: "Shop for supplies" → "Shop for supplies and rest"

**Removed shop image type check:**
```php
// OLD: Only show waypoint images for stations
if ($waypoint['type'] === 'station')

// NEW: Show waypoint images for all waypoints
if ($waypoint !== null)
```

### 3. uranus_game_bot.php

**Updated handleStationCallback() messages:**
- "station arrival pending" → "waypoint arrival pending"
- "Docking at {station}" → "Stopping at {waypoint}"
- "stopped at the station" → "stopped at this location"
- "Bypassing {station}" → "Bypassing {waypoint}"
- "stop at another station" → "stop at another waypoint"

### 4. waypoints.json

**Added `id` field to all waypoints:**
```json
{
  "name": "Moon",
  "id": "moon",
  "type": "landmark",
  ...
}
```

Previously only stations had `id` fields. Now all 11 waypoints have short, URL-safe IDs for callback data.

---

## Game Mechanics Impact

### Gravitational Assist

**No changes to mechanics**, just more opportunities to use it:

- Skip waypoint → gain/maintain 2× speed boost
- Stop at waypoint → lose speed boost (reset to 1×)
- Boost persists across days until next stop

**Before:** 3 opportunities to skip (Ceres, Europa, Titan)
**After:** 10 opportunities to skip (all waypoints except Earth Orbit and Uranus)

### Shop Access

**No changes to shop inventory or pricing:**
- Fuel: 100 units for 100 credits (1× multiplier)
- Food: 50 units for 50 credits
- Repair: +20 hull for 200 credits
- Prices scale with difficulty (easy: 0.7×, normal: 1×, hard: 1.5×)

**Before:** 3 shop opportunities (Ceres, Europa, Titan)
**After:** 10 shop opportunities (all waypoints except Uranus)

### Morale

**Removed:** +10 morale bonus for arriving at stations
**Rationale:** Player can rest at any waypoint now, so arrival bonus is redundant

---

## Behavior Changes

### Player Experience

1. **More frequent choices**: Every ~20M km of travel triggers a waypoint choice
2. **Strategic depth**: More opportunities to trade speed vs supplies
3. **Easier survival**: 10 resupply points instead of 3
4. **Faster completion**: Can skip all waypoints for permanent 2× speed

### Victory Condition

**Unchanged:**
- Reaching distance ≥ 2.8B km triggers instant victory
- Uranus waypoint never shows stop/skip choice
- Victory check happens before waypoint detection in `advanceDay()`

---

## Session State

### Status Values

**No changes to status flow:**
- `'pre_launch'` → Launch → `'in_space'`
- `'in_space'` → Reach waypoint → stop → `'at_waypoint'`
- `'at_waypoint'` → Continue → `'in_space'`

### Fields Used

- `status`: Current game phase
- `at_waypoint`: Waypoint data when physically at a waypoint (set in `advanceDay()`)
- `pending_station_arrival`: Waypoint data when presenting stop/skip choice
- `progress.speed_multiplier`: 1.0 (normal) or 2.0 (boosted)

---

## Testing Notes

### Test Cases

1. ✅ Reach Moon → see stop/skip choice
2. ✅ Stop at Moon → enter shop, see supplies
3. ✅ Skip Moon → gain 2× speed boost
4. ✅ Reach Mars with boost → choice shows "maintain speed boost"
5. ✅ Stop at Mars → lose boost, enter shop
6. ✅ Reach Uranus → instant victory, no choice
7. ✅ Shop at any waypoint → same inventory/prices
8. ✅ Rest at any waypoint → same mechanics

### Edge Cases

- **Earth Orbit (distance 0)**: Never reached during gameplay (starting point)
- **Callback data length**: All waypoint IDs under 64-byte limit
- **Old sessions**: `speed_multiplier` initialized to 1.0 if missing
- **Missing waypoint ID**: Falls back to name in callback data

---

## Backward Compatibility

### Session Migration

**Automatic and transparent:**
- Old sessions continue working with no data loss
- `speed_multiplier` defaults to 1.0 if missing
- No session schema changes required

### Gameplay Changes

**Easier for existing players:**
- More shop opportunities = easier survival
- No negative impact on in-progress journeys
- Players can take advantage immediately

---

## Documentation Updates Needed

The following files reference "3 stations" and should be updated:

- [ ] `CLAUDE.md` - Update station count and mechanics
- [ ] `README.md` - Update feature description
- [ ] `docs/GAME_MECHANICS_REVIEW.md` - Update shop analysis
- [ ] `docs/USERS_GUIDE.md` - Update gameplay instructions

---

## Future Considerations

### Potential Enhancements

1. **Waypoint-specific shops**: Different inventory per waypoint type
   - Planets: cheaper fuel
   - Hazards: cheaper hull repairs
   - Stations: full inventory

2. **Diminishing returns**: Speed boost decay over skips
   - First skip: 2× speed
   - Second skip: 1.75× speed
   - Third skip: 1.5× speed

3. **Stopping costs**: Credits or time penalty for stopping
   - Balances increased shop opportunities
   - Makes skip choice more strategic

4. **Waypoint events**: Special events at specific waypoints
   - Mars: dust storm hazard
   - Jupiter: radiation exposure
   - Deep Space: isolation morale check

---

**Implementation complete.** All changes tested and functional.

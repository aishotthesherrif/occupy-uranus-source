# Journey View - Daily Chad Quote Addition

## Summary
Added daily chad quote to Journey view for flavor and personality. Quote rotates through active chad and uses the same contextual quote system as Status view.

---

## Implementation

### What Was Added
**Location:** `GameEngine::formatJourney()` after header, before flash message

**Format:**
```
💬 "Quote" — Chad Name (Role)
```

**Example:**
```
💬 "We've got this!" — Jordan (Captain)
```

### Chad Selection Logic
```php
$activeChad = $session['resources']['chad'];
$chadIndex = ($day - 1) % $activeChad; // Rotate through active chad
```

**Rotation Pattern:**
- Day 1 → Chad 0 (Captain)
- Day 2 → Chad 1 (Engineer)
- Day 3 → Chad 2 (Navigator)
- Day 4 → Chad 3 (Medic)
- Day 5 → Chad 4 (Specialist)
- Day 6 → Chad 0 (Captain) again...

**Handles Lost Chad:**
- Only rotates through active chad
- If chad = 3, rotates through indices 0, 1, 2
- Automatically adjusts as chad is lost

### Quote Selection
Uses `getChadQuote()` method with same contextual logic as Status view:
- **General** quotes during normal operation
- **Low morale** quotes when morale < 30%
- **Critical resources** quotes when fuel/food < 5 days
- **Near waypoint** quotes within 5 days of station
- **Near victory** quotes within 20 days of Uranus

---

## Examples

### Day 42 - Normal (Captain speaks, good morale)
```
🚀 DAY 42 — Journey to Uranus

💬 "We've got this!" — Jordan (Captain)

Progress to Uranus: [████████████░░░░░░░░] 30%
...
```

### Day 43 - Normal (Engineer speaks)
```
🚀 DAY 43 — Journey to Uranus

💬 "At least we're not dead yet." — Alex (Engineer)

Progress to Uranus: [████████████░░░░░░░░] 30%
...
```

### Day 68 - Critical Resources (Captain speaks, fuel low)
```
🚀 DAY 68 — Journey to Uranus

💬 "We'll figure this out, we always do!" — Jordan (Captain)

Progress to Uranus: [████████████████░░░░] 60%
...

⚠️ WARNING: Fuel critically low (4 days)!
```

### Day 135 - Near Victory (Navigator speaks)
```
🚀 DAY 135 — Journey to Uranus

💬 "Uranus is so close I can taste it!" — Taylor (Navigator)

Progress to Uranus: [███████████████████░] 95%
...
```

---

## Benefits

1. **Adds Personality** - Journey view now has character voice
2. **Contextual Awareness** - Quotes respond to game state
3. **Variety** - Different chad speaks each day
4. **Minimal Impact** - Only adds 1 line to view
5. **Consistency** - Uses same quote system as Status view
6. **Engagement** - Players see chad personalities without opening Status

---

## Technical Details

**Lines Added:** ~15 lines in `formatJourney()`

**Dependencies:**
- `getChadRoster()` - Get chad data
- `getChadQuote()` - Get contextual quote
- `getChadTitle()` - Fallback for role
- Active chad count from session

**Edge Cases Handled:**
- No chad (if somehow all lost) → Skip quote
- Missing chad data → Use fallback names
- Lost chad → Skip in rotation

---

## Files Modified

1. **GameEngine.php**
   - Modified `formatJourney()` to add daily chad quote

2. **JOURNEY_VIEW_IMPLEMENTATION.md**
   - Updated format specification
   - Updated examples

3. **GAME_VIEWS.md**
   - Updated Journey view format
   - Added chad quote to conditionals

4. **JOURNEY_QUOTE_ADDITION.md** (this file)

---

## Testing Checklist

- [ ] Quote appears on Journey view
- [ ] Quote rotates daily (different chad each day)
- [ ] Quote is contextual (changes based on game state)
- [ ] Lost chad are skipped in rotation
- [ ] Format is correct: `💬 "Quote" — Name (Role)`
- [ ] Works with flash messages
- [ ] Works at waypoints
- [ ] Works near victory

---

**Status:** ✅ IMPLEMENTED
**Date:** 2026-02-27
**Feature:** Daily chad quote on Journey view

# Event Result Quote Context Update

**Date:** 2026-02-28
**Change:** Updated event result screen to use contextual quotes

---

## The Problem

Event result screens were showing **random quotes from random contexts** instead of contextual `post_event_success` or `post_event_failure` quotes.

**Before:**
- Event result: Random quote from ANY context (general, low_morale, critical, etc.)
- Journey view (next screen): Contextual post-event quote

**Result:** Inconsistent experience - random quote on event screen, contextual quote on next screen.

---

## The Fix

**Changed:** `processEventChoice()` line 380
```php
// Before:
$quote = $this->getPersonalityQuote($member['trait']);
// Picks random quote from random context

// After:
$quote = $this->getChadQuote($member, $session, $idx);
// Uses contextual post_event_success/failure quote
```

---

## How It Works Now

### Event Flow with Contextual Quotes

1. **Event occurs** → Player makes choice
2. **processEventChoice() runs:**
   - Line 354: Sets `recent_event_outcome` flag ('success' or 'failure')
   - Lines 377-384: 25% chance to show chad quote on event result
   - Quote is now **contextual** (uses post_event_success/failure context)
3. **Player sees event result:**
   - Event outcome message
   - 25% chance: Contextual chad reaction quote
4. **Player clicks Continue:**
   - Journey view shows contextual quote (100% of the time)
   - Different chad (day-based rotation)
5. **Next action:**
   - Flag cleared automatically

---

## Example Scenarios

### Scenario 1: Successful Event (Quote Appears)

**Event:** Derelict Ship → Choose "Board carefully" → Success (gain 50 fuel, 30 food)

**Event Result Screen (25% chance):**
```
🎲 Result:

🎯 YOUR CHOICE: Board the ship carefully

🎁 Jackpot! You found 50 fuel cells and 30 food rations!

💬 Jordan: "See? I told you it would work out!"
```

**Journey View (Next Screen):**
```
🚀 DAY 43 — Journey to Uranus

💬 "That actually worked? Unexpected." — Alex (Engineer)

Progress to Uranus: [████████████░░░░░░░░] 30%
...
```

**Result:** Player sees TWO post-event success quotes from different chads. Reinforces positive outcome.

---

### Scenario 2: Failed Event (Quote Appears)

**Event:** Solar Flare → Choose "Press on" → Failure (lose 20 hull)

**Event Result Screen (25% chance):**
```
🎲 Result:

🎯 YOUR CHOICE: Press on through the radiation

💥 You took damage! Hull integrity decreased by 20%.

💬 Taylor: "Called it. Knew that would go badly."
```

**Journey View (Next Screen):**
```
🚀 DAY 69 — Journey to Uranus

💬 "Of course. Exactly as I predicted." — Morgan (Medic)

Progress to Uranus: [████████████████░░░░] 60%
...
```

**Result:** Player sees TWO post-event failure quotes from different chads. Reinforces negative outcome.

---

### Scenario 3: Event with No Quote

**Event:** Asteroid Field → Navigate → Success

**Event Result Screen (75% chance - no quote):**
```
🎲 Result:

🎯 YOUR CHOICE: Navigate carefully through the field

✅ Success! You navigated safely through the asteroids.
```

**Journey View (Next Screen):**
```
🚀 DAY 85 — Journey to Uranus

💬 "That's what teamwork looks like!" — Jordan (Captain)

Progress to Uranus: [█████████████████░░░] 75%
...
```

**Result:** Player only sees ONE contextual quote on Journey view.

---

## Benefits

### 1. Immediate Contextual Feedback
- Chad reacts to event outcome **immediately** on result screen
- No longer random/disconnected quotes
- Feels responsive and immersive

### 2. Multiple Chad Reactions (25% of time)
- Event result: Random chad's contextual quote
- Journey view: Day-rotation chad's contextual quote
- Reinforces event importance with multiple perspectives
- Shows different personality reactions to same event

### 3. Consistent Experience
- All quotes now use same contextual system
- Event quotes → Journey quotes → Status quotes all contextual
- No more random quote confusion

### 4. Personality Showcase
- 25% chance to see 2 different personalities react to same event
- Example: Optimistic celebrates success, Sarcastic is surprised
- Deepens character personalities

---

## Quote Display Summary

### Journey View
**Frequency:** Every time (100%)
**Chad:** Day-based rotation (Day % chad count)
**Context:** Fully contextual (8 contexts)
**Format:** `💬 "Quote" — Name (Role)`

### Status View
**Frequency:** When opened
**Chad:** All 5 chads
**Context:** Fully contextual (8 contexts)
**Format:** Each chad shows quote + activity

### Event Result View
**Frequency:** 25% chance after events
**Chad:** Random active chad
**Context:** Fully contextual (8 contexts) ✅ **NEW**
**Format:** `💬 Name: "Quote"`

---

## Technical Details

**File Modified:** `classes/GameEngine.php`
**Function:** `processEventChoice()`
**Line:** 380
**Change Type:** Function call replacement

**Old behavior:**
- `getPersonalityQuote()` - Random context selection
- No awareness of event outcome

**New behavior:**
- `getChadQuote()` - Contextual quote selection
- Reads `recent_event_outcome` flag set on line 354
- Uses `post_event_success` or `post_event_failure` context

**Backward compatible:** Yes
- Falls back to 'general' context if post-event quotes missing
- No breaking changes

---

## Context Priority After Events

**When event result shown:**
1. Check `recent_event_outcome` flag → Use post-event context
2. Fall back to other contexts if flag not set

**Priority order:**
1. `post_event_success` (if flag = 'success')
2. `post_event_failure` (if flag = 'failure')
3. `at_waypoint` (if docked)
4. `low_morale` (if morale < 30%)
5. `critical_resources` (if fuel/food < 5 days)
6. `near_victory` (if within 20 days)
7. `near_waypoint` (if within 5 days)
8. `general` (default)

---

## Testing Checklist

### Event Result Screen
- [ ] Event success shows post_event_success quote (when quote appears)
- [ ] Event failure shows post_event_failure quote (when quote appears)
- [ ] Quote appears ~25% of the time
- [ ] Correct chad name shown
- [ ] Quote matches personality trait

### Journey View After Event
- [ ] Shows contextual post-event quote
- [ ] Different chad than event result (usually)
- [ ] Flag clears after next action

### Multiple Quotes
- [ ] When both appear, they're from different chads
- [ ] Both quotes use same context (post_event_success/failure)
- [ ] Personality differences visible

### Edge Cases
- [ ] Works with chad deaths
- [ ] Works with enhanced events (chad_reaction)
- [ ] Falls back to general if post-event quotes missing
- [ ] No errors if chad data incomplete

---

## Statistics

**Lines Changed:** 2
**Functions Modified:** 1 (processEventChoice)
**Impact:** Event result quotes now contextual
**Backward Compatible:** Yes

---

## Before/After Comparison

### Before This Update
- ❌ Event result: Random quote (disconnected)
- ✅ Journey view: Contextual quote
- ✅ Status view: Contextual quote
- **Result:** Inconsistent experience

### After This Update
- ✅ Event result: Contextual quote
- ✅ Journey view: Contextual quote
- ✅ Status view: Contextual quote
- **Result:** Fully consistent contextual experience

---

**Status:** ✅ COMPLETE
**Ready for:** Testing and deployment
**Impact:** Improved immersion, consistent contextual quotes across all views

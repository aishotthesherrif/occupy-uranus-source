# New Quote Contexts Implementation Summary

**Date:** 2026-02-28
**Feature:** Added 3 new contextual quote triggers for chad personality system

---

## New Contexts Added

### 1. `at_waypoint`
**Trigger:** When `status === 'at_waypoint'` (docked at station)
**Priority:** High (after post-event contexts, before low_morale)
**Purpose:** Chad comments on arrival, docking, station atmosphere

**Example quotes needed:**
- Optimistic: Excited to be at station
- Sarcastic: Underwhelmed by station amenities
- Pessimistic: Worries about what could go wrong at station

---

### 2. `post_event_success`
**Trigger:** Right after positive event outcome (gains resources, repairs, etc.)
**Priority:** Highest (shows once, then clears)
**Purpose:** Chad celebrates, acknowledges good luck, relief

**Classification logic:**
```php
// Success if:
- No chad deaths
- Net resource change >= 0
- Hull gained, morale gained
- Credits/fuel/food gained
```

**Example quotes needed:**
- Optimistic: Celebrates success, knew it would work out
- Sarcastic: Surprised things went well for once
- Pessimistic: Suspicious of good fortune, waiting for next disaster

---

### 3. `post_event_failure`
**Trigger:** Right after negative event outcome (losses, damage, chad death)
**Priority:** Highest (shows once, then clears)
**Purpose:** Chad reacts to setback, frustration, damage control

**Classification logic:**
```php
// Failure if:
- Any chad deaths
- Net resource change < 0
- Hull damage, morale loss
- Significant losses
```

**Example quotes needed:**
- Optimistic: Still hopeful despite setback, could be worse
- Sarcastic: "Told you so" energy
- Pessimistic: Confirmation of worldview, we're doomed

---

## Context Priority Order (Updated)

**Highest → Lowest:**

1. **`post_event_success`** (temporary, clears after display)
2. **`post_event_failure`** (temporary, clears after display)
3. **`at_waypoint`** (while docked)
4. **`low_morale`** (< 30%)
5. **`critical_resources`** (< 5 days fuel/food)
6. **`near_victory`** (<= 20 days to Uranus)
7. **`near_waypoint`** (<= 5 days to next station)
8. **`general`** (default)

---

## Implementation Details

### Code Changes

**File:** `classes/GameEngine.php`

**Modified functions:**
1. `getQuoteContext()` - Added 3 new context checks
2. `processEventChoice()` - Classifies and sets `recent_event_outcome`
3. `classifyEventOutcome()` - NEW - Analyzes effects to determine success/failure
4. `advanceDay()` - Clears `recent_event_outcome` flag
5. `huntResources()` - Clears `recent_event_outcome` flag
6. `rest()` - Clears `recent_event_outcome` flag

**Lines added:** ~60 lines

---

### Event Outcome Classification Logic

```php
private function classifyEventOutcome(array $effects, array $deathMessages): string {
    // Chad death = always failure
    if (!empty($deathMessages) || (isset($effects['chad']) && $effects['chad'] < 0)) {
        return 'failure';
    }

    // Calculate net resource change with weights
    $netChange = 0;

    // Positive: +fuel/10, +food/20, +credits/100, +hull, +morale
    // Negative: -fuel/10, -food/20, -credits/100, -hull*1.5, -morale

    return $netChange >= 0 ? 'success' : 'failure';
}
```

**Weighted effects:**
- Hull damage: 1.5× weight (more serious)
- Fuel: 0.1× weight (lots of fuel, small numbers)
- Food: 0.05× weight (lots of food, small numbers)
- Credits: 0.01× weight (thousands of credits)
- Morale: 1× weight (direct value)

---

### Session Flag Management

**New session field:** `recent_event_outcome`

**Lifecycle:**
1. **Set:** In `processEventChoice()` right after applying effects
2. **Read:** In `getQuoteContext()` with highest priority
3. **Clear:** At start of `advanceDay()`, `huntResources()`, `rest()`

**Why this works:**
- Event resolved → flag set → result shown with contextual quote
- Player sees Journey/Status with post-event quote
- Next action → flag cleared → back to normal contexts
- Shows exactly once per event

---

## Data File Changes Required

**File:** `data/chad_personalities.json`

**Need to add for ALL 20 personality traits:**

```json
{
  "Optimistic": {
    "general": [...existing...],
    "low_morale": [...existing...],
    "critical_resources": [...existing...],
    "near_waypoint": [...existing...],
    "near_victory": [...existing...],

    // NEW CONTEXTS
    "at_waypoint": [
      "Quote 1",
      "Quote 2",
      "Quote 3"
    ],
    "post_event_success": [
      "Quote 1",
      "Quote 2",
      "Quote 3"
    ],
    "post_event_failure": [
      "Quote 1",
      "Quote 2",
      "Quote 3"
    ]
  }
}
```

**Total new quotes needed:**
- 20 traits × 3 contexts × 3 quotes = **180 new quotes**

---

## Personality Traits Reference

**20 traits in chad_personalities.json:**

1. Optimistic
2. Sarcastic
3. Pessimistic
4. Brave
5. Paranoid
6. Methodical
7. Enthusiastic
8. Cautious
9. Rebellious
10. Loyal
11. Curious
12. Adventurous
13. Analytical
14. Impulsive
15. Diplomatic
16. Stubborn
17. Creative
18. Logical
19. Emotional
20. Detached

---

## Quote Writing Guidelines

**Follow CREATIVE_VOICE_GUIDE.md principles:**

### At Waypoint Quotes
- **Theme:** Observations about stations, docking, supplies
- **Tone:** Matter-of-fact, mundane bureaucracy
- **Example:** "A station. How thrilling." (Sarcastic)
- **Example:** "Finally, a place to resupply!" (Optimistic)

### Post-Event Success Quotes
- **Theme:** Relief, celebration, acknowledgment of good luck
- **Tone:** Varies by personality (hopeful, surprised, suspicious)
- **Example:** "We did it! I knew we would!" (Optimistic)
- **Example:** "That actually worked? Unexpected." (Sarcastic)

### Post-Event Failure Quotes
- **Theme:** Frustration, damage assessment, resignation
- **Tone:** Varies by personality (still hopeful, cynical, fatalistic)
- **Example:** "Could be worse... I think." (Optimistic)
- **Example:** "Called it. Knew that would go badly." (Sarcastic)

### Length
- 4-8 words recommended (shorter for reactions)
- Can be slightly longer if needed for personality
- Punctuation matters (! for enthusiasm, . for deadpan)

### Style
- **Deadpan delivery** for most quotes
- **No explaining** - just state the observation
- **Personality consistent** across all contexts
- **No hope or optimism** except for Optimistic trait

---

## Testing Checklist

### Context Triggering
- [ ] `at_waypoint` triggers when docked at station
- [ ] `post_event_success` triggers after positive event
- [ ] `post_event_failure` triggers after negative event
- [ ] Post-event contexts clear after one action
- [ ] Priority order works correctly

### Quote Display
- [ ] Correct personality trait quotes show
- [ ] Quotes rotate daily (hash-based)
- [ ] Contextual quotes match game state
- [ ] Journey view shows post-event quotes
- [ ] Status view shows post-event quotes

### Edge Cases
- [ ] No errors if quotes missing for context
- [ ] Falls back to 'general' if context quotes don't exist
- [ ] Works with all 20 personality traits
- [ ] Handles chad death events correctly

---

## Example Scenarios

### Scenario 1: Successful Event → Dock at Station

**Day 42:** Event "Derelict Ship" → Choose "Board carefully" → Gain 50 fuel, 30 food
- **Context:** `post_event_success`
- **Quote:** "That actually worked? Unexpected." (Sarcastic chad)

**Day 43:** Continue journey → Advance to station
- **Context:** `at_waypoint` (flag cleared, now at station)
- **Quote:** "Finally, a place to resupply!" (Optimistic chad)

---

### Scenario 2: Failed Event → Low Morale

**Day 68:** Event "Solar Flare" → Choose "Press on" → Lose 20 hull, -10 morale
- **Context:** `post_event_failure`
- **Quote:** "Called it. Knew that would go badly." (Sarcastic chad)

**Day 69:** Continue journey → Morale now at 28%
- **Context:** `low_morale` (flag cleared, now checking morale)
- **Quote:** "Morale's low? Gee, wonder why." (Sarcastic chad)

---

## Benefits

### Player Experience
1. **Immediate feedback** - Chad reacts to events right away
2. **Personality depth** - More situations show character traits
3. **Immersion** - Chad feels responsive to game state
4. **Variety** - 3 more contexts × 20 traits = 60 new quote pools

### Technical Quality
1. **Clean implementation** - No breaking changes
2. **Backward compatible** - Falls back to existing contexts
3. **Deterministic** - Same outcomes = same quotes (testable)
4. **Self-cleaning** - Flag clears automatically

---

## Next Steps

1. **Write 180 new quotes** (20 traits × 3 contexts × 3 quotes each)
2. **Update chad_personalities.json** with new contexts
3. **Test each personality trait** in all 3 new contexts
4. **Verify priority order** works correctly
5. **Check fallback behavior** when quotes missing

---

**Status:** ✅ Code complete, ready for quote content
**Files Modified:** 1 (GameEngine.php)
**Files To Update:** 1 (chad_personalities.json)
**Testing:** Required after quotes added

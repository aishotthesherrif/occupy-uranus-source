# Status View - Quote Rotation System

## Current System
- Each chad has a personality trait (Optimist, Pragmatist, Cynic, Stoic, Joker)
- chad_personalities.json has 20 traits with 5 quotes each
- Quotes are currently generic, not contextual

---

## Quote Rotation Approaches

### Option 1: **Day-Based Rotation** (Simplest)
**How it works:** Quote index = day % 5 (cycles through 5 quotes every 5 days)

**Pros:**
- Super simple to implement
- Deterministic (same quote on day 5 every playthrough)
- No random state needed

**Cons:**
- Not very organic
- Same rotation pattern every game
- No response to game state

**Implementation:**
```php
$quoteIndex = ($session['progress']['day'] - 1) % 5;
$quote = $chadMember['quotes'][$quoteIndex];
```

---

### Option 2: **Session-Seeded Random** (Consistent per game)
**How it works:** Use session ID as seed for random quote selection

**Pros:**
- Different quotes each playthrough
- Still deterministic within a session
- Feels more natural

**Cons:**
- Same quotes for the session duration
- No variety within one playthrough

**Implementation:**
```php
$seed = crc32($session['session_id'] . $chadIndex . floor($day / 3));
mt_srand($seed);
$quoteIndex = mt_rand(0, 4);
$quote = $chadMember['quotes'][$quoteIndex];
```

---

### Option 3: **Day + Chad Index Hash** (Rotating)
**How it works:** Hash(day + chad_index) % 5 for quote selection

**Pros:**
- Different quote each day
- Different per chad
- No random state needed
- Repeatable/testable

**Cons:**
- Not truly random
- Pattern might emerge

**Implementation:**
```php
$hash = abs(crc32($session['progress']['day'] . $chadIndex));
$quoteIndex = $hash % 5;
$quote = $chadMember['quotes'][$quoteIndex];
```

---

### Option 4: **Contextual Quote Categories** ⭐ Recommended
**How it works:** Different quote pools based on game state

**Quote Categories:**
1. **General** - Normal operation quotes (5 per trait)
2. **Low Morale** - When morale < 40% (3 per trait)
3. **Critical Resources** - When fuel/food < 10 days (3 per trait)
4. **Near Waypoint** - Within 5 days of station (3 per trait)
5. **Near Victory** - Within 20 days of Uranus (3 per trait)

**Pros:**
- Quotes respond to game state
- More immersive/reactive
- Adds depth without repetition
- Players feel chad is "aware"

**Cons:**
- More quotes to write
- More complex logic
- Larger data file

**Implementation:**
```php
// Determine quote category
if ($session['resources']['morale'] < 40) {
    $category = 'low_morale';
} elseif ($fuelDays < 10 || $foodDays < 10) {
    $category = 'critical_resources';
} elseif ($daysToNextWaypoint < 5) {
    $category = 'near_waypoint';
} elseif ($daysToUranus < 20) {
    $category = 'near_victory';
} else {
    $category = 'general';
}

// Select quote from category with day-based rotation
$quotes = $chadMember['quotes'][$category];
$quoteIndex = ($session['progress']['day'] + $chadIndex) % count($quotes);
$quote = $quotes[$quoteIndex];
```

---

### Option 5: **Hybrid (Contextual + Rotating)** ⭐⭐ BEST
**How it works:** Combine contextual categories with rotation within category

**Structure:**
```json
{
  "Optimistic": {
    "general": [
      "Everything will work out fine!",
      "We've got this!",
      "The universe is on our side!",
      "Every challenge is an opportunity!",
      "I believe in our mission!"
    ],
    "low_morale": [
      "Don't give up now, we're almost there!",
      "I know it's hard, but we can do this!",
      "Tomorrow will be better!"
    ],
    "critical_resources": [
      "We'll figure this out, we always do!",
      "A little scarcity builds character!",
      "I'm sure we'll find supplies soon!"
    ],
    "near_waypoint": [
      "I can't wait to dock at the station!",
      "Almost there, team!",
      "Rest and resupply, here we come!"
    ],
    "near_victory": [
      "Uranus is so close I can taste it!",
      "We're about to make history!",
      "I knew we'd make it all along!"
    ]
  }
}
```

**Quote Selection:**
1. Determine context category
2. Get quotes for that category
3. Rotate through quotes using hash(day + chadIndex)

**Pros:**
- Best of all worlds
- Contextual AND varied
- Chad feels responsive to situation
- Still rotates to avoid repetition

**Cons:**
- Requires writing ~15 quotes per trait (75 total per trait)
- Larger data structure

---

## Recommended Approach: **Option 5 (Hybrid)**

### Phase 1: Start with Option 3 (Simple Rotation)
- Use existing 5 general quotes per trait
- Implement: `hash(day + chadIndex) % 5`
- Gets feature working immediately

### Phase 2: Add Contextual Categories
- Add low_morale quotes (3 per trait)
- Add critical_resources quotes (3 per trait)
- Keep general as fallback (5 per trait)

### Phase 3: Full Context System
- Add near_waypoint quotes
- Add near_victory quotes
- Polish personality variations

---

## Example Quote Selection Logic

```php
/**
 * Get contextual quote for chad based on game state.
 *
 * @param array $chadMember Chad data with trait
 * @param array $session Game session
 * @param int $chadIndex Chad position (0-4)
 * @return string Selected quote
 */
private function getChadQuote(array $chadMember, array $session, int $chadIndex): string {
    $trait = $chadMember['trait'] ?? 'Optimistic';
    $day = $session['progress']['day'];

    // Determine context
    $context = $this->getQuoteContext($session);

    // Load personality quotes
    $personalities = $this->loadChadPersonalities();
    $quotes = $personalities['personality_quotes'][$trait][$context] ?? [];

    // Fallback to general if context quotes don't exist
    if (empty($quotes)) {
        $quotes = $personalities['personality_quotes'][$trait]['general'] ?? [];
    }

    // If still empty, use trait quotes (backward compatibility)
    if (empty($quotes)) {
        $quotes = $personalities['personality_quotes'][$trait] ?? [
            "I'm just doing my job."
        ];
    }

    // Select quote using deterministic hash
    $hash = abs(crc32($day . '-' . $chadIndex . '-' . $trait));
    $quoteIndex = $hash % count($quotes);

    return $quotes[$quoteIndex];
}

/**
 * Determine quote context based on game state.
 */
private function getQuoteContext(array $session): string {
    $morale = $session['resources']['morale'];
    $chad = $session['resources']['chad'];

    // Calculate days of resources remaining
    $fuelDays = $this->calculateDaysRemaining('fuel', $session);
    $foodDays = $this->calculateDaysRemaining('food', $session);

    // Calculate proximity to waypoints/victory
    $daysToUranus = $this->calculateDaysToDestination($session);
    $daysToNextWaypoint = $this->calculateDaysToNextWaypoint($session);

    // Priority order (most urgent first)
    if ($morale < 30) {
        return 'low_morale';
    }

    if ($fuelDays < 5 || $foodDays < 5) {
        return 'critical_resources';
    }

    if ($daysToUranus < 20) {
        return 'near_victory';
    }

    if ($daysToNextWaypoint !== null && $daysToNextWaypoint < 5) {
        return 'near_waypoint';
    }

    return 'general';
}
```

---

## Sample Contextual Quotes

### Optimistic Trait Examples:

**General:**
- "Everything will work out fine!"
- "We've got this!"
- "The universe is on our side!"
- "Every challenge is an opportunity!"
- "I believe in our mission!"

**Low Morale:**
- "Don't give up now, we're almost there!"
- "I know it's hard, but we can do this!"
- "Tomorrow will be better, I promise!"

**Critical Resources:**
- "We'll figure this out, we always do!"
- "A little scarcity builds character!"
- "I'm sure we'll find supplies at the next station!"

**Near Waypoint:**
- "I can't wait to dock at the station!"
- "Almost there, team!"
- "Rest and resupply, here we come!"

**Near Victory:**
- "Uranus is so close I can taste it!"
- "We're about to make history!"
- "I knew we'd make it all along!"

---

### Cynic Trait Examples:

**General:**
- "Space is big. Really big."
- "I've seen worse... barely."
- "At least we're not dead yet."
- "Another day, another disaster waiting to happen."
- "Sure, this'll be fine. Probably."

**Low Morale:**
- "Morale's low? Gee, wonder why."
- "At least we're all miserable together."
- "Could be worse. Could be... actually, no, this is pretty bad."

**Critical Resources:**
- "Running out of supplies. Shocking."
- "Who could have seen this coming? Oh wait, I did."
- "Well, this is going exactly as I predicted."

**Near Waypoint:**
- "A station. How thrilling."
- "Let's hope they have supplies and not just more problems."
- "Another stop on our doomed journey."

**Near Victory:**
- "Almost at Uranus. Assuming we survive."
- "We might actually make it. Emphasis on 'might'."
- "Don't celebrate yet. Plenty can still go wrong."

---

## Implementation Checklist

### Phase 1: Simple Rotation (Immediate)
- [ ] Implement hash-based quote selection from existing quotes
- [ ] Wire into formatDetailedStatus()
- [ ] Test with different days/chad

### Phase 2: Add Basic Context (Next)
- [ ] Expand chad_personalities.json with context categories
- [ ] Add low_morale quotes for all 20 traits
- [ ] Add critical_resources quotes for all 20 traits
- [ ] Implement getQuoteContext() helper
- [ ] Update getChadQuote() to use context

### Phase 3: Full Context (Later)
- [ ] Add near_waypoint quotes
- [ ] Add near_victory quotes
- [ ] Add special event-triggered quotes (optional)
- [ ] Polish and balance

---

## Data Structure Changes

### Current (chad_personalities.json):
```json
{
  "personality_quotes": {
    "Optimistic": [
      "Quote 1",
      "Quote 2",
      "Quote 3",
      "Quote 4",
      "Quote 5"
    ]
  }
}
```

### Proposed (contextual):
```json
{
  "personality_quotes": {
    "Optimistic": {
      "general": ["...", "...", "...", "...", "..."],
      "low_morale": ["...", "...", "..."],
      "critical_resources": ["...", "...", "..."],
      "near_waypoint": ["...", "...", "..."],
      "near_victory": ["...", "...", "..."]
    }
  }
}
```

---

## Decision Points

1. **Start with which phase?** Phase 1 (simple), Phase 2 (basic context), or Phase 3 (full)?
2. **Keep backward compatibility?** Support old flat array format?
3. **How many quotes per context?** 3, 5, or more?
4. **Priority order for contexts?** What takes precedence when multiple conditions true?

What do you think? Should we start with Phase 1 (simple rotation of existing quotes) and build up, or jump straight to Phase 2/3 with contextual quotes?

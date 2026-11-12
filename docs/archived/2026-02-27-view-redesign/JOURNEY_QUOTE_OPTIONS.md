# Journey View - Chad Quote Options

## Current Journey View Structure
```
🚀 DAY 42 — Journey to Uranus

[Flash message if present]
[Waypoint banner if at waypoint]

Progress bar...
Resources...
Footer prompt
```

---

## Option A: Captain's Voice (Simple)
**Always show the Captain's quote**

```
🚀 DAY 42 — Journey to Uranus

👨‍✈️ Jordan: "We've got this!"

Progress to Uranus: [████████████░░░░░░░░] 30%
...
```

**Pros:**
- Consistent "voice" of the ship
- Captain is natural spokesperson
- Simple to implement

**Cons:**
- Less variety (only one personality)
- Captain might be lost (needs handling)

---

## Option B: Random Daily Chad Member
**Rotate through different chad each day**

```
🚀 DAY 42 — Journey to Uranus

🔧 Alex: "At least we're not dead yet."

Progress to Uranus: [████████████░░░░░░░░] 30%
...
```

**Pros:**
- Variety - different voice each day
- Shows personality of entire chad
- More engaging

**Cons:**
- Might feel random/disconnected
- No consistency

---

## Option C: Highest Morale Chad Member
**Quote from chad with highest morale/best attitude**

```
🚀 DAY 42 — Journey to Uranus

💬 Jordan (Captain): "Every challenge is an opportunity!"

Progress to Uranus: [████████████░░░░░░░░] 30%
...
```

**Pros:**
- Contextually makes sense (optimistic voice rises)
- Represents chad sentiment
- Dynamic based on game state

**Cons:**
- More complex logic
- Might always be the same person

---

## Option D: Context-Relevant Chad Member
**Different chad speaks based on situation**

- Normal operation → **Captain**
- Low resources → **Engineer** (about ship/supplies)
- Near waypoint → **Navigator** (about destination)
- Low morale → **Medic** (about chad health)
- Special situations → **Specialist** (about discoveries)

```
🚀 DAY 42 — Journey to Uranus

🧭 Taylor (Navigator): "Almost at the station, team!"

Progress to Uranus: [████████████░░░░░░░░] 30%
...
```

**Pros:**
- Feels natural (right person for situation)
- Adds depth to roles
- Contextually appropriate

**Cons:**
- Most complex logic
- Requires role-aware quote selection

---

## Option E: Rotating Position (Day-Based)
**Cycle through chad positions: day % 5**

- Day 1, 6, 11... → Captain
- Day 2, 7, 12... → Engineer
- Day 3, 8, 13... → Navigator
- Day 4, 9, 14... → Medic
- Day 5, 10, 15... → Specialist

```
🚀 DAY 42 — Journey to Uranus

⚕️ Morgan: "Everyone's still breathing."

Progress to Uranus: [████████████░░░░░░░░] 30%
...
```

**Pros:**
- Predictable variety
- Each chad gets equal representation
- Simple to implement

**Cons:**
- Doesn't account for lost chad
- No contextual awareness

---

## Formatting Options

### Minimal (just quote):
```
🚀 DAY 42 — Journey to Uranus

"We've got this!" — Jordan

Progress to Uranus: [████████████░░░░░░░░] 30%
```

### With Role:
```
🚀 DAY 42 — Journey to Uranus

👨‍✈️ Jordan (Captain): "We've got this!"

Progress to Uranus: [████████████░░░░░░░░] 30%
```

### Boxed:
```
🚀 DAY 42 — Journey to Uranus

┌─────────────────────────────┐
│ 👨‍✈️ Jordan: "We've got this!" │
└─────────────────────────────┘

Progress to Uranus: [████████████░░░░░░░░] 30%
```

### Subtle (smaller):
```
🚀 DAY 42 — Journey to Uranus
💬 "We've got this!" — Jordan (Captain)

Progress to Uranus: [████████████░░░░░░░░] 30%
```

---

## My Recommendation: **Option B + Minimal Format**

**Why:**
- Simple to implement
- Provides variety
- Uses same quote system as Status view
- Not overwhelming

**Implementation:**
```php
// Select chad based on day
$activeChad = $session['resources']['chad'];
$chadIndex = ($day - 1) % $activeChad; // Rotate through active chad

// Get their quote (same context logic as Status view)
$roster = $this->getChadRoster($session);
$member = $roster[$chadIndex];
$name = $member['name'];
$quote = $this->getChadQuote($member, $session, $chadIndex);

// Format
$quoteDisplay = "💬 \"{$quote}\" — {$name}\n\n";
```

**Result:**
```
🚀 DAY 42 — Journey to Uranus

💬 "We've got this!" — Jordan

Progress to Uranus: [████████████░░░░░░░░] 30%
...
```

**Advantages:**
- One line only (keeps Journey concise)
- Daily rotation through chad
- Same contextual quotes as Status view
- Simple and clean

---

## Alternative Recommendation: **Option D (Context-Relevant)**

If you want more depth:

```php
function getJourneyQuoteChad($session, $context) {
    $roster = $this->getChadRoster($session);
    $activeChad = $session['resources']['chad'];

    // Select role based on context
    $targetRole = match($context) {
        'critical_resources' => 'Engineer',
        'near_waypoint' => 'Navigator',
        'low_morale' => 'Medic',
        'near_victory' => 'Specialist',
        default => 'Captain'
    };

    // Find chad with that role
    foreach ($roster as $i => $member) {
        if ($i < $activeChad && $member['role'] === $targetRole) {
            return [$member, $i];
        }
    }

    // Fallback to first active chad
    return [$roster[0], 0];
}
```

---

## Decision Points

1. **Which chad selection method?**
   - A: Always Captain
   - B: Daily rotation through chad (day % active_chad)
   - C: Highest morale
   - D: Context-relevant role
   - E: Fixed position rotation (day % 5)

2. **Formatting style?**
   - Minimal: `💬 "Quote" — Name`
   - With role: `👨‍✈️ Name (Role): "Quote"`
   - Boxed: `┌─ Quote ─┐`
   - Subtle: Small text

3. **Handle lost chad?**
   - Skip lost chad in rotation
   - Show memorial quote if Captain is lost
   - Always fall back to first active chad

What's your preference?

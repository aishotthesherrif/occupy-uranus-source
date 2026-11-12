# Game Mechanics - Reality Check

**Date:** 2026-02-28
**Critical Finding:** The entire GAME_MECHANICS_REVIEW.md is describing features that don't exist!

---

## The Problem

The Game Mechanics Review document extensively discusses **Easy/Normal/Hard difficulty levels**, comparing starting resources, event frequencies, and shop prices across three difficulties.

**Reality:** Difficulty selection was **REMOVED on 2026-02-27**.

From `uranus_game_bot.php` lines 41-43:
```php
* DIFFICULTY SELECTION REMOVED (2026-02-27)
* - Removed difficulty selection UI. All games now default to 'normal' difficulty.
*   Players go directly from "New Journey" to the shop. Simplified onboarding flow.
```

From line 1286-1288:
```php
// Create game with normal difficulty (difficulty selection removed)
$difficulty = 'normal';
$session = $this->gameEngine->createNewGame($userId, $difficulty);
```

---

## What Actually Exists

### Single Difficulty Level

**ALL players get:**
- Starting Credits: 1500
- Starting Fuel: 600 (120 days of travel)
- Starting Food: 1750 (116 days for 5 chad)
- Starting Hull: 100%
- Shop Prices: Base (1.0× multiplier)
- Event Frequency: 25% base rate

**No player choice. No difficulty selection screen. Just one game mode.**

---

## Why config.php Still Has getDifficulty()

The `config.php` file (lines 353-400) still defines `easy`, `normal`, and `hard` configurations:

```php
function getDifficulty(string $difficulty): array {
    $difficulties = [
        'easy' => [...],
        'normal' => [...],
        'hard' => [...],
    ];
    return $difficulties[$difficulty];
}
```

**Reasons this exists:**
1. **Legacy code** - Not yet removed
2. **Future flexibility** - Could re-enable difficulty selection
3. **Testing** - Can manually test different difficulties in code
4. **Backend support** - The engine *supports* multiple difficulties, but the UI doesn't expose it

**But players NEVER see or select these options.**

---

## Impact on Documentation

### GAME_MECHANICS_REVIEW.md Sections That Are Misleading

1. **Lines 98-132** - "Starting Resources by Difficulty"
   - Discusses Easy/Normal/Hard
   - **Reality:** Only 'normal' exists for players

2. **Lines 121-140** - "Difficulty Scaling is Excellent"
   - Compares three difficulties
   - **Reality:** No scaling - single difficulty

3. **Lines 354-365** - "Event Frequency by Difficulty"
   - Shows Easy/Normal/Hard rates
   - **Reality:** Only 25% base rate (normal)

4. **Lines 618-634** - Priority Matrix showing "All Waypoints as Shops" with difficulty comparisons
   - **Reality:** One implementation, one difficulty

### Recommendations Section

**Lines 450-472** - Recommendation #1 discusses waypoints with difficulty considerations
**Reality:** This was implemented, but only for the single 'normal' difficulty

---

## What Should the Document Say Instead?

### Correct Framing

The document should describe:
1. **One game mode** - "Normal" (Entrepreneur) difficulty
2. **Actual starting resources** - 1500/600/1750/100%
3. **Single event frequency** - 25% base + morale modifiers
4. **One shop price** - Base multiplier (1.0×)
5. **Game balance** - Analyzed for the one difficulty that exists

### Note About Backend Support

Document could include a note:
> **Developer Note:** The backend supports multiple difficulty levels (easy/normal/hard) via `config.php::getDifficulty()`, but difficulty selection was removed from the UI on 2026-02-27. All players experience 'normal' difficulty. The infrastructure remains for potential future re-enablement.

---

## Corrective Action Needed

1. **Rewrite GAME_MECHANICS_REVIEW.md** to describe single-difficulty game
2. **Remove all Easy/Hard comparisons** unless explicitly noting they're theoretical/backend-only
3. **Update analysis** to focus on balance of the actual game (normal only)
4. **Add prominent note** at top of document about difficulty selection removal
5. **Preserve backend info** in developer notes section if relevant

---

## Conclusion

The current GAME_MECHANICS_REVIEW.md is **fundamentally inaccurate** because it describes a multi-difficulty game that no longer exists in the player-facing implementation.

**Action Required:** Complete rewrite to reflect single-difficulty reality.

---

**Status:** ⚠️ CRITICAL - Document needs major rewrite

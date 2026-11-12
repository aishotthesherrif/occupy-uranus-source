# Game Mechanics Review - Audit Report

**Date:** 2026-02-28
**Auditor:** Claude (verifying against actual code implementation)
**Status:** Critical discrepancies found

---

## Summary

The GAME_MECHANICS_REVIEW.md document contains **significant inaccuracies** that do not match the current code implementation. Several core mechanics descriptions are outdated or incorrect.

---

## Critical Discrepancies

### 1. ❌ Fuel Consumption Rate

**Document Claims (Line 122):**
> Fuel consumption: 5 units/day (5 chad × 1)

**Actual Implementation (`GameEngine.php` line 179):**
```php
$fuelConsumed = FUEL_PER_DAY; // Flat 5 units per day
```

**Reality:** Fuel consumption is **FLAT 5 units per day**, NOT chad-based.
Food is chad-based (chad × 3), but fuel is constant regardless of chad count.

**Impact:** HIGH - This affects strategy understanding. Losing chad does NOT reduce fuel consumption.

---

### 2. ❌ Difficulty Starting Resources - COMPLETELY WRONG

**Document Claims (Lines 98-117):**
```
Easy:   1000 credits, 280 fuel, 420 food, 100% hull
Normal:  600 credits, 156 fuel, 234 food, 85% hull
Hard:    300 credits,  46 fuel,  69 food, 60% hull
```

**Actual Implementation (`config.php` lines 354-391):**
```php
'easy' => [
    'credits'        => 3000,
    'starting_fuel'  => 1200,
    'starting_food'  => 3500,
    'starting_hull'  => 100,
    'price_multiplier' => 0.8,  // 20% discount
],
'normal' => [
    'credits'        => 1500,
    'starting_fuel'  => 600,
    'starting_food'  => 1750,
    'starting_hull'  => 100,
    'price_multiplier' => 1.0,
],
'hard' => [
    'credits'        => 1200,
    'starting_fuel'  => 600,
    'starting_food'  => 1800,
    'starting_hull'  => 70,
    'price_multiplier' => 1.5,  // 50% premium
],
```

**Reality:** Starting resources are **3-10× HIGHER** than documented.

**Impact:** CRITICAL - The entire difficulty balance analysis is based on wrong numbers.

---

### 3. ❌ Event Frequency Rates

**Document Claims (Lines 354-356):**
```
Easy:   20% base
Normal: 30% base
Hard:   45% base
```

**Actual Implementation (`config.php` lines 361, 373, 385):**
```php
'easy'   => ['event_frequency' => 0.15],  // 15%
'normal' => ['event_frequency' => 0.25],  // 25%
'hard'   => ['event_frequency' => 0.35],  // 35%
```

**Reality:** Event rates are **5-10% LOWER** than documented.

**Impact:** MEDIUM - Affects difficulty perception and event encounter calculations.

---

### 4. ❌ Rest Mechanics Description

**Document Claims (Line 54):**
> At waypoint: Rest (costs double food, half fuel, +30 morale, no distance)

**Actual Implementation (`GameEngine.php` lines 404-456):**
```php
public function rest(array $session): array {
    // Advance one day
    $session['progress']['day']++;

    // Consume food (chad eats while resting)
    $foodConsumed = $session['resources']['chad'] * FOOD_PER_CREW;
    $session['resources']['food'] -= $foodConsumed;

    // NO FUEL CONSUMED (ship is docked)

    // Restore hull (+20%)
    $hullRepaired = 20;
    $session['resources']['hull'] = min(MAX_HULL, $session['resources']['hull'] + $hullRepaired);

    // Restore morale (+30%)
    $moraleGained = 30;
    $session['resources']['morale'] = min(MAX_MORALE, $session['resources']['morale'] + $moraleGained);
}
```

**Reality:**
- Food: **Normal consumption** (chad × 3), NOT double
- Fuel: **ZERO consumption**, NOT half
- Hull: **+20% repair** (not mentioned in document)
- Morale: **+30%** (correct)
- Distance: **0** (correct)

**Impact:** HIGH - Rest is much more valuable than documented (no fuel cost, includes hull repair).

---

### 5. ❌ Shop Pricing Clarity

**Document Claims (Line 124):**
> Ratio 1:3 matches shop pricing (fuel costs 100, food costs 50)

**Context Needed:**
The document conflates PRICE with QUANTITY, causing confusion.

**Actual Implementation (`GameEngine.php` lines 809-813, `ViewManager.php` lines 273-274):**
```php
$prices = [
    'fuel'   => 100,  // 100 credits
    'food'   => 50,   // 50 credits
    'repair' => 200,  // 200 credits
];

// Shop buttons (quantities):
'⛽ Buy Fuel (+100)'   // +100 units for 100 credits (×multiplier)
'🍖 Buy Food (+50)'    // +50 units for 50 credits (×multiplier)
'🛡️ Repair Hull (+10)' // +10% hull for 200 credits (×multiplier)
```

**Reality:**
- Fuel: 100 credits → +100 units (1 credit per unit)
- Food: 50 credits → +50 units (1 credit per unit)
- Hull: 200 credits → +10% hull (20 credits per 1%)

**Impact:** LOW - The ratio is correct, but the description could be clearer.

---

### 6. ❌ Difficulty Shop Price Multipliers

**Document Claims (Lines 99, 106, 113):**
```
Easy:   Shop discount 20%
Normal: (implied 1.0)
Hard:   Shop premium +50%
```

**Actual Implementation (`config.php`):**
```php
'easy'   => ['price_multiplier' => 0.8],  // ✓ 20% discount
'normal' => ['price_multiplier' => 1.0],  // ✓ base price
'hard'   => ['price_multiplier' => 1.5],  // ✓ 50% premium
```

**Reality:** These ARE correct in the document. ✅

---

## Minor Discrepancies

### 7. ⚠️ Resource Table Clarity (Lines 86-95)

**Document shows:**
| Resource | Max | Consumption | Replenishment |
|----------|-----|-------------|---------------|
| Fuel | 500 | chad × 1/day | Shop, events |

**Should be:**
| Resource | Max | Consumption | Replenishment |
|----------|-----|-------------|---------------|
| Fuel | 500 | **5/day (flat)** | Shop, events |

---

## Accurate Sections ✅

These sections correctly describe the implementation:

1. **Morale system** (Lines 187-224) - Correct
2. **Victory/Loss conditions** (Lines 368-405) - Correct
3. **Waypoint shops implementation** (Lines 450-472) - Correct (post-2026-02-27)
4. **Telegram UX** (Lines 179-242) - Correct
5. **Event structure** (Lines 309-327) - Correct
6. **Oregon Trail DNA** (Lines 143-176) - Correct

---

## Recommendations

### Priority 1: Fix Critical Errors

1. **Update fuel consumption description** - Change "chad × 1" to "flat 5 per day"
2. **Update all difficulty numbers** - Replace with current config.php values
3. **Fix rest mechanics description** - Correct to "normal food, zero fuel, +20 hull, +30 morale"
4. **Update event frequency rates** - Use current 15%/25%/35%

### Priority 2: Enrich Content

1. **Add hull repair to rest description** - Currently missing
2. **Clarify shop pricing** - Distinguish between credits (price) and units (quantity)
3. **Add shop quantities to resource table** - Show +100 fuel, +50 food, +10% hull per purchase
4. **Update journey time estimates** - Recalculate based on new starting resources

### Priority 3: Verify Secondary Mechanics

Areas not checked in this audit (should be verified):
- Hunting mechanics
- Return to waypoint costs (doc says "2-day cost")
- Gravitational assist specifics (doc says "2× speed boost")
- Chad personality system integration level
- Leaderboard implementation details

---

## Impact Assessment

**Documentation Trust Level:** ⚠️ LOW

The document contains critical errors in core mechanics that would mislead anyone using it for:
- Game balance decisions
- Strategy planning
- Code modifications
- Player guides

**Recommended Action:** Complete rewrite of affected sections using code as source of truth.

---

## Next Steps

1. ✅ Create this audit report
2. ⏭️ Update GAME_MECHANICS_REVIEW.md with corrections
3. ⏭️ Add code references (file:line) to key mechanics
4. ⏭️ Verify secondary mechanics not covered in this audit
5. ⏭️ Add "Last Verified Against Code" timestamp to document

---

**Audit Complete:** 2026-02-28
**Files Audited:** config.php, GameEngine.php, ViewManager.php, uranus_game_bot.php

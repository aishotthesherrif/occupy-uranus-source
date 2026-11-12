# Game Mechanics Review

**Created:** 2026-02-28
**Last Verified Against Code:** 2026-03-01
**Game Version:** Post-difficulty-removal (single-difficulty game)

**Recent Changes:**
- 2026-03-02: Resource standardization pass - fuel/food event values consolidated into defined tiers
- 2026-03-02: Starting resources adjusted (fuel: 450→400, food: 1313→800) for tighter balance
- 2026-03-01: Resource displays standardized to 6 resources: Fuel, Food, Hull, Chad, Morale, Credits
- 2026-03-01: Post-Event View now shows resource impact (before/after comparison)
- 2026-03-01: Base speed increases from 20M to 40M km/day after passing Jupiter waypoint
- 2026-02-28: Added 8 new single-resource hunt events and credit rewards from events

---

## Executive Summary

Occupy Uranus is a **well-balanced, genuinely funny** space parody of Oregon Trail with excellent Telegram integration. The game offers a single, well-tuned difficulty that provides meaningful challenge without being punishing.

**Overall Rating:** ⭐⭐⭐⭐½ (4.5/5)

**Strengths:**
- ✅ Meaningful resource management with real consequences
- ✅ Well-balanced single difficulty (comfortable but not trivial)
- ✅ Hilarious crypto/web3 parody events
- ✅ Strong Oregon Trail DNA with creative space adaptation
- ✅ Clean Telegram UX with inline keyboards
- ✅ Strategic waypoint system (10 shops with stop/skip choices)
- ✅ Local character stories add personality and humor

**Areas for Enhancement:**
- 🔄 Victory feels abrupt after long journey
- 🔄 Morale system could have more impact

---

## Core Gameplay Loop

```
1. PRE-LAUNCH PHASE
   ├─ Receive starting resources
   ├─ Shop for additional supplies (fuel, food, hull repair)
   └─ Launch when ready

2. JOURNEY PHASE (repeats until victory or death)
   ├─ Press "Continue" to advance one day
   ├─ Consume resources:
   │  ├─ Fuel: 5 units/day (flat rate)
   │  ├─ Food: chad × 3 units/day
   │  └─ Travel: 20M km/day base (40M km/day after Jupiter)
   │     └─ ×2 multiplier with gravitational assist (skip waypoint)
   ├─ Random event (~25% chance + morale modifiers)
   │  └─ Choose outcome and apply effects
   ├─ Waypoint encounters:
   │  ├─ Stop/skip choice (10 waypoints)
   │  ├─ Shop (resupply at any waypoint)
   │  └─ Rest (food cost, +20% hull, +30% morale)
   ├─ Check game over (chad=0, fuel=0, food=0, hull=0)
   └─ Check victory (distance ≥ 2.8B km)

3. GAME OVER / VICTORY
   └─ Show stats, leaderboard placement, restart option
```

**Pacing:** Each day takes 1-2 button presses. Journey is ~97 days at base speed (40 before Jupiter + 57 after). Can be reduced to ~48 days with gravitational assist speed boost from skipping waypoints.

---

## Starting Resources

**All Players Receive:**
- **Credits:** 1,500 (for shop purchases)
- **Fuel:** 400 units (80 days of travel)
- **Food:** 800 units (feeds 5 chad for 53 days)
- **Hull:** 100% (full integrity)
- **Chad:** 5 members
- **Morale:** 100%

**Analysis:** Starting supplies cover ~53-80 days of the ~97-day journey (with speed boost after Jupiter). Players MUST actively engage with hunting, shopping, and events to survive. This creates significant resource pressure and makes every decision meaningful. Speed boost after Jupiter (800M km mark) becomes critical for players running low on supplies.

**Shop Prices:** Base rate (1.0× multiplier)
- Fuel: 100 credits → +100 units
- Food: 50 credits → +50 units
- Hull Repair: 200 credits → +10%

---

## Resource Economy

### Resource Table

| Resource | Max | Consumption | Replenishment | Criticality |
|----------|-----|-------------|---------------|-------------|
| **Chad** | 5 | Events only | None (permanent loss) | 🔴 CRITICAL (game over at 0) |
| **Fuel** | 1200 | **5/day (flat)** | Shop (+100), events | 🔴 CRITICAL (game over at 0) |
| **Food** | 3500 | chad × 3/day | Shop (+50), events | 🔴 CRITICAL (game over at 0) |
| **Hull** | 100% | Events only | Shop (+10%), rest (+20%), events | 🔴 CRITICAL (game over at 0) |
| **Morale** | 100% | Events, desertion | Rest (+30%), stations, events | 🟡 MEDIUM (affects event rate) |
| **Credits** | ∞ | Shop only | Events | 🟢 LOW (used at shops) |

**Key Insight:** Fuel consumption is FLAT (5/day), NOT chad-based. Losing chad reduces food needs but not fuel needs.

### Rest Mechanics

**Available:** Only when docked at a station (status = 'at_station')

**Costs:**
- Time: +1 day
- Food: chad × 3 units (normal daily consumption)
- Fuel: ZERO (ship is docked)

**Benefits:**
- Hull: +20% repair
- Morale: +30% boost
- Distance: 0 (no travel)

**Strategic Value:** Very efficient way to restore hull and morale without fuel cost. More valuable than documented in original materials.

---

## Core Mechanics (Code-Verified)

### Travel System

**Base Travel Speed:**
- Before Jupiter: 20M km/day (`config.php` line 281: `TRAVEL_SPEED = 20_000_000`)
- After Jupiter: 40M km/day (2× base speed, automatic at 800M km)

**Speed Multiplier:** 1.0× (normal) or 2.0× (gravitational assist from skipping waypoints)

**Gravitational Assist:**
- Activated by skipping a waypoint
- Persists until stopping at any waypoint
- Doubles current base speed (20M→40M before Jupiter, 40M→80M after Jupiter)
- Creates strategic trade-off: speed vs supplies

**Total Distance:** 2.8 billion km (`config.php` line 279)

**Journey Duration:**
- Base speed (no assists): ~97 days (40 days to Jupiter + 57 days after)
- With gravitational assist (always skip): ~48 days
- Realistic mixed strategy: ~60-75 days

### Fuel Consumption

**Daily Cost:** 5 units flat (`config.php` line 288: `FUEL_PER_DAY = 5`)

**NOT chad-dependent.** This is critical for strategy:
- Losing chad does NOT reduce fuel consumption
- Maximum voyage fuel capacity: 1200 units = 240 days
- Starting fuel: 600 units = 120 days (50% of max)
- Can complete full journey (140 days) and have buffer for events

### Food Consumption

**Daily Cost:** chad × 3 units (`config.php` line 295: `FOOD_PER_CREW = 3`)

**IS chad-dependent:**
- 5 chad: 15 units/day
- 4 chad: 12 units/day (20% reduction)
- 3 chad: 9 units/day (40% reduction)
- Losing chad extends food reserves

**Maximum storage:** 3500 units = 233 days for 5 chad

**Starting food:** 1,750 units = 116 days for 5 chad (50% of max)

### Morale System

**Effects:**

**Critical (≤10%):**
- +20% event frequency
- 15% chance of desertion per day (if chad > 1)
- "CRITICAL MORALE!" warning

**Low (11-30%):**
- +10% event frequency
- Chad discontent messages

**High (≥80%):**
- -5% event frequency
- Chad morale bonuses

**Restoration:**
- Rest at station: +30%
- Reaching station: +10%
- Positive events: varies

**Strategic Impact:** Morale is secondary to fuel/food/hull but can create death spirals at critical levels through increased events and desertion.

---

## Event System

### Event Frequency

**Base Rate:** 25% per day (`config.php` line 373: `event_frequency => 0.25`)

**Morale Modifiers (`GameEngine.php` lines 195-218):**
- Morale ≤10: +20% → 45% total
- Morale 11-30: +10% → 35% total
- Morale ≥80: -5% → 20% total

**Expected Events:**
- Average journey (140 days, mixed morale): ~35-40 events
- High morale journey: ~28-35 events
- Low morale journey: ~49-63 events

### Event Types

**52 total events in `data/events.json`:**

**Comedy Events (~50%):** Crypto parody, Uranus jokes, web3 satire
- "HODL Crisis" - chad diamond hands $TITANICTOKEN
- "Uranus Smell" - hydrogen sulfide confirmed
- "DAO Governance" - smart contracts for every decision

**Survival Events (~35%):** Classic Oregon Trail mechanics
- Asteroid fields (navigate vs. burn fuel)
- Space pirates (pay vs. fight)
- Dysentery (always relevant)
- Derelict ships (board vs. ignore)

**Story Events (~15%):** Non-repeatable progression events
- Unique encounters
- Milestone celebrations

### Event Outcomes

Most events offer 2-3 choices with probabilistic outcomes:

**Risk/Reward Pattern:**
- High-risk choices: 50-70% success, significant reward
- Safe choices: 80-100% success, minor benefit
- Avoid choices: 100% safe, no benefit

**Example - Derelict Ship:**
- Board (risky): 60% gain supplies, 30% damage, 10% nothing
- Ignore (safe): 100% safe, no benefit

---

## Waypoint System

### 8 Waypoints

All non-destination waypoints offer stop/skip choices:
1. Moon (25M km)
2. Mars Base (240M km)
3. Ceres Asteroid Station (450M km)
4. Jupiter Gas Mine (800M km)
5. Saturn Outpost (1.4B km)
6. Deep Space 69 (2.1B km)
7. **Uranus Arrival (2.8B km)** - Destination

**Earth Orbit (0 km)** - Starting point

### Stop/Skip Mechanic

**At each waypoint, choose:**

**🛑 Stop for Supplies:**
- Enter waypoint view
- Access shop
- Can rest
- Lose gravitational assist (speed → 1.0×)

**⏭️ Skip Waypoint:**
- Continue at current speed
- Gain/maintain gravitational assist (speed → 2.0×)
- No shop access
- No rest option

**Strategic Depth:** This creates 6 decision points (excluding starting point and destination) where players balance speed vs supplies.

### Station View

When stopped at a station (after choosing "Stop" at waypoint), features:
- **Local character story** (500-600 chars, Hitchhiker's Guide humor)
- **Characters:** Derek Lansing (Moon), Captain Rosa Velasquez (Mars), etc.
- **Chad status** with morale descriptions
- **Resource days remaining** calculations
- **Buttons:** Shop | Rest, Continue Journey, Status | Map, Main Menu

**Note:** This is distinct from Waypoint View, which only shows the stop/skip decision when approaching.

**Example (Moon):**
> Derek Lansing runs the Moon Gift Shop. His entire inventory is novelty snow globes filled with moon dust. They cost $800 each. He's sold three. Total. He insists this is "disrupting retail."

---

## Oregon Trail DNA

### Mechanics Comparison

| Oregon Trail | Occupy Uranus | Quality |
|-------------|---------------|---------|
| **Resource management** | Fuel, Food, Hull, Morale | ✅ EXCELLENT |
| **Travel pacing** | Continue / Rest / Hunt | ✅ EXCELLENT |
| **Random events** | 35 events, varied outcomes | ✅ EXCELLENT |
| **Party member death** | Chad loss (permanent) | ✅ GOOD |
| **Hunting** | Board derelict ships | ✅ GOOD |
| **Trading posts** | 6 waypoint shops | ✅ EXCELLENT |
| **River crossings** | Asteroid fields, radiation | ✅ EXCELLENT |
| **Milestones** | 8 waypoints (Earth → Uranus) | ✅ EXCELLENT |
| **Death messages** | Named chad deaths | ✅ GOOD |

### Excellent Adaptations

**Fording rivers → Navigating asteroid fields**
- Risk hull damage vs burn fuel to avoid
- Same strategic tension

**Hunting buffalo → Boarding derelict ships**
- Risk trap vs gain supplies
- Probabilistic rewards

**Dysentery → Space dysentery**
- Same mechanic, space-themed
- Perfectly adapted

**Trading posts → Waypoint shops**
- Oregon Trail: Fort Laramie, Fort Bridger, etc.
- Occupy Uranus: 10 waypoints with stop/skip choices
- Improves on original with strategic speed trade-offs

---

## Telegram UX

### Interface Design ✅

**Inline Keyboards:**
- All actions use buttons (no typing)
- Clean layout: 1-2 buttons per row
- Emoji labels for clarity
- Owner-stamped callbacks (prevents hijacking)

**Message Management:**
- Bot deletes previous message before sending new one
- Prevents chat spam
- Each game state = 1 message

**Status Display:**
- Concise resource display: "⛽ Fuel: 450 | 🍖 Food: 1,313"
- Day counter: "📅 Day 42"
- Total KM Travelled (cumulative, includes backtrack)
- KM to Uranus (remaining distance)
- Fits in single message bubble

### Group vs DM Support ✅

**Dual Mode:**
- Play in group chat OR private DM
- Switch mid-game (`/privatedm`, `/backtogroup`)
- Group mode: "👤 GAMER: @username" header identifies owner

**Button Ownership:**
- Only game owner can press buttons
- Prevents griefing in group chats
- Admin user exception (group anonymous bot)

### Session Management ✅

**Persistence:**
- Per-user JSON files (`logs/sessions/{userId}.json`)
- Resume exactly where you left off
- No context loss (resources, day, progress all visible)

**Session Length:**
- Short (10 min): ~10-15 days progress
- Medium (30 min): ~30-50 days progress
- Full playthrough: 2-4 hours across multiple sessions

---

## Parody & Writing

### Crypto/Web3 Parody ✅

**Sample Events:**

**"HODL Crisis"**
> A chad invested life savings in $TITANICTOKEN. It's down 99.8%. They have diamond hands.
>
> Choice: Stage intervention → "They called you a paper-handed bitch and doubled down"
>
> 💎 **Perfect** — captures crypto bro psychology

**"DAO Governance"**
> The chad wants to vote on every decision using smart contracts. This will take 47 hours per vote.
>
> ⚡ **Perfect** — parodies DAO inefficiency

**"Wrong Planet"**
> A chad keeps calling it "Ur-ANUS" instead of "YOOR-uh-nus". The rest of the chad is furious.
>
> 🎯 **Great** — meta-commentary on pronunciation debate

### Waypoint Character Stories ✅

**Hitchhiker's Guide Deadpan Humor:**

Each waypoint has 500-600 character story with local flavor:
- Moon: Gift shop with $800 moon dust snow globes
- Mars: Traffic controller with 47 ships/month
- Ceres: Mining foreman declaring independence (population: 3)

**Quality:** Consistently funny, adds personality without slowing gameplay.

---

## Balance Assessment

### What Works Brilliantly ✅

1. **Resource Tension** — Fuel/food/hull balance creates meaningful decisions
2. **Waypoint Strategy** — Stop vs skip creates 10 strategic decision points
3. **Shop Availability** — Can resupply at any waypoint (no death spirals from bad RNG)
4. **Starting Resources** — 85% of journey needs = must engage with systems but not punishing
5. **Event Frequency** — 25% base is balanced (not too rare, not overwhelming)
6. **Morale System** — Secondary but creates interesting feedback loops at extremes
7. **Parody Quality** — Genuinely funny, not just trying too hard

### Areas for Enhancement 🔄

1. **Victory Payoff**
   - After 140-day journey, victory is just one message
   - No special celebration or unlockable
   - Could add: point scoring, rank system, achievement celebration

2. **Morale Impact**
   - Currently affects event rate and desertion
   - Could be more impactful at extreme levels
   - Desertion rate (15% at ≤10% morale) is rare

3. **Chad Attachment**
   - Chad members have names but minimal personality integration
   - Deaths feel mechanical rather than emotional
   - Could add: chad epitaphs, personality traits, final words

---

## Recommendations

### High Priority (Low Effort, High Impact)

**1. Enhanced Victory Screen**
- Point scoring system (chad × 100 + supplies × 5 + speed bonus)
- Rank assignment ("Legendary Captain" / "Lucky Survivor")
- Funny recap of journey highlights
- Leaderboard placement
- **Effort:** Low (formatting change)
- **Code:** `GameEngine.php::formatVictoryWithImage()`

**2. Chad Death Epitaphs**
- Memorable death messages with humor
- "Here lies Jordan — diamond hands to the grave"
- "Here lies Sam — opened the vents (big mistake)"
- **Effort:** Low (add to event messages)
- **Code:** `GameEngine.php::applyEffects()`

**3. Last Event Reminder**
- When resuming, show "Last event: Space Pirates (fought them off)"
- Helps context after long breaks
- **Effort:** Low (add to status screen)
- **Code:** `ViewManager.php::formatStatusView()`

### Medium Priority (More Complex)

**4. Achievement System**
- Badges for milestones (First Victory, Zero Deaths, Speed Run, etc.)
- Stored in per-user file
- Display in profile/status
- **Effort:** Medium (tracking system + UI)

**5. Shop Item Expansion**
- Shield Generator (200cr): Block next hull damage
- Emergency Beacon (300cr): One-time chad rescue
- Thruster Boost (250cr): +50% speed for 20 days
- **Effort:** Medium (new items + effects)

---

## Code Reference

### Key Files

| File | Purpose | Key Methods |
|------|---------|-------------|
| `config.php` | Constants, difficulty config | `getDifficulty()`, constants |
| `game/GameEngine.php` | Core game logic | `advanceDay()`, `rest()`, `purchaseItem()` |
| `bot/ViewManager.php` | All view formatting | `formatStatusView()`, `getShopKeyboard()` |
| `game/GameService.php` | The single entry point for player actions | `act()`, `runRule()` |
| `uranus_game_bot.php` | Telegram webhook | Shop/station callbacks |

### Key Constants

```php
TOTAL_DISTANCE = 2_800_000_000  // km to Uranus
TRAVEL_SPEED = 20_000_000       // km/day base
FUEL_PER_DAY = 5                // flat rate
FOOD_PER_CREW = 3               // per chad per day
MAX_CREW = 5
MAX_FUEL = 1200                 // 2× starting (600 × 2)
MAX_FOOD = 3500                 // 2× starting (1750 × 2)
MAX_HULL = 100
MAX_MORALE = 100
```

---

## Final Verdict

**Rating:** ⭐⭐⭐⭐½ (4.5/5)

This is a **well-designed, genuinely funny** game that successfully adapts Oregon Trail to space with creative mechanics and excellent Telegram integration. The single-difficulty balance is well-tuned — challenging but fair.

**Core strengths:**
- Resource management tension
- Strategic waypoint system
- Hilarious parody writing
- Clean UX
- Well-balanced single difficulty

**Minor improvements possible:**
- Victory celebration
- Chad attachment
- Morale impact

**Bottom Line:** The game is production-ready and delivers an engaging 2-4 hour experience with high replayability potential.

---

**Last Verified:** 2026-02-28
**Game Version:** Single-difficulty (post-2026-02-27)
**Code Status:** Verified against actual implementation

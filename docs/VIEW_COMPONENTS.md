# View Components Reference

**Last Updated:** 2026-03-03 (Standardization implemented)
**Purpose:** Catalog all distinct information components across views and track standardization status

This document identifies reusable "components" (clusters of formatted information) across all game views and documents the standardization implementation.

---

## Implementation Status

**✅ STANDARDIZATION COMPLETE (2026-03-03)**

All core formatting methods have been moved to `bot/FormatHelpers.php` for consistency across views:

- ✅ **formatEth()** - Standardized ETH display with 3 decimal places
- ✅ **formatDistance()** - Human-readable distance formatting (B/M/km)
- ✅ **formatVelocityDisplay()** - Velocity with optional boost indicator
- ✅ **formatResourceWithDays()** - Resource display with days remaining
- ✅ **formatProgressBar()** - 16-block progress bar
- ✅ **formatViewHeader()** - Consistent view headers with optional day number
- ✅ **formatResources()** - Three standard formats (full, compact, minimal)

**Backward Compatibility:**
- `GameEngine::formatDistance()` delegates to FormatHelpers
- `ViewManager::formatResourcesSummary()` delegates to FormatHelpers
- All existing views updated to use FormatHelpers methods

**See:** `bot/FormatHelpers.php` for complete method documentation

---

## Standard Formats (Implementation Reference)

### Resource Display Formats

**Full Format** (`formatResources($session, 'full')`):
```
📦 Resources:
⛽ Fuel: 600 | 🍖 Food: 1750 | 🛡️ Hull: 100%
👥 Chad: 5/5 | 😊 Vibes: 100% | 💰 ETH: 15.000
```
- Use: Default for most views (Status View, Station View, Post-Event View)
- 2 lines, all 6 resources, no days remaining

**Compact Format** (`formatResources($session, 'compact')`):
```
⛽ Fuel: 600 units (120d)
🍖 Food: 1750 units (116d)
🛡️ Hull: 100%  |  💰 ETH: 15.000
👥 Chad: 5/5  |  😊 Vibes: 100%
```
- Use: Journey View only
- 4 lines, shows fuel/food with days remaining

**Minimal Format** (`formatResources($session, 'minimal')`):
```
⛽600 🍖1750 🛡️100% 👥5 😊100% 💰15.000
```
- Use: Ultra-compact display (future use)
- 1 line, emoji + value only

### Other Standard Formats

- **Progress Bar:** `formatProgressBar($percent)` - Always 16 blocks (█░)
- **View Header:** `formatViewHeader($name, $day, $emoji)` - Consistent format with optional day
- **Velocity:** `formatVelocityDisplay($speed, $multiplier)` - Boost indicator (⚡)
- **ETH:** `formatEth($amount)` - Always 3 decimal places
- **Distance:** `formatDistance($km)` - Auto-scales to B/M/km

---

## Component Catalog (Historical Analysis)

### 1. RESOURCE SUMMARY

**Purpose:** Display current resource levels
**Appears In:** Journey View, Status View, Station View, Post-Event View, Shop View

#### Variations:

**Format A - Compact (Journey View):**
```
⛽ Fuel: 450 units (90 days)  |  🍖 Food: 1313 units (88 days)
🛡️ Hull: 100%  |  👥 Chad: 5/5  |  😊 Vibes: 100%  |  💰 ETH: 1500
```
- Shows fuel/food with days remaining
- 2 lines, pipe-separated pairs
- Includes ETH as "ETH"

**Format B - Standard (Station View, Post-Event View):**
```
📦 Resources:
⛽ Fuel: 450 units  |  🍖 Food: 1313 units
🛡️ Hull: 100%  |  😊 Morale: 100%  |  💰 Credits: 1500
```
- No days remaining calculation
- Uses "Credits" instead of "ETH"
- Uses "Morale" instead of "Vibes"
- 2 lines, no chad shown

**Format C - Full 6 Resources (Post-Event View):**
```
📦 Resources:
⛽ Fuel: 450 | 🍖 Food: 1313 | 🛡️ Hull: 100%
👥 Chad: 5/5 | 😊 Morale: 100% | 💰 Credits: 1500
```
- Includes chad count
- Shorter format (no "units" label)
- 2 lines, pipe-separated

**Inconsistencies:**
- ❌ ETH vs Credits naming
- ❌ Vibes vs Morale naming
- ❌ Days remaining shown inconsistently
- ❌ Chad shown in some, not others
- ❌ "units" label present/absent

**Standardization Recommendation:**
- Pick one name: "ETH" or "Credits" (suggest ETH, matches game theme)
- Pick one name: "Vibes" or "Morale" (suggest Vibes, more unique)
- Define 3 standard formats:
  - **Compact with days:** Journey View only
  - **Full 6 resources:** Default for most views
  - **Minimal 4 resources:** Station/Shop (omit chad/morale when not relevant)

---

### 2. PROGRESS BAR

**Purpose:** Visual representation of journey completion
**Appears In:** Journey View, Map View (text version), Status View

#### Variations:

**Format A - Journey View:**
```
Progress to Uranus: [████████████░░░░░░░░] 30%
```
- 16 blocks (█ filled, ░ empty)
- Shows percentage
- Prefix text: "Progress to Uranus:"

**Format B - Status View:**
```
Progress: 30% (840M of 2.8B km)
```
- Text only, no visual bar
- Shows distance ratio

**Format C - Map View:**
```
Day 50 ┃ 1000M ┃ 36%
```
- Ultra-compact, pipe-separated
- Part of status line

**Inconsistencies:**
- ❌ Different number of blocks possible
- ❌ Text vs visual representation
- ✅ Percentage calculation consistent

**Standardization Recommendation:**
- Visual bar: Always 16 blocks when shown
- Text format: Always include both percentage and distance
- Suggested standard: `[████████░░░░░░░░] 30% (840M / 2.8B km)`

---

### 3. JOURNEY STATUS

**Purpose:** Current mission stats (day, distance, velocity, ETA)
**Appears In:** Journey View, Status View, Map View, Main Menu

#### Variations:

**Format A - Journey View (Detailed):**
```
🚀 Velocity: 20M km/day (1×) | 📏 Traveled: 840M km
🎯 Destination: 1,960M km (~98 days)
🛰️ Next Stop: Jupiter Gas Mine - 40M km (~2 days)
```
- 3 lines
- Includes velocity, distance traveled, destination, next waypoint
- Velocity shows multiplier

**Format B - Status View (Summary):**
```
Progress: 30% (840M of 2.8B km)
Speed: 20M km/day (1×)
ETA: ~98 days
```
- 3 lines
- Similar info, different layout

**Format C - Map View (Compact):**
```
Day 50 ┃ 1000M ┃ 36%
Status: Deep Space / At [Station]
```
- 2 lines, ultra-compact
- No velocity/ETA

**Format D - Main Menu (Minimal):**
```
📅 Day 42 | 🛸 1240M km
```
- Single line, very minimal

**Inconsistencies:**
- ❌ Different emoji choices (🚀 vs 🛸)
- ❌ Different information density
- ✅ Distance formatting consistent (M/B km)

**Standardization Recommendation:**
- Define "minimal", "compact", "full" formats
- Minimal: `Day X | Distance`
- Compact: `Day X | Distance | Progress%`
- Full: Include velocity, ETA, next waypoint
- Standardize emoji: 🚀 for velocity, 📏 for distance, 🎯 for destination

---

### 4. CREW QUOTE

**Purpose:** Display contextual chad quote
**Appears In:** Journey View, Status View, Post-Event View, Station View

#### Variations:

**Format A - Journey View:**
```
💬 "Quote text" — Name (Role)
```
- Single line
- Quote in quotes, name and role after em-dash

**Format B - Status View:**
```
👨‍✈️ Name (Role) — Trait
   💬 "Quote text"
   📍 Current activity
```
- Multi-line per chad
- Quote indented on separate line
- Includes trait and activity

**Format C - Post-Event View:**
```
💬 Name: "Quote text"
```
- Single line
- No role shown
- Different punctuation (colon instead of em-dash)

**Format D - Station View:**
```
"[Character Quote]"
— [Character Name], [Character Role]
```
- Multi-line
- Quote on first line
- Attribution on second line with em-dash prefix

**Inconsistencies:**
- ❌ Quote attribution format varies (3 different styles)
- ❌ Role shown/not shown
- ❌ Single-line vs multi-line
- ✅ Quote always in double quotes

**Standardization Recommendation:**
- Single-line format: `💬 "Quote" — Name (Role)` (for journey, post-event)
- Multi-line format: `"Quote"\n— Name (Role)` (for station stories)
- Always show role when showing name
- Always use em-dash for attribution

---

### 5. CREW ROSTER

**Purpose:** List all chads with details
**Appears In:** Status View only

#### Format:
```
👨‍✈️ Name (Role) — Trait
   💬 "Contextual quote"
   📍 Current activity
```
- 3 lines per chad
- Emoji avatar, name, role, trait on first line
- Indented quote and activity

**Notes:**
- ✅ Consistent format (only appears in one view)
- Shows alive and deceased chad differently
- Deceased: `❌ Lost` instead of quote/activity

**Standardization Recommendation:**
- Keep current format
- Consider: Could this be useful in mini-app expanded view?

---

### 6. FLASH MESSAGE

**Purpose:** Show temporary notifications/outcomes
**Appears In:** Journey View, Post-Event View, Launch View

#### Variations:

**Format A - Journey View:**
```
[Flash message text]
```
- Plain text, no decoration
- Shows if `context['flash']` exists

**Format B - Launch View:**
```
🚀 Launch successful! The journey to Uranus has begun!
```
- Emoji prefix
- Hardcoded text

**Format C - "No Events Today":**
```
✅ No Events Today
```
- Shows when no flash message

**Inconsistencies:**
- ❌ Sometimes has emoji prefix, sometimes doesn't
- ❌ "No Events Today" vs blank when no message

**Standardization Recommendation:**
- Always use emoji prefix matching context:
  - 🚀 Launch/travel
  - 🎲 Events
  - ⚠️ Warnings
  - ✅ Success
  - ❌ Failure
  - 📡 Discovery
- Optional: Remove "No Events Today" (clutter when nothing happened)

---

### 7. WARNING BANNER

**Purpose:** Alert player to critical resource levels
**Appears In:** Journey View

#### Format:
```
⚠️ WARNING: Low fuel (12 days), Low food (12 days)
```
- Single line
- Comma-separated list of warnings

**Triggers:**
- Fuel < 10 days
- Food < 10 days
- Hull < 30%
- Morale < 30%

**Notes:**
- ✅ Consistent format
- Only appears in Journey View
- Could be useful in other views

**Standardization Recommendation:**
- Keep current format
- Consider: Show in Status View and mini-app
- Consider: Different severity levels (⚠️ warning vs 🚨 critical)

---

### 8. VELOCITY DISPLAY

**Purpose:** Show current travel speed with multiplier
**Appears In:** Journey View, Status View, Map View

#### Variations:

**Format A - Journey View:**
```
🚀 Velocity: 20M km/day (1×)
```
- Full label with emoji
- Shows multiplier in parentheses

**Format B - Journey View (Header):**
```
🚀 DAY 42 — Velocity: 20M km/day (⚡2×)
```
- Inline in header
- Lightning emoji for boost

**Format C - Status View:**
```
Speed: 20M km/day (1×)
```
- No emoji
- "Speed" instead of "Velocity"

**Inconsistencies:**
- ❌ "Velocity" vs "Speed"
- ❌ Emoji usage inconsistent
- ✅ Multiplier format consistent

**Standardization Recommendation:**
- Use "Velocity" everywhere (more sci-fi than "speed")
- Always show multiplier
- Use ⚡ emoji when multiplier > 1.0
- Standard format: `🚀 Velocity: 20M km/day (⚡2×)`

---

### 9. WAYPOINT INFO

**Purpose:** Show current/next waypoint details
**Appears In:** Journey View, Map View, Waypoint View, Station View

#### Variations:

**Format A - Journey View (Next Waypoint):**
```
🛰️ Dist to Jupiter Gas Mine - 40M km (~2 days)
```
- Distance and ETA shown

**Format B - Journey View (At Waypoint):**
```
═══════════════════════════
🛰️ ARRIVED: JUPITER GAS MINE
═══════════════════════════
```
- Banner format with borders

**Format C - Map View:**
```
├─→ 🚀 YOU ···· 1000M
┃   ≫ Jupiter → Saturn
```
- Tree format with arrows
- Shows from/to waypoints

**Format D - Station View:**
```
📍 [Station Name]
```
- Simple title

**Inconsistencies:**
- ❌ Different emoji usage (🛰️ vs 📍)
- ❌ Banner vs simple text
- ❌ Different information shown

**Standardization Recommendation:**
- Use 🛰️ for waypoints consistently
- Use 📍 for current location
- Banner format for arrivals only
- Standard format: `🛰️ Next: [Name] - [Distance] (~[Days]d)`

---

### 10. RESOURCE CHANGES (Post-Event)

**Purpose:** Show before/after comparison of resources
**Appears In:** Post-Event View only

#### Format:
```
📊 RESOURCE CHANGES:
⛽ Fuel: 500 → 450 (-50)
🛡️ Hull: 100% → 80% (-20%)
```
- Only shows resources that changed
- Shows delta with sign (+ or -)
- Arrow notation for before → after

**Notes:**
- ✅ Consistent format
- Only appears in Post-Event View
- Very useful pattern

**Standardization Recommendation:**
- Keep current format
- Consider: Use in shop purchases ("ETH: 1500 → 1300 (-200)")
- Consider: Use in hunt results ("Food: 1200 → 1250 (+50)")

---

### 11. MISSION OVERVIEW (Status View)

**Purpose:** High-level journey summary
**Appears In:** Status View only

#### Format:
```
═══════════════════════════
MISSION OVERVIEW
═══════════════════════════
Progress %, speed with multiplier, ETA
```
- Section header with border
- Multiple stats below

**Notes:**
- Only appears in Status View
- Uses text borders for sections

**Standardization Recommendation:**
- Keep for detailed views
- Not needed in compact views

---

### 12. SECTION HEADERS

**Purpose:** Label sections within views
**Appears In:** Status View, Post-Event View, Map View

#### Variations:

**Format A - Status View:**
```
═══════════════════════════
SECTION NAME
═══════════════════════════
```
- Double border, uppercase

**Format B - Post-Event View:**
```
📊 RESOURCE CHANGES:
```
- Emoji + uppercase + colon

**Format C - Map View:**
```
⟬ SECTION NAME ⟭
```
- Special bracket characters

**Inconsistencies:**
- ❌ Three different styles
- ❌ Inconsistent emoji usage

**Standardization Recommendation:**
- Detailed views: Use double border format
- Compact views: Use emoji + uppercase + colon
- Map view: Keep special brackets (unique aesthetic)

---

### 13. MAP DISPLAY

**Purpose:** Visual tree of waypoint progression
**Appears In:** Map View only

#### Format:
```
☉ Sol
 ┃
 ├─✓ 🌍 Earth ····· 0M
 ┃    Start
 ┃
 ├─→ 🚀 YOU ···· 1000M
 ┃   ≫ Jupiter → Saturn
```
- Tree structure with box drawing characters
- Checkmarks for passed, arrow for current, circles for future
- Distance shown with dots

**Notes:**
- ✅ Consistent format
- Width constrained to 28 characters
- Only appears in Map View

**Standardization Recommendation:**
- Keep current format (unique and works well)

---

### 14. EVENT COMPONENTS

**Purpose:** Display random event with choices
**Appears In:** Event View only

#### Components:
- Event title/type
- Event description
- Optional chad reactions
- Choice buttons (dynamic)

**Notes:**
- ✅ Consistent format
- Dynamic based on event data

**Standardization Recommendation:**
- Keep current format

---

### 15. SHOP ITEMS

**Purpose:** Display available purchases
**Appears In:** Shop View only

#### Format:
```
[Inline keyboard with item buttons]
```
- Dynamic buttons based on station services
- Shows cost in button text

**Notes:**
- Shop uses inline keyboard, not message text
- Items: Fuel (+100), Food (+50), Repair (+20%)

**Standardization Recommendation:**
- Keep current format

---

### 16. STATION STORY

**Purpose:** Display local character and flavor text
**Appears In:** Station View only

#### Format:
```
📍 [Station Name]

[Local Character Story]
"[Character Quote]"
— [Character Name], [Character Role]
```
- Location header
- Story paragraph
- Multi-line quote attribution

**Notes:**
- ✅ Consistent format
- Only appears at stations

**Standardization Recommendation:**
- Keep current format

---

### 17. VIEW HEADERS

**Purpose:** Title/context for each view
**Appears In:** Most views

#### Variations:

**Format A - Journey View:**
```
🚀 DAY 42 — Journey to Uranus
```
- Day number + context

**Format B - Status View:**
```
📊 DETAILED STATUS — Day 42 of the Journey
```
- View name + day number

**Format C - Map View:**
```
🛰️ DEEP SPACE NAV 🛰️
```
- View name, no day number

**Format D - Main Menu:**
```
🏠 MAIN MENU
```
- Simple title

**Inconsistencies:**
- ❌ Day shown in some, not others
- ❌ Different emoji styles
- ❌ Different text formats

**Standardization Recommendation:**
- Views with day context: `[Emoji] DAY X — [Context]`
- Views without day: `[Emoji] [VIEW NAME]`
- Consistent emoji usage per view type

---

## Standardization Matrix

| Component | Views Using It | Formats | Standardized? | Priority |
|-----------|----------------|---------|---------------|----------|
| Resource Summary | 5 views | 3 formats | ❌ No | 🔴 High |
| Progress Bar | 3 views | 3 formats | ❌ No | 🟡 Medium |
| Journey Status | 4 views | 4 formats | ❌ No | 🔴 High |
| Chad Quote | 4 views | 4 formats | ❌ No | 🟡 Medium |
| Chad Roster | 1 view | 1 format | ✅ Yes | 🟢 Low |
| Flash Message | 3 views | 3 formats | ❌ No | 🟡 Medium |
| Warning Banner | 1 view | 1 format | ✅ Yes | 🟢 Low |
| Velocity Display | 3 views | 3 formats | ❌ No | 🟡 Medium |
| Waypoint Info | 4 views | 4 formats | ❌ No | 🟡 Medium |
| Resource Changes | 1 view | 1 format | ✅ Yes | 🟢 Low |
| Section Headers | 3 views | 3 formats | ❌ No | 🟢 Low |
| Map Display | 1 view | 1 format | ✅ Yes | 🟢 Low |
| Event Components | 1 view | 1 format | ✅ Yes | 🟢 Low |
| Shop Items | 1 view | 1 format | ✅ Yes | 🟢 Low |
| Station Story | 1 view | 1 format | ✅ Yes | 🟢 Low |
| View Headers | 5+ views | 4 formats | ❌ No | 🟡 Medium |

---

## Top Standardization Priorities

### 🔴 CRITICAL: Resource Summary
**Problem:** 3 different formats, inconsistent naming (ETH vs Credits, Vibes vs Morale)
**Used In:** 5 views (most common component)
**Impact:** Confusing for players, harder to maintain

**Proposed Standard:**
```php
// Full format (default)
📦 Resources:
⛽ Fuel: 450 units  |  🍖 Food: 1313 units  |  🛡️ Hull: 100%
👥 Chad: 5/5  |  😊 Vibes: 100%  |  💎 ETH: 450.5

// Compact with days (Journey View only)
⛽ Fuel: 450 units (90d)  |  🍖 Food: 1313 units (88d)
🛡️ Hull: 100%  |  👥 Chad: 5/5  |  😊 Vibes: 100%  |  💎 ETH: 450.5

// Minimal (when space constrained)
⛽ 450  |  🍖 1313  |  🛡️ 100%  |  👥 5/5  |  😊 100%  |  💎 450.5
```

**Naming Standards:**
- Use "ETH" (not "Credits") - more thematic
- Use "Vibes" (not "Morale") - more unique/fun
- Use "units" for fuel/food counts
- Always show all 6 resources in same order

---

### 🔴 CRITICAL: Journey Status
**Problem:** 4 different formats across views
**Used In:** 4 views
**Impact:** Inconsistent information density

**Proposed Standard:**
```php
// Full format (Journey View, Status View)
🚀 Velocity: 20M km/day (⚡2×)
📏 Distance: 840M km traveled (30% complete)
🎯 Destination: 1,960M km to Uranus (~98 days)
🛰️ Next Stop: Jupiter Gas Mine - 40M km (~2 days)

// Compact format (Map View)
Day 42 | 840M km | 30%
Velocity: 20M km/day (⚡2×)

// Minimal format (Main Menu, headers)
Day 42 | 840M km (30%)
```

---

### 🟡 MEDIUM: Chad Quote
**Problem:** 4 different attribution formats
**Used In:** 4 views
**Impact:** Inconsistent presentation

**Proposed Standard:**
```php
// Single-line format (Journey, Post-Event)
💬 "Quote text here" — Name (Role)

// Multi-line format (Station stories, Status View)
"Quote text here"
— Name (Role)
```

---

### 🟡 MEDIUM: View Headers
**Problem:** Inconsistent day display and emoji usage
**Used In:** All views
**Impact:** Views feel disconnected

**Proposed Standard:**
```php
// Views with day context
🚀 DAY 42 — Journey to Uranus
📊 DAY 42 — Detailed Status

// Views without day context
🛰️ DEEP SPACE NAV
🏠 MAIN MENU
🏆 LEADERBOARD
```

---

## Implementation Recommendations

### Phase 1: Naming Consistency (Quick Win)
1. Globally replace "Credits" → "ETH"
2. Globally replace "Morale" → "Vibes"
3. Update all references in ViewManager and GameEngine

**Files to Update:**
- `bot/ViewManager.php`
- `game/GameEngine.php`
- `docs/SESSION_STATE.md`
- `docs/GAME_VIEWS.md`

---

### Phase 2: Resource Summary Standardization
1. Create `formatResourceSummary($session, $format = 'full')` method
2. Add format options: 'full', 'compact', 'minimal'
3. Update all views to use standard method
4. Remove duplicate formatting code

**Benefits:**
- Single source of truth
- Easy to update format globally
- Consistent ordering and spacing

---

### Phase 3: Component Library
1. Create dedicated formatting methods for each component:
   - `formatJourneyStatus($session, $format = 'full')`
   - `formatChadQuote($member, $format = 'single')`
   - `formatVelocity($session, $includeEmoji = true)`
   - `formatWaypointInfo($waypoint, $format = 'full')`
2. Document parameters and output formats
3. Migrate existing code to use library

**Benefits:**
- Reusable components
- Easier testing
- Clearer code organization

---

### Phase 4: Mini-App Component Mapping
1. Map standardized components to mini-app UI elements
2. Ensure bot and mini-app use same data formatting
3. Create shared formatting logic (via API responses)

**Benefits:**
- Consistent experience across bot and mini-app
- Easier to maintain
- Data formatting happens once, used twice

---

## Questions for Discussion

1. **Naming:** Do you prefer "ETH" or "Credits"? "Vibes" or "Morale"?

2. **Resource Display:** Should we always show all 6 resources, or contextually hide some (e.g., no chad count in shop)?

3. **Days Remaining:** Should fuel/food days remaining be shown everywhere, or only in Journey View?

4. **Warning Severity:** Should we differentiate between "warning" (< 10 days) and "critical" (< 5 days)?

5. **Section Headers:** Keep multiple styles, or standardize to one?

6. **View Headers:** Always show day number, or only in time-sensitive views?

7. **Flash Messages:** Always use emoji prefix, or keep plain text?

8. **Mini-App:** Which components are most important to standardize for mini-app compatibility?

---

## Related Documentation

- **Game Views:** `docs/GAME_VIEWS.md` (all views catalog)
- **Session Data:** `docs/SESSION_STATE.md` (data structure)
- **Mini-App Components:** `docs/archived/2026-11-11-untangle/mini-app/MINI_APP_COMPONENTS.md` (archived; see `docs/mini-app/README.md`)
- **Mini-App Data:** `docs/archived/2026-11-11-untangle/mini-app/MINI_APP_DATA_REFERENCE.md` (archived; the API snapshot is documented in `api/game/README.md`)
- **ViewManager:** `bot/ViewManager.php` (formatting code)
- **GameEngine:** `game/GameEngine.php` (game logic)

# Session State Reference

**Last Updated:** 2026-11-11 (save holds game state only; station story stored with the waypoint)
**Last Verified Against Code:** 2026-11-11 for the changes listed under "2026-11-11 changes"; other sections last verified 2026-03-02

Complete reference for the session data structure used in Occupy Uranus.

---

## Overview

Session files are stored as JSON in `logs/sessions/{userId}.json`. Each session represents one player's game state.

**Primary Handler:** `game/GameSessionManager.php` (used only through `game/GameService.php`)
**Session Schema:** Flat structure with nested objects for resources, progress, chad

**The save is shared by the Telegram bot and the Mini-App and holds game state only.** Bot-only state (where the bot replies, picture set, show-once notices) lives in `logs/bot_prefs/{userId}.json` ([`bot/BotPreferences.php`](../bot/BotPreferences.php)), never here. `preferred_chat`, `original_chat_id`, `aesthetic` and `pending_flash_resource_changes` no longer exist in new saves (old saves may still contain them; nothing reads them).

### 2026-11-11 changes

- `at_waypoint` (while docked) now holds the waypoint **with the story chosen at docking**: `description` and `local_story` are set, and the `descriptions` / `local_stories` alternatives are dropped. Both clients read the same story from it. A save docked before this change has the raw waypoint (no `local_story`) until the next dock.
- `seen_waypoint_content` (object, station id → `{description_id, story_id}`) records which variants were shown so the next visit picks different ones. Written by `GameEngine::selectStationContent()` when the ship docks.

---

## Session Structure

### Top-Level Fields

| Field | Type | Purpose | Set When |
|-------|------|---------|----------|
| `user_id` | int | Telegram user ID | Session creation |
| `username` | string | Telegram username (fallback display) | Session creation/update |
| `display_name` | string | Telegram display name (preferred) | Session creation/update |
| `difficulty` | string | Game difficulty ('easy', 'normal', 'hard') | Session creation |
| `status` | string | Game phase (see Status Field below) | Various |
| `created_at` | int | Unix timestamp of session start | Session creation |
| `last_updated` | int | Unix timestamp of last action (used for activity tracking) | Every action |
| `resources` | object | Current resource levels | Every day/event |
| `progress` | object | Travel progress tracking | Every day |
| `chad` | object | Chad state and morale | Every day/event |
| `at_waypoint` | object\|null | Current waypoint data (null when traveling) | Waypoint arrival |
| `pending_station_arrival` | object\|null | Temp storage for stop/skip UI | Waypoint approach |
| `waypoints_reached` | array | Array of visited waypoint names | Waypoint visits |
| `recent_event_outcome` | string\|null | Event outcome flag ('success'\|'failure', clears on next action) | Event processing |

---

## Status Field Values

**`session['status']`** controls game phase and available actions:

| Status | Meaning | Available Actions | Next Status |
|--------|---------|-------------------|-------------|
| `'pre_launch'` | Created new game, in setup or launch sequence | New Game View: Launch Ship, Aesthetic<br>Launch Sequence View: GO | `'in_space'` |
| `'in_space'` | Normal travel mode | Continue, Hunt, Status, Map | `'at_waypoint'` or game over |
| `'at_waypoint'` | At waypoint threshold, decision phase | Stop or Skip | `'at_station'` or `'in_space'` |
| `'at_station'` | Stopped at station, service phase | Shop, Rest, Continue | `'in_space'` |

**Status Transitions:**

```php
// Launch ship (GO button triggers this)
// Called by GameEngine->launchShip()
$session['status'] = 'in_space';

// Cross waypoint threshold (GameEngine->advanceDay)
$session['status'] = 'at_waypoint';
$session['at_waypoint'] = $waypoint;

// Stop at waypoint (GameEngine->stopAtWaypoint)
$session['status'] = 'at_station';
$session['at_waypoint'] = $waypoint;

// Skip waypoint (GameEngine->skipWaypoint)
$session['status'] = 'in_space';
$session['at_waypoint'] = null;

// Continue from station (GameEngine->advanceDay clears this automatically)
$session['status'] = 'in_space';
$session['at_waypoint'] = null;
```

**Note:** `pre_launch` covers both New Game View (setup screen) and Launch Sequence View (dramatic launch moment). The status changes to `in_space` only when the GO button is clicked in Launch Sequence View.

**Code References:**
- Status checks: `GameEngine.php` lines 138-145
- Stop at waypoint: `GameEngine.php:stopAtWaypoint()` (line 2659)
- Leave waypoint: `GameEngine.php:advanceDay()` (lines 157-160)
- Skip waypoint: `GameEngine.php:skipWaypoint()` (line 2690)
- Status transitions: `GameEngine.php` lines 167-200, 452-467

---

## Resources Object

**`session['resources']`** tracks consumable resources:

| Field | Type | Range | Purpose | Code Reference |
|-------|------|-------|---------|----------------|
| `credits` | int | 0-999999 | Currency for shops | `config.php:370-372` |
| `fuel` | int | 0-MAX_FUEL | Ship propulsion | `config.php:283-291` |
| `food` | int | 0-MAX_FOOD | Chad sustenance | `config.php:292-299` |
| `hull` | int | 0-100 | Ship integrity (%) | `config.php:304-312` |
| `morale` | int | 0-100 | Chad happiness (%) | `config.php:313-321` |

**Starting Values (Normal Difficulty):**
- Credits: 1,500 (`config.php:371`)
- Fuel: 450 (`config.php:373`)
- Food: 1,313 (`config.php:374`)
- Hull: 100% (`config.php:375`)
- Morale: 100% (`config.php:376`)

**Maximum Capacities:**
- MAX_FUEL: 1,200 (`config.php:285`)
- MAX_FOOD: 3,500 (`config.php:294`)
- MAX_HULL: 100 (`config.php:305`)
- MAX_MORALE: 100 (`config.php:314`)

**Daily Consumption:**
- Fuel: 5 units/day (flat rate) - `config.php:288`, `GameEngine.php:179`
- Food: chad × 3 units/day - `config.php:295`, `GameEngine.php:180`

**Standardized Resource Display:**

All resource summaries across views display these 6 resources:
1. ⛽ **Fuel** - Units (with days remaining in Journey View)
2. 🍖 **Food** - Units (with days remaining in Journey View)
3. 🛡️ **Hull** - Percentage
4. 👥 **Chad** - Active/Max (e.g., "5/5")
5. 😊 **Morale** - Percentage
6. 💰 **Credits** - Currency amount

**Format Examples:**
```
// Post-Event & Station Choice Views
📦 Resources:
⛽ Fuel: 450 | 🍖 Food: 1313 | 🛡️ Hull: 100%
👥 Chad: 5/5 | 😊 Morale: 100% | 💰 Credits: 1500

// Journey View (includes consumption calculations)
⛽ Fuel: 450 units (90 days)  |  🍖 Food: 1313 units (88 days)
🛡️ Hull: 100%  |  👥 Chad: 5/5  |  😊 Morale: 100%  |  💰 Credits: 1500
```

---

## Progress Object

**`session['progress']`** tracks journey advancement:

| Field | Type | Purpose | Code Reference |
|-------|------|---------|----------------|
| `speed` | int | Base travel speed (km/day) | `config.php:281` |
| `speed_multiplier` | float | Speed boost multiplier (1.0 or 2.0) | `GameEngine.php:484-487` |
| `distance` | int | Current distance traveled (km) | `GameEngine.php:170-176` |
| `day` | int | Current day number | `GameEngine.php:168` |

**Constants:**
- TRAVEL_SPEED: 20,000,000 km/day (base speed before Jupiter, `config.php:281`)
- TOTAL_DISTANCE: 2,800,000,000 km to Uranus (`config.php:282`)

**Speed Mechanics:**
- Base speed starts at 20M km/day
- After passing Jupiter (800M km), base speed increases to 40M km/day automatically
- This is stored in `$session['progress']['speed']`

**Effective Speed Calculation:**
```php
$effectiveSpeed = $session['progress']['speed'] * $session['progress']['speed_multiplier'];
// Before Jupiter: 20M × 1.0 = 20M km/day
// After Jupiter: 40M × 1.0 = 40M km/day
// With gravitational assist after Jupiter: 40M × 2.0 = 80M km/day
// Gravitational assist: 20M × 2.0 = 40M km/day (until next waypoint)
```

**Code Reference:**
- Speed calculation: `GameEngine.php:170-176`
- Gravitational assist: `GameEngine.php:484-487`

---

## Chad Object

**`session['chad']`** tracks chad state:

| Field | Type | Purpose | Code Reference |
|-------|------|---------|----------------|
| `count` | int | Number of living chad (1-5) | `config.php:278` |
| `members` | array | Array of chad objects | `GameEngine.php:createNewSession()` |

**Chad Member Object:**
```php
[
    'name' => 'Jordan',           // From chad_personalities.json
    'status' => 'alive',          // 'alive' or 'dead'
    'personality_type' => 'crypto_bro',  // From personalities.json
    'traits' => [...],            // Personality traits
    'catchphrases' => [...]       // Character quotes
]
```

**Starting Chad:**
- MAX_CREW: 5 members (`config.php:278`)
- All start with status 'alive'
- Personalities assigned from `data/chad_personalities.json`

**Code Reference:**
- Chad creation: `GameEngine.php:createNewSession()`
- Chad death: `GameEngine.php` (event outcomes)
- Personality system: `data/chad_personalities.json`

---

## Waypoint Fields

### at_waypoint

**`session['at_waypoint']`** contains current waypoint data when stopped:

| Field | Type | Purpose |
|-------|------|---------|
| `name` | string | Waypoint name ("Mars Flyby", etc.) |
| `type` | string | Cosmetic type (planet/landmark/station/hazard/destination) |
| `distance` | int | Distance from Earth (km) |
| `icon` | string | Map emoji icon |
| `description`, `local_story` | string | Only when docked (`at_station`): the variant chosen at docking |
| `local_character`, `local_job` | string | The station's local character |

**Value:**
- `null` when traveling (`status === 'in_space'` or `status === 'pre_launch'`)
- `object` when at threshold (`status === 'at_waypoint'`)
- `object` when stopped (`status === 'at_station'`)
- Cleared to `null` after skipping or leaving station

**Waypoint IDs (from `data/waypoints.json`):**
- `earth` - Earth Orbit
- `moon` - Moon
- `mars` - Mars Base (station)
- `ceres` - Ceres Asteroid Station (station)
- `jupiter` - Jupiter Gas Mine (station)
- `saturn` - Saturn Outpost (station)
- `deep_space` - Deep Space 69 (station)
- `uranus` - Uranus Arrival (destination)

**Important:** These IDs primarily drive **map labeling** and the special destination case. ALL non-destination waypoints share the same stop/skip + shop/rest behavior when stopped.

**Code Reference:**
- Waypoint data: `data/waypoints.json`
- Waypoint arrival: `GameEngine.php:213-249`

### pending_station_arrival

**`session['pending_station_arrival']`** is temporary storage for stop/skip choice UI:

**Value:**
- `null` normally
- `object` when showing stop/skip buttons (contains waypoint data)
- Cleared after player chooses stop or skip

**Purpose:** Allows UI to show waypoint info before player commits to stopping.

**Code Reference:**
- Set on approach: `GameEngine.php:231-237`
- Cleared on choice: `GameEngine.php:452-467`

### waypoints_reached

**`session['waypoints_reached']`** is an array of visited waypoint names:

```php
[
    "Moon",
    "Mars Base",
    "Ceres Asteroid Station",
    ...
]
```

**Purpose:** Track journey progress for map display and stats.

**Code Reference:**
- Updated on arrival: `GameEngine.php:460`

### recent_event_outcome

**`session['recent_event_outcome']`** is a temporary flag for contextual chad quotes:

**Values:**
- `null` - No recent event (normal state)
- `'success'` - Last event had positive outcome
- `'failure'` - Last event had negative outcome

**Lifecycle:**
1. **Set:** After event processing in `processEventChoice()` (line 354)
2. **Read:** By `getQuoteContext()` for contextual quotes (line 1409)
3. **Cleared:** At start of next action (`advanceDay()`, `huntResources()`, `rest()`)

**Purpose:**
- Allows chad to react contextually to event outcomes
- Shows once (on event result + journey view), then clears
- Triggers `post_event_success` or `post_event_failure` quote contexts

**Example flow:**
```php
// Event resolves
$session['recent_event_outcome'] = 'success'; // Set after positive event

// Journey view shows contextual quote
$context = getQuoteContext($session); // Returns 'post_event_success'

// Player continues
advanceDay($session); // Clears the flag
unset($session['recent_event_outcome']);
```

**Classification logic:**
- Chad death = always 'failure'
- Net resource gain = 'success'
- Net resource loss = 'failure'
- Hull damage weighted 1.5× (more serious)

**Code Reference:**
- Set: `GameEngine.php:354` (processEventChoice)
- Cleared: `GameEngine.php:165` (advanceDay), `GameEngine.php:572` (huntResources), `GameEngine.php:422` (rest)
- Classification: `GameEngine.php:2468` (classifyEventOutcome)
- Context check: `GameEngine.php:1409` (getQuoteContext)

---

## Activity Tracking & Who's Playing

### last_updated Field

**`session['last_updated']`** serves dual purpose:
1. **Session persistence:** Timestamp when session was last modified
2. **Activity tracking:** Used to filter "active" players for Who's Playing view

**Updated by:** `GameSessionManager->saveSession()` - automatically stamps every save
**Used for:**
- Session cleanup (expire old sessions)
- Who's Playing view (show players active within last 7 days)

### Who's Playing View

**Access:**
- `/frens` command
- "Who's Playing" button (main menu, journey view, start view)

**Data Source:** `GameSessionManager->getActivePlayers(maxAgeDays = 7)`

**Filtering Logic:**
```php
// Get all sessions with activity in last 7 days
$sevenDaysAgo = time() - (7 * 24 * 60 * 60);
foreach ($allSessions as $session) {
    if ($session['last_updated'] >= $sevenDaysAgo) {
        // Include in Who's Playing list
    }
}
```

**Display Format:**
- Sorted by distance traveled (descending - leaders first)
- Shows: display_name (or username), distance, percentage, days, chad, last activity date
- Special handling for finished games: shows "Victory!" instead of distance

**Code Reference:**
- Activity fetching: `GameSessionManager.php:362` (getActivePlayers)
- View formatting: `ViewManager.php:1633` (formatWhosPlaying)
- Handler: `uranus_game_bot.php` (handleWhosPlaying)

---

## Button Visibility Logic

Understanding status and waypoint fields is critical for button visibility:

### Shop Button
```php
$showShop = $session['status'] === 'at_station';
```

**Visible when:** `status === 'at_station'` (stopped at station for services)

### Rest Button
```php
if ($session['status'] === 'at_station') {
    // Show rest button
}
```

**Visible when:** `status === 'at_station'` (same as shop)

### Hunt Button
```php
if ($session['status'] === 'in_space' && !$this->isAtWaypoint($session)) {
    // Show hunt button
}
```

**Visible when:** Traveling between waypoints (not stopped)

### Continue Button
**Visible:** Only after launching (not shown in pre_launch)
- `in_space` → Advance day (normal travel)
- `at_station` → Leave station (GameEngine clears at_waypoint state automatically)

**Implementation:** When Continue (`advance_day`) is pressed at a station, `GameService` calls `GameEngine->advanceDay()`, which internally clears the waypoint state before advancing the day. No pre-clearing required.

**Note:** In `pre_launch` status, "Launch Ship" and "GO" buttons are used instead of Continue.

**Code References:**
- Button logic: `ViewManager.php:buildActionKeyboard()`
- State clearing: `GameEngine.php:advanceDay()` (lines 157-160)
- Helper methods: `GameEngine.php:isAtWaypoint()`, `getAtWaypoint()`

---

## Common Patterns

### Loading and Saving Sessions

```php
// Load session
$session = $this->sessionManager->loadSession($userId);

// Modify in game logic (returns new copy)
$result = $this->gameEngine->advanceDay($session);
$session = $result['session']; // MUST use returned copy

// Save session
$this->sessionManager->saveSession($userId, $session);
```

**Critical:** GameEngine methods take session BY VALUE and return modified copy. Callers MUST use `$result['session']`.

**Why:** Ensures immutability and clear data flow.

### Checking Game Phase

```php
// Check if at waypoint
if ($this->isAtWaypoint($session)) {
    // Player is stopped at a waypoint
    // Show shop, rest, and continue options
}

// Check if traveling
if ($session['status'] === 'in_space' && !$this->isAtWaypoint($session)) {
    // Player is traveling between waypoints
    // Show hunt option
}

// Check if pre-launch
if ($session['status'] === 'pre_launch') {
    // Player hasn't launched yet
    // New Game View or Launch Sequence View
    // No Continue button shown
}
```

### Getting Current Resources

```php
$credits = $session['resources']['credits'];
$fuel = $session['resources']['fuel'];
$food = $session['resources']['food'];
$hull = $session['resources']['hull'];
$morale = $session['resources']['morale'];
$chadCount = $session['chad']['count'];
```

### Calculating Progress Percentage

```php
$progressPercent = ($session['progress']['distance'] / TOTAL_DISTANCE) * 100;
// Used for map display and milestone checks
```

---

## Validation Rules

### Resource Constraints

**Enforced in:** `GameEngine.php`

- Fuel: Cannot exceed MAX_FUEL (1200)
- Food: Cannot exceed MAX_FOOD (3500)
- Hull: Clamped to 0-100 range
- Morale: Clamped to 0-100 range
- Credits: No upper limit, cannot go negative

**Code Reference:**
- Resource clamping: `GameEngine.php:clampResources()`
- Shop validation: `GameEngine.php:buyShopItem()`

### Chad Rules

- Chad count: 1-5 (game over if 0)
- Chad death: Permanent (no resurrection)
- Status: Only 'alive' or 'dead'

**Game Over Conditions:**
- Chad count reaches 0
- Hull reaches 0
- Fuel reaches 0 (can't travel)
- Food reaches 0 (chad will die)

**Code Reference:**
- Game over checks: `GameEngine.php:checkGameOver()`

---

## File Locations

| Purpose | File Path | Format |
|---------|-----------|--------|
| **Session Files** | `logs/sessions/{userId}.json` | JSON |
| **Session Manager** | `game/GameSessionManager.php` | PHP class |
| **Game Logic** | `game/GameEngine.php` | PHP class |
| **Constants** | `config.php` | PHP defines |

---

## Code Verification Log

**Last Verified:** 2026-02-28

### Files Checked
- ✅ `config.php` - All constants and starting values
- ✅ `GameEngine.php` - Session creation, status transitions, resource consumption
- ✅ `GameSessionManager.php` - File I/O and locking
- ✅ `data/waypoints.json` - Waypoint structure

### Verified Fields
- ✅ All status values and transitions
- ✅ Resource starting values and max capacities
- ✅ Daily consumption rates
- ✅ Progress calculation
- ✅ Waypoint field structure
- ✅ Chad member structure

---

**For architecture context:** See `docs/ARCHITECTURE.md`
**For game mechanics:** See `docs/GAME_MECHANICS_REVIEW.md`
**For codebase structure:** See `docs/CODEBASE_STRUCTURE.md`

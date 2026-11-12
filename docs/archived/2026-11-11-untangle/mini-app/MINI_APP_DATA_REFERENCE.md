# Mini App Data Reference

**Last Updated:** 2026-03-03
**Purpose:** Reference for all game data available to the mini-app Journey View

This document maps session data to mini-app UI components.

---

## Formatting Reference

**For consistent display formatting, use `classes/FormatHelpers.php` methods:**

- `FormatHelpers::formatEth($amount)` - ETH with 3 decimals (e.g., "15.000")
- `FormatHelpers::formatDistance($km)` - Human-readable distance (e.g., "2.80B km", "150.0M km")
- `FormatHelpers::formatVelocityDisplay($speed, $multiplier)` - Velocity with boost indicator
- `FormatHelpers::formatProgressBar($percent, $width)` - Progress bar (default 16 blocks)
- `FormatHelpers::formatResources($session, $format)` - Standard resource displays
  - `'full'` - Default 2-line display for most views
  - `'compact'` - Journey view with days remaining
  - `'minimal'` - Ultra-compact single line

**See:** `docs/VIEW_COMPONENTS.md` for complete format documentation

---

## Journey View Data Sources

### Header Section

| UI Element | Session Field | Type | Example | Notes |
|------------|---------------|------|---------|-------|
| Day Number | `session['progress']['day']` | int | `42` | Current day count |
| Mission Title | Static | string | `"OCCUPY URANUS"` | Hardcoded |

### Progress Section

| UI Element | Session Field | Calculation | Example | Notes |
|------------|---------------|-------------|---------|-------|
| Current Distance | `session['progress']['distance']` | Direct | `1,240,000,000 km` | Distance from Earth |
| Remaining Distance | `TOTAL_DISTANCE - distance` | Calculated | `1,560,000,000 km` | 2.8B km total |
| Progress Percentage | `(distance / TOTAL_DISTANCE) * 100` | Calculated | `44%` | For progress bar fill |
| Total Traveled | `session['stats']['total_distance_traveled']` | Direct | Same or > distance | Includes returns to waypoints |
| Current Velocity | `session['progress']['speed']` | Direct | `20,000,000 km/day` | Base speed (40M after Jupiter) |
| Speed Multiplier | `session['progress']['speed_multiplier']` | Direct | `1.0` or `2.0` | Gravitational assist |
| Effective Speed | `speed * speed_multiplier` | Calculated | `40,000,000 km/day` | Actual travel rate |

**Constants (config.php):**
- `TOTAL_DISTANCE = 2,800,000,000` km
- `TRAVEL_SPEED = 20,000,000` km/day (initial base speed)
- Speed doubles to 40M km/day after passing Jupiter (800M km)

### Waypoint Progress

| UI Element | Data Source | Type | Example | Notes |
|------------|-------------|------|---------|-------|
| Waypoints Reached | `session['waypoints_reached']` | array | `["Moon", "Mars Base"]` | List of visited waypoint names |
| Current Location | `session['at_waypoint']['name']` | string | `"Jupiter Gas Mine"` | Null when traveling |
| Current Location Type | `session['at_waypoint']['type']` | string | `"station"` | planet/landmark/station/hazard/destination |
| Current Location Icon | `session['at_waypoint']['icon']` | emoji | `"♃"` | Map marker |
| Next Waypoint Name | Lookup next waypoint > distance | string | `"Saturn Outpost"` | From waypoints.json |
| Next Waypoint Distance | Lookup + calculate | int | `40,000,000 km` | `waypoint.distance - current_distance` |
| Days to Next Waypoint | `distance_to_next / effective_speed` | calculated | `2 days` | Rounded up |

**Waypoint Data (`data/waypoints.json`):**
- `earth` - Earth Orbit
- `moon` - Moon
- `mars` - Mars Base (station)
- `ceres` - Ceres Asteroid Station (station)
- `jupiter` - Jupiter Gas Mine (station)
- `saturn` - Saturn Outpost (station)
- `deep_space` - Deep Space 69 (station)
- `uranus` - Uranus Arrival (destination)

### Resource Status

| UI Element | Session Field | Range | Format | Notes |
|------------|---------------|-------|--------|-------|
| Fuel Amount | `session['resources']['fuel']` | 0-1200 | `815 units` | Current fuel |
| Fuel Max | `MAX_FUEL` constant | 1200 | `1200` | Maximum capacity |
| Fuel Percentage | `(fuel / MAX_FUEL) * 100` | 0-100 | `68%` | For progress bar |
| Fuel Days | `fuel / FUEL_PER_DAY` | calculated | `163 days` | Days until empty |
| Food Amount | `session['resources']['food']` | 0-3500 | `1575 units` | Current food |
| Food Max | `MAX_FOOD` constant | 3500 | `3500` | Maximum capacity |
| Food Percentage | `(food / MAX_FOOD) * 100` | 0-100 | `45%` | For progress bar |
| Food Days | `food / (chad * FOOD_PER_CREW)` | calculated | `105 days` | Days until empty |
| Hull Percentage | `session['resources']['hull']` | 0-100 | `85%` | Ship integrity |
| Morale Percentage | `session['resources']['vibes']` | 0-100 | `72%` | Chad happiness |
| Chad Count | `session['resources']['chad']` | 1-5 | `5` | Living chad |
| Chad Max | `MAX_CREW` constant | 5 | `5` | Maximum chad |
| Credits | `session['resources']['eth']` | 0-999999 | `450.5` | Currency (ETH) |

**Resource Display Format:**
- Fuel: `815 / 1200` (current / max)
- Food: `1,575 / 3500` (current / max)
- Hull: `85%` (percentage only)
- Morale: `72%` (percentage only)
- Chad: `5 / 5` (alive / max)
- Credits: `450.5` (amount, formatted to 1 decimal)

**Constants (config.php):**
- `MAX_FUEL = 1200`
- `MAX_FOOD = 3500`
- `MAX_HULL = 100`
- `MAX_MORALE = 100`
- `MAX_CREW = 5`
- `FUEL_PER_DAY = 5` (flat rate, not chad-based)
- `FOOD_PER_CREW = 3` (per chad per day)

### Chad Details (For Expanded View)

| UI Element | Session Field | Type | Example | Notes |
|------------|---------------|------|---------|-------|
| Chad Roster | `session['chad']['members']` | array | 5 chad objects | All chad (alive + dead) |
| Chad Name | `member['name']` | string | `"Alex Chen"` | From chad_personalities.json |
| Chad Status | `member['status']` | string | `"alive"` or `"dead"` | Alive/deceased |
| Chad Role | `member['role']` | string | `"Captain"` | Job title |
| Chad Personality | `member['personality_type']` | string | `"crypto_bro"` | Personality type |
| Chad Traits | `member['traits']` | array | `["optimistic", "tech_savvy"]` | Character traits |
| Active Chad Count | Count where status='alive' | int | `5` | Living chad |

**Chad Member Structure:**
```json
{
  "name": "Alex Chen",
  "status": "alive",
  "role": "Captain",
  "personality_type": "crypto_bro",
  "traits": ["optimistic", "tech_savvy"],
  "catchphrases": ["To the moon!", "HODL strong!"]
}
```

### Flash Messages / Events

| UI Element | Session Field | Type | Example | Notes |
|------------|---------------|------|---------|-------|
| Flash Message | Passed via context | string | `"You found 50 units of food..."` | Event outcomes, action results |
| Recent Event Outcome | `session['recent_event_outcome']` | string | `"success"` or `"failure"` | Clears after next action |

**Flash Message Sources:**
- Event outcomes (combat, hazards, discoveries)
- Hunt results (food gained)
- Rest results (morale restored)
- Shop purchases
- Chad deaths
- Resource warnings

### Context-Aware Chad Quote

| UI Element | Data Source | Type | Example | Notes |
|------------|-------------|------|---------|-------|
| Quote Text | `member['catchphrases']` | string | `"To the moon!"` | Context-aware selection |
| Quote Author | `member['name']` | string | `"Alex Chen"` | Rotating daily |
| Quote Author Role | `member['role']` | string | `"Captain"` | Job title |

**Quote Contexts (Priority Order):**
1. `post_event_success` - After positive events (shows once)
2. `post_event_failure` - After negative events (shows once)
3. `at_station` - At waypoint stations
4. `low_morale` - Morale < 30%
5. `critical_resources` - < 5 days fuel/food
6. `near_victory` - <= 20 days to Uranus
7. `near_waypoint` - <= 5 days to next station
8. `general` - Default

**Quote Rotation:**
- Rotates through active chads by day
- Formula: `chadIndex = (day - 1) % activeChad`
- Dead chads excluded from rotation

### Game Status / Phase

| UI Element | Session Field | Values | Notes |
|------------|---------------|--------|-------|
| Game Status | `session['status']` | `'pre_launch'`, `'in_space'`, `'at_waypoint'`, `'at_station'` | Controls available actions |
| At Waypoint | `session['at_waypoint']` | object or null | Waypoint data when stopped |

**Status Values:**
- `'pre_launch'` - Setup/launch sequence (not in mini-app journey view)
- `'in_space'` - Normal travel mode (main mini-app state)
- `'at_waypoint'` - At waypoint threshold, showing stop/skip decision
- `'at_station'` - Stopped at station for services

### Warning Indicators

| Condition | Threshold | Display |
|-----------|-----------|---------|
| Low Fuel | < 10 days remaining | ⚠️ Warning badge |
| Low Food | < 10 days remaining | ⚠️ Warning badge |
| Low Hull | < 30% | ⚠️ Warning badge |
| Low Morale | < 30% | ⚠️ Warning badge |
| Critical Fuel | < 5 days | 🚨 Critical alert |
| Critical Food | < 5 days | 🚨 Critical alert |

---

## Button Availability (Based on Status)

### Journey View (status = 'in_space')

| Button | Callback | Always Available | Conditional |
|--------|----------|------------------|-------------|
| Continue | `action\|continue` | ✅ | - |
| Hunt | `action\|hunt` | ✅ | Not available at waypoints |
| Status | `action\|status` | ✅ | - |
| Map | `action\|map` | ✅ | - |
| Aesthetic | `menu\|aesthetic` | ✅ | - |
| Main Menu | `menu\|main` | ✅ | - |
| Return to Waypoint | `action\|return` | ❌ | Only if waypoints_reached not empty |
| Who's Playing | `menu\|whosplaying` | ✅ | - |

### Station View (status = 'at_station')

| Button | Callback | Always Available |
|--------|----------|------------------|
| Shop | `station\|shop` | ✅ |
| Rest | `station\|rest` | ✅ |
| Continue | `action\|continue` | ✅ |
| Map | `action\|map` | ✅ |
| Main Menu | `menu\|main` | ✅ |

### Waypoint Approach (status = 'at_waypoint')

| Button | Callback | Always Available |
|--------|----------|------------------|
| Stop for Supplies | `station\|stop\|{id}` | ✅ |
| Skip Waypoint | `station\|skip\|{id}` | ✅ |

---

## Format Helper Functions

### Distance Formatting
```php
function formatDistance(int $km): string {
    if ($km >= 1_000_000_000) {
        return number_format($km / 1_000_000_000, 1) . "B km";
    } elseif ($km >= 1_000_000) {
        return number_format($km / 1_000_000) . "M km";
    } else {
        return number_format($km) . " km";
    }
}
```

Examples:
- `1_240_000_000` → `"1,240M km"` or `"1.24B km"`
- `40_000_000` → `"40M km"`
- `500_000` → `"500,000 km"`

### ETH Formatting
```php
function formatEth(int $eth): string {
    return number_format($eth / 100, 1);
}
```

Examples:
- `45050` → `"450.5"`
- `150000` → `"1500.0"`

**Note:** ETH stored as cents (int), displayed as decimal (float)

### Resource with Days
```php
function formatResourceWithDays(string $icon, string $name, int $amount, int $days): string {
    return "{$icon} {$name}: {$amount} units ({$days} days)";
}
```

Examples:
- `"⛽ Fuel: 815 units (163 days)"`
- `"🍖 Food: 1575 units (105 days)"`

### Velocity Display
```php
function formatVelocityDisplay(int $baseSpeed, float $multiplier): string {
    $speedM = $baseSpeed / 1_000_000;
    $multiplierDisplay = $multiplier > 1.0 ? " (⚡{$multiplier}×)" : "";
    return "Velocity: {$speedM}M km/day{$multiplierDisplay}";
}
```

Examples:
- `"Velocity: 20M km/day"` (normal)
- `"Velocity: 20M km/day (⚡2×)"` (gravitational assist)
- `"Velocity: 40M km/day"` (post-Jupiter base speed)
- `"Velocity: 40M km/day (⚡2×)"` (post-Jupiter with assist)

---

## Session Data Example (JSON)

```json
{
  "user_id": 123456,
  "username": "spacecowboy",
  "display_name": "Alex Chen",
  "difficulty": "normal",
  "status": "in_space",
  "created_at": 1709500000,
  "last_updated": 1709586400,
  "resources": {
    "fuel": 815,
    "food": 1575,
    "hull": 85,
    "vibes": 72,
    "chad": 5,
    "eth": 45050
  },
  "progress": {
    "day": 42,
    "distance": 1240000000,
    "speed": 20000000,
    "speed_multiplier": 1.0
  },
  "chad": {
    "count": 5,
    "members": [
      {
        "name": "Alex Chen",
        "status": "alive",
        "role": "Captain",
        "personality_type": "crypto_bro",
        "traits": ["optimistic", "tech_savvy"],
        "catchphrases": ["To the moon!", "HODL strong!"]
      }
      // ... 4 more chads
    ]
  },
  "stats": {
    "total_distance_traveled": 1240000000
  },
  "at_waypoint": null,
  "pending_station_arrival": null,
  "waypoints_reached": ["Moon", "Mars Base", "Ceres Asteroid Station"],
  "recent_event_outcome": null
}
```

---

## API Endpoints (Future Implementation)

**When building backend API for mini-app:**

### GET `/api/game/status`
**Query:** `?user_id={userId}&init_data={telegramInitData}`
**Returns:** Complete session data (JSON structure above)

### POST `/api/game/action`
**Body:**
```json
{
  "user_id": 123456,
  "init_data": "telegram_web_app_init_data",
  "action": "continue",
  "params": {}
}
```
**Returns:**
```json
{
  "success": true,
  "session": { /* updated session */ },
  "flash_message": "Event text",
  "view": "journey"
}
```

**Supported Actions:**
- `continue` - Advance day
- `hunt` - Hunt for food
- `rest` - Rest at station
- `shop` - Buy items (requires `item_id` param)
- `stop` - Stop at waypoint (requires `waypoint_id` param)
- `skip` - Skip waypoint (requires `waypoint_id` param)
- `return` - Return to last waypoint

---

## Related Documentation

- **Session Schema:** `docs/SESSION_STATE.md` (complete field reference)
- **Game Views:** `docs/GAME_VIEWS.md` (all bot views)
- **UI Components:** `docs/MINI_APP_COMPONENTS.md` (mini-app component catalog)
- **Game Engine:** `classes/GameEngine.php` (all game logic)
- **View Manager:** `classes/ViewManager.php` (formatting logic)

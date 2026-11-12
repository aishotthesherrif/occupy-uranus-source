# Journey View - Detailed Analysis

## Overview
The Journey view is the primary gameplay screen. It shows the current status of the mission and provides all major gameplay actions.

**Handler:** `renderJourneyView()` in ViewManager.php
**Formatter:** `formatStatus()` in GameEngine.php (with `includeChadDetails = false`)
**Keyboard:** `getJourneyKeyboard()` in GameEngine.php

---

## What's Displayed

### Header Section
```
📊 MISSION STATUS — Day {current_day}
🎮 Difficulty: {emoji} {difficulty_name}  📍 Status: {status}
```

**Conditionals:**
- If `speed_multiplier > 1.0`: Shows banner
  ```
  ⚡ GRAVITATIONAL ASSIST ACTIVE — 2× Speed Boost
  ```

### Journey Progress Section
```
Journey Progress:
📍 Total KM Travelled: {total_distance_traveled}
🎯 KM to Uranus: {remaining_distance}
🚀 Speed: {effective_speed}/day
⏳ Estimated days to Uranus: {days_remaining}
```

**Conditionals:**
- If `speed_multiplier > 1.0`:
  - Shows: `🚀 Speed: 40M km/day (2× GRAVITATIONAL ASSIST)`
- Else:
  - Shows: `🚀 Speed: 20M km/day`

### Ship Status Section
```
Ship Status:
🛡️ Hull: {hull}%
⛽ Fuel: {fuel} units
🍖 Food: {food} units
```

**Conditionals:**
- If `speed_multiplier > 1.0`: Adds line
  ```
  🚀 Speed Boost: 2× ACTIVE
  ```

### Chad Status Section
```
Chad Status:
👥 Chad: {chad}/5
😊 Morale: {morale}%
```

**Note:** Journey view does NOT show individual chad details (that's Status view only)

### Flash Messages
**Conditionals:**
- If `context['flash']` exists (after events, rest, hunt):
  ```
  {flash_message}

  ─────────────────

  {normal journey content}
  ```

---

## Keyboard Options

### Always Present (Row 1-2):
```
▶️ Continue         [action|continue]
📊 Status | 🗺️ Map  [action|status] [action|map]
```

### Conditional Row 3 - Shop Button:
**Condition:** `atWaypoint !== null` OR `includeShop === true`
```
🛒 Shop  [action|shop]
```

### Conditional Rows 4-5 - Location-Based Actions:

#### If `isAtWaypoint($session) === true`:
```
😴 Rest | 🏠 Main Menu  [action|rest] [menu|main]
```

#### If `isAtWaypoint($session) === false`:
```
🦌 Hunt Resources           [action|hunt]
↩️ Return to Last Waypoint | 🏠 Main Menu
  [action|return] [menu|main]
```

---

## Conditionals Summary

### Display Conditionals:

1. **Gravitational Assist Banner**
   - Condition: `session['progress']['speed_multiplier'] > 1.0`
   - Shows: Alert banner at top + speed boost in journey progress + line in ship status

2. **Speed Display**
   - If boosted: Shows "40M km/day (2× GRAVITATIONAL ASSIST)"
   - If normal: Shows "20M km/day"

3. **Chad Details**
   - Journey view: Always hidden (uses `includeChadDetails = false`)
   - Status view: Always shown (uses `includeChadDetails = true`)

4. **Flash Messages**
   - If `context['flash']` exists: Prepended to message with separator
   - Used for: Event outcomes, rest results, hunt results

### Keyboard Conditionals:

1. **Shop Button** (Row 3)
   - Condition: `getAtWaypoint($session) !== null` OR `includeShop === true`
   - When true: Shop button appears

2. **At Waypoint Actions** (Rows 4-5)
   - Condition: `isAtWaypoint($session) === true`
   - Shows: Rest + Main Menu (2 buttons on one row)

3. **In Space Actions** (Rows 4-5)
   - Condition: `isAtWaypoint($session) === false`
   - Shows: Hunt (row 4), Return + Main Menu (row 5, 2 buttons)

---

## Session State Dependencies

### Required Fields:
- `progress.day` - Current day number
- `difficulty` - Difficulty level (easy/normal/hard)
- `status` - Game status (pre_launch/in_space/at_waypoint)
- `progress.distance` - Distance traveled
- `progress.speed` - Base speed (20M km/day)
- `progress.speed_multiplier` - Speed boost (1.0 or 2.0)
- `stats.total_distance_traveled` - Total distance
- `resources.hull` - Hull integrity (0-100)
- `resources.fuel` - Fuel units
- `resources.food` - Food units
- `resources.chad` - Active chad count (0-5)
- `resources.morale` - Morale percentage (0-100)

### Optional Fields:
- `at_waypoint` - Waypoint data if currently at one
- `context.flash` - Flash message from previous action

---

## Button Actions Summary

### Actions That Advance Time:
- **▶️ Continue** → `advance_day` action → Time advances by 1 day

### Actions That Consume Resources (No Time):
- **😴 Rest** → `rest` action → Consumes extra food/fuel, restores morale
- **🦌 Hunt** → `hunt` action → May consume fuel, gain food

### View Navigation (No State Change):
- **📊 Status** → Shows detailed chad breakdown
- **🗺️ Map** → Shows solar system map with waypoints
- **🛒 Shop** → Shows shop (if at pre_launch or at_waypoint)
- **🏠 Main Menu** → Returns to main menu
- **↩️ Return to Last Waypoint** → Travel backward action

---

## Design Notes

### Current Issues:
1. **Journey = Status** - Journey view currently uses the same formatter as Status view (just hides chad details)
2. **No Day Summary** - After continuing, there's no distinct "what happened today" message
3. **Flash Messages** - Currently used for post-event outcomes, but formatting is minimal

### From TODO in ViewManager.php:
```php
// TODO Phase 2: Implement custom journey format with day summary as decided in design.
// For Phase 1, using existing formatViewWithImage('journey') which shows status format.
```

### Potential Improvements:
- Separate journey formatter with focus on:
  - "Day X Summary" format
  - Distance traveled today
  - Resources consumed
  - What happened (events, waypoints, etc.)
- Keep Status view as detailed "ship manifest" style
- Journey view = "captain's log" style

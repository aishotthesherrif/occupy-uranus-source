# Adding 'at_station' Status - Complete Change Plan

**Date:** 2026-03-02
**Status:** Planning

---

## Overview

Adding a 4th status value `'at_station'` to clarify the distinction between:
- **Waypoint** = geographic location/threshold
- **Station** = service facility (stopped state)

---

## New 4-Status System

| Status | Meaning | View | at_waypoint field | pending_station_arrival field |
|--------|---------|------|-------------------|------------------------------|
| `'pre_launch'` | Setup/launch phase | New Game, Launch Sequence | `null` | `null` |
| `'in_space'` | Traveling | Journey, Event | `null` | `null` |
| `'at_waypoint'` | **NEW:** At threshold, decision phase | **Waypoint View** | `object` | `object` |
| `'at_station'` | **NEW:** Stopped, service phase | **Station View** | `object` | `null` |

**Status Transitions:**
```
'pre_launch' → 'in_space' (launch ship)

'in_space' → 'at_waypoint' (cross threshold)
           → 'in_space' (continue traveling)

'at_waypoint' → 'at_station' (click Stop)
              → 'in_space' (click Skip)

'at_station' → 'in_space' (click Continue)
```

---

## Code Changes Required

### 1. GameEngine.php - Status Transitions

#### Change #1: Set 'at_waypoint' status when crossing threshold
**Location:** After line 229 (checkWaypointReached in advanceDay)

**Current:**
```php
$waypoint = $this->checkWaypointReached($session);
$session['at_waypoint'] = $waypoint;
// No status change
```

**New:**
```php
$waypoint = $this->checkWaypointReached($session);
$session['at_waypoint'] = $waypoint;

// NEW: Set status to at_waypoint when crossing threshold
if ($waypoint !== null) {
    $session['status'] = 'at_waypoint';
}
```

**Why:** Crossing threshold now means entering decision phase (at_waypoint status).

---

#### Change #2: stopAtWaypoint() sets 'at_station' status
**Location:** Line 2723

**Current:**
```php
$session['status'] = 'at_waypoint';
```

**New:**
```php
$session['status'] = 'at_station';  // Changed from 'at_waypoint'
```

**Why:** Clicking Stop means entering service phase (at_station status).

---

#### Change #3: advanceDay() clears 'at_station' status
**Location:** Line 162

**Current:**
```php
if ($session['status'] === 'at_waypoint') {
    $session['status'] = 'in_space';
    $session['at_waypoint'] = null;
}
```

**New:**
```php
if ($session['status'] === 'at_station') {  // Changed from 'at_waypoint'
    $session['status'] = 'in_space';
    $session['at_waypoint'] = null;
}
```

**Why:** Continue from station returns to traveling.

---

#### Change #4: rest() checks 'at_station' status
**Location:** Line 442

**Current:**
```php
if ($session['status'] !== 'at_waypoint') {
    return [
        'success' => false,
        'message' => '❌ You can only rest at waypoints!',
        'session' => $session,
    ];
}
```

**New:**
```php
if ($session['status'] !== 'at_station') {  // Changed from 'at_waypoint'
    return [
        'success' => false,
        'message' => '❌ You can only rest at stations!',  // Updated message
        'session' => $session,
    ];
}
```

**Why:** Can only rest when stopped at station (service phase).

---

#### Change #5: getQuoteContext() returns 'at_station' context
**Location:** Line 1500

**Current:**
```php
if ($status === 'at_waypoint') {
    return 'at_waypoint';
}
```

**New:**
```php
if ($status === 'at_station') {  // Changed from 'at_waypoint'
    return 'at_waypoint';  // Keep context name (chad quotes still reference waypoint)
}
```

**Why:** Chad quotes use 'at_waypoint' context (semantic - they're at a waypoint location, even though status is at_station).

**Note:** Could also rename context to 'at_station' for consistency, but quotes are about the waypoint location.

---

### 2. ActionManager.php - Route Logic

#### Change #6: Don't check for 'at_waypoint' status (it's now valid)
**Location:** Line 88

**Current:**
```php
if ($session['status'] !== 'at_waypoint' && empty($session['pending_station_arrival'])) {
    // Present choice: stop or skip
    $session['pending_station_arrival'] = $waypoint;
    return [...];
}
```

**New:**
```php
if ($session['status'] === 'at_waypoint' && empty($session['pending_station_arrival'])) {
    // Present choice: stop or skip
    $session['pending_station_arrival'] = $waypoint;
    return [...];
}
```

**Why:** Now we WANT status to be 'at_waypoint' when presenting the choice. The check becomes: "if we're at_waypoint status and haven't presented choice yet, present it now."

**Alternative approach:**
```php
if (empty($session['pending_station_arrival'])) {
    // Present choice: stop or skip
    $session['pending_station_arrival'] = $waypoint;
    return [...];
}
```

**Rationale:** If GameEngine already set status = 'at_waypoint', we just need to check if we've already presented the choice.

---

### 3. GameController.php - View Routing

#### Change #7: Route to 'station' view for 'at_station' status
**Location:** Line 112

**Current:**
```php
if ($session['status'] === 'at_waypoint') {
    return 'station';
}
```

**New:**
```php
if ($session['status'] === 'at_station') {  // Changed from 'at_waypoint'
    return 'station';
}
```

**Why:** Station view shown for at_station status.

---

#### Change #8: Prevent view switching when at_station
**Location:** Line 178

**Current:**
```php
if ($session['status'] === 'pre_launch' || $session['status'] === 'at_waypoint') {
    $viewName = 'shop';
}
```

**New:**
```php
if ($session['status'] === 'pre_launch' || $session['status'] === 'at_station') {  // Changed from 'at_waypoint'
    $viewName = 'shop';
}
```

**Why:** Shop view logic checks for stopped state (at_station).

---

### 4. ViewManager.php - View Selection

#### Change #9: Status-based prompt in Journey View
**Location:** Line 515

**Current:**
```php
} elseif ($session['status'] === 'at_waypoint') {
    return "What would you like to do at the station?";
```

**New:**
```php
} elseif ($session['status'] === 'at_waypoint') {
    return "Will you stop here?";  // Decision phase
} elseif ($session['status'] === 'at_station') {
    return "What would you like to do at the station?";  // Service phase
```

**Why:** Different prompts for decision vs. service phase.

**Note:** Need to verify this function's purpose - may be keyboard building logic.

---

### 5. Additional Changes to Consider

#### Shop Access Logic
**Search for:** Shop availability checks

**Current behavior:** Shop available when `at_waypoint !== null` or status `at_waypoint`

**New behavior:** Shop available when status `at_station` OR status `at_waypoint` (can preview shop during decision phase?)

**Decision needed:** Can player access shop during decision phase, or only after stopping?

---

#### Button Visibility
**Current:**
- Hunt button: visible when `status === 'in_space' && !at_waypoint`
- Rest button: visible when `status === 'at_waypoint'`
- Shop button: visible when `at_waypoint !== null`

**New:**
- Hunt button: visible when `status === 'in_space'`
- Rest button: visible when `status === 'at_station'`
- Shop button: visible when `status === 'at_station'` (or also 'at_waypoint'?)

---

## Documentation Changes Required

### 1. SESSION_STATE.md

#### Update Status Field Table (line 42-48)
```markdown
| Status | Meaning | Available Actions | Next Status |
|--------|---------|-------------------|-------------|
| `'pre_launch'` | Created new game, in setup or launch sequence | New Game View: Launch Ship, Aesthetic<br>Launch Sequence View: GO | `'in_space'` |
| `'in_space'` | Normal travel mode | Continue, Hunt, Status, Map | `'at_waypoint'` or game over |
| `'at_waypoint'` | At waypoint threshold, decision phase | Stop or Skip | `'at_station'` or `'in_space'` |
| `'at_station'` | Stopped at station, service phase | Shop, Rest, Continue | `'in_space'` |
```

#### Update Status Transitions (line 50-64)
```php
// Crossing threshold (NEW)
$session['status'] = 'at_waypoint';

// Stop at waypoint
$session['status'] = 'at_station';  // Changed from 'at_waypoint'
$session['at_waypoint'] = $waypoint;

// Skip waypoint
$session['status'] = 'in_space';
$session['at_waypoint'] = null;

// Continue from station
$session['status'] = 'in_space';
$session['at_waypoint'] = null;
```

#### Update at_waypoint Field Description (line 199-220)
```markdown
**Value:**
- `null` when not near waypoint (status: 'in_space' or 'pre_launch')
- `object` when at threshold (status: 'at_waypoint')
- `object` when stopped (status: 'at_station')
- `null` after skipping or leaving
```

---

### 2. GAME_VIEWS.md

#### Update Waypoint View description (line 177-197)
Add status info:
```markdown
**Status:** `'at_waypoint'` (decision phase)
```

#### Update Station View description (line 216-244)
Change status:
```markdown
**Status:** `'at_station'` (was `'at_waypoint'`)
```

---

### 3. ARCHITECTURE.md

#### Update Decision Log (line 433-439)
Add new entry:
```markdown
### 2026-03-02: Added 'at_station' status for clarity
**Issue:** 'at_waypoint' status was overloaded - meant both "at threshold deciding" and "stopped at station"
**Decision:** Added 4th status 'at_station' to separate:
  - 'at_waypoint' = crossed threshold, showing stop/skip decision (Waypoint View)
  - 'at_station' = chose to stop, using services (Station View)
**Rationale:** Clearer semantics - waypoint = location, station = stopped state
**Commit:** TBD
```

---

### 4. CLAUDE.md

#### Update Status Values section (line 325-330)
```markdown
## Session Status Values (→ docs/SESSION_STATE.md)

**Quick reference only. Full schema: [`docs/SESSION_STATE.md`](docs/SESSION_STATE.md)**

- **`'pre_launch'`** — Just created, before clicking Launch Ship
- **`'in_space'`** — Normal travel mode
- **`'at_waypoint'`** — At waypoint threshold, showing stop/skip decision
- **`'at_station'`** — Stopped at station for services
```

---

## Testing Plan

### Manual Test Cases

1. **Cross waypoint threshold**
   - Status should become `'at_waypoint'`
   - Waypoint View should show Stop/Skip buttons
   - `pending_station_arrival` should be set

2. **Click Stop**
   - Status should become `'at_station'`
   - Station View should show Shop/Rest/Continue buttons
   - `pending_station_arrival` should be cleared

3. **Click Skip**
   - Status should become `'in_space'`
   - Journey View should show
   - `at_waypoint` should be cleared
   - Speed multiplier should be 2.0

4. **Click Continue from station**
   - Status should become `'in_space'`
   - Journey View should show
   - `at_waypoint` should be cleared

5. **Click Rest at station**
   - Status should remain `'at_station'`
   - Station View should show with flash message
   - Hull and morale should increase

6. **Try to rest while traveling**
   - Should fail with error message
   - Status should remain `'in_space'`

### Edge Cases

1. **Load old session with status = 'at_waypoint'**
   - Migration: treat as `'at_station'` (stopped state)
   - Or: force to `'in_space'` and clear waypoint

2. **Event triggers while at_waypoint status**
   - Should not happen (events checked after waypoint in advanceDay)
   - Verify event doesn't override waypoint view

---

## Migration Strategy

### Session Data Migration

**Problem:** Existing sessions may have `status = 'at_waypoint'`

**Options:**

**Option A: Treat as 'at_station' (conservative)**
```php
// In advanceDay or session load
if ($session['status'] === 'at_waypoint') {
    $session['status'] = 'at_station';  // Assume they stopped
}
```

**Option B: Force to 'in_space' (reset state)**
```php
// In advanceDay or session load
if ($session['status'] === 'at_waypoint') {
    $session['status'] = 'in_space';
    $session['at_waypoint'] = null;
    unset($session['pending_station_arrival']);
}
```

**Recommendation:** Option A (treat as at_station) - less disruptive.

---

## Implementation Order

1. ✅ Create this plan document
2. ✅ Update GameEngine status transitions
3. ✅ Update ActionManager route logic
4. ✅ Update GameController view routing
5. ✅ Update ViewManager conditionals
6. ✅ Update chad quote context in chad_personalities.json
7. ⏳ Test all status transitions manually
8. ✅ Update documentation (SESSION_STATE.md, GAME_VIEWS.md, ARCHITECTURE.md, CLAUDE.md)
9. ⏳ Deploy and verify in production (after testing)

---

## Open Questions - RESOLVED

1. **Shop access during decision phase:** ✅ Only at_station (not during at_waypoint decision phase)

2. **Chad quote context:** ✅ Renamed to 'at_station' to match status

3. **Migration strategy:** ✅ Don't worry about old games (will be reset)

4. **View routing in GameController line 178:** What is this logic doing? Need to verify purpose before changing.

---

**Next Step:** Discuss open questions, then implement in order listed above.

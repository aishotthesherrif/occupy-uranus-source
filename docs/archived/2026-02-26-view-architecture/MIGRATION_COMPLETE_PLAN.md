# Complete Migration Plan: Unified Architecture

**Goal:** Finish migrating all handlers to the new architecture and delete all legacy code.

---

## Current State Analysis

### ✅ Already Migrated (4 handlers)
- `handleContinue()` → `routeViaArchitecture('continue')`
- `handleJourney()` → `routeViaArchitecture('journey')`
- `handleStatus()` → `routeViaArchitecture('status')`
- `handleMap()` → `routeViaArchitecture('map')`

### ❌ Not Yet Migrated

#### Action Handlers (need ActionManager support)
1. **`handleRest()`** - Rest to repair hull/morale
2. **`handleReturnToLastWaypoint()`** - Backtrack to last waypoint
3. **`handleHunt()`** - Hunt for food (future feature)
4. **`handleShop()`** - Show shop
5. **`handleShopCallback()`** - Shop purchases (fuel, food, rest, leave)

#### Event Handlers
6. **`handleEventCallback()`** - Process event choices

#### Result Routers (complex, need replacement)
7. **`showDayResult()`** - Routes to event view OR journey view
8. **`handleGameOverResult()`** - Routes to game over OR continues flow
9. **`showGameOver()`** - Display game over screen
10. **`showVictory()`** - Display victory screen

### 🗑️ Dead Code to Delete
- `handleContinue_LEGACY()` - Preserved for reference, never called
- All the old code paths once migration is complete

---

## Migration Plan (8 Steps)

### **Step 1: Add Event Processing to ActionManager** ✅ Already Done
**Status:** Event processing already exists (`process_event`)

**Verify:**
```php
// classes/ActionManager.php line 28
case 'process_event':
    return $this->processEvent($session, $params);
```

---

### **Step 2: Add Rest Action to ActionManager**

**Current:** `handleRest()` directly calls GameEngine.rest() and formats output

**New:** Add rest to ActionManager's execute() switch

**Implementation:**
```php
// classes/ActionManager.php
case 'rest':
    return $this->rest($session);
```

Already exists! Just need to add button routing.

**Files to modify:**
- `classes/GameController.php` - Add 'rest' to `$buttonActionMap`

---

### **Step 3: Migrate handleRest()**

**Current code:**
```php
private function handleRest(...) {
    $session = loadSession();
    $result = gameEngine->rest($session);
    saveSession();
    // Show journey view with flash message
}
```

**New code:**
```php
private function handleRest(...) {
    return $this->routeViaArchitecture('rest', $userId, $chatId, [], ...);
}
```

**Files to modify:**
- `uranus_game_bot.php` - Simplify handleRest()

---

### **Step 4: Migrate handleEventCallback()**

**Current:** Loads session, calls `gameEngine->processEventChoice()`, routes to showDayResult

**New:** Route through architecture with params

**Implementation:**
```php
private function handleEventCallback($eventId, $choiceId, ...) {
    $params = [
        'event_id' => $eventId,
        'choice_id' => $choiceId,
    ];
    return $this->routeViaArchitecture('event_choice', $userId, $chatId, $params, ...);
}
```

**Files to modify:**
- `classes/GameController.php` - Add 'event_choice' to `$buttonActionMap`
- `uranus_game_bot.php` - Simplify handleEventCallback()

---

### **Step 5: Handle Shop Actions**

**Challenge:** Shop is complex with multiple sub-actions (buy fuel, buy food, rest, leave)

**Strategy:** Defer full shop migration to later. For now, make it work with minimal changes.

**Keep as-is for now:**
- `handleShop()`
- `handleShopCallback()`

**Reason:** Shop logic is self-contained and doesn't interfere with the architecture. Can be migrated later.

---

### **Step 6: Eliminate showDayResult() Router**

**The Problem:** `showDayResult()` is a view router that decides:
- If event → show event view
- If no event → show journey view with day result

**Solution:** This logic is now in `ActionManager.advanceDay()`:
```php
// ActionManager already does this:
if (!empty($result['event'])) {
    return ['next_view' => 'event', 'context' => ['event' => ...]];
}
return ['next_view' => 'journey', 'flash' => 'Day complete!'];
```

**What to do:**
1. Verify ActionManager.advanceDay() handles all cases
2. Remove all calls to showDayResult()
3. Delete showDayResult() method

**Current callers:**
- `handleContinue_LEGACY()` - Already dead code
- Need to search for any other callers

**Files to modify:**
- `uranus_game_bot.php` - Delete showDayResult() and handleContinue_LEGACY()

---

### **Step 7: Migrate Game Over and Victory**

**Current:** `handleGameOverResult()` calls `showGameOver()` or `showVictory()`

**New:** ActionManager already returns `next_view: 'game_over'` or `'victory'`

**What ViewManager needs:**
- Already has `renderGameOverView()` ✅
- Already has `renderVictoryView()` ✅

**What ActionManager returns:**
```php
if (!empty($result['game_over'])) {
    return [
        'next_view' => 'game_over',
        'context' => ['reason' => $result['game_over_reason']],
    ];
}
```

**Verify flow:**
```
Continue button → ActionManager.advanceDay()
                → game_over detected
                → GameController gets next_view: 'game_over'
                → ViewManager.render('game_over', $session, $context)
                → User sees game over screen
```

**Files to check:**
- Ensure GameController handles game_over and victory views
- Delete showGameOver(), showVictory() if no other callers
- Delete handleGameOverResult()

---

### **Step 8: Clean Up Dead Code**

**Methods to delete:**
1. `handleContinue_LEGACY()` - Already dead
2. `showDayResult()` - Replaced by ActionManager + ViewManager
3. `handleGameOverResult()` - Logic moved to ActionManager
4. `showGameOver()` - Replaced by ViewManager.renderGameOverView()
5. `showVictory()` - Replaced by ViewManager.renderVictoryView()

**Methods to keep (for now):**
- `handleShop()` - Complex, migrate later
- `handleShopCallback()` - Complex, migrate later
- `handleReturnToLastWaypoint()` - Not yet in ActionManager (TODO Phase 2)
- `handleHunt()` - Placeholder feature

**Final verification:**
```bash
# Search for any remaining calls to deleted methods
grep -r "showDayResult\|showGameOver\|showVictory\|handleGameOverResult" *.php
```

---

## Implementation Order

### Phase 1: Actions (Steps 2-4) ⏱️ 30 minutes
1. Add 'rest' and 'event_choice' to GameController button mapping
2. Simplify handleRest() to use routeViaArchitecture
3. Simplify handleEventCallback() to use routeViaArchitecture
4. Test: Continue → Event → Choice → Journey

### Phase 2: Eliminate Routers (Steps 6-7) ⏱️ 20 minutes
5. Verify ActionManager handles all advance_day outcomes
6. Search and verify no other callers of showDayResult
7. Delete showDayResult(), handleContinue_LEGACY(), handleGameOverResult()
8. Verify showGameOver/showVictory not called elsewhere, delete
9. Test: Continue → Game Over
10. Test: Continue → Victory

### Phase 3: Clean Up (Step 8) ⏱️ 10 minutes
11. Remove dead code
12. Run syntax check on all files
13. Search for any orphaned methods
14. Update documentation

**Total time estimate:** ~1 hour

---

## Verification Checklist

After migration, verify:

- [ ] `/game` → Select difficulty → Continue works
- [ ] Continue advances day correctly
- [ ] Events trigger and display
- [ ] Event choices process correctly
- [ ] Back to Journey shows consistent view
- [ ] Rest action works
- [ ] Game over displays correctly
- [ ] Victory displays correctly
- [ ] Status view works
- [ ] Map view works
- [ ] No PHP errors in logs
- [ ] No calls to deleted methods

---

## Files That Will Be Modified

### ActionManager (already complete)
- No changes needed (rest and process_event already exist)

### GameController
```php
// Add to $buttonActionMap:
'rest' => 'rest',
'event_choice' => 'process_event',
```

### uranus_game_bot.php
**Simplify:**
- handleRest() → 1 line
- handleEventCallback() → 3 lines

**Delete:**
- handleContinue_LEGACY() (50 lines)
- showDayResult() (45 lines)
- handleGameOverResult() (15 lines)
- showGameOver() (5 lines)
- showVictory() (5 lines)

**Net result:** -115 lines of code

---

## Risk Assessment

### Low Risk ✅
- Adding button mappings to GameController
- Simplifying handleRest() and handleEventCallback()
- Deleting clearly dead code (handleContinue_LEGACY)

### Medium Risk ⚠️
- Deleting showDayResult() - verify no other callers first
- Deleting game over/victory methods - verify routing works

### High Risk 🔴
- None! The architecture already handles all cases.

### Rollback Strategy
- Git revert if anything breaks
- Each step is independent and can be rolled back individually

---

## Post-Migration State

### Active Handlers (using new architecture)
```
handleContinue()          → routeViaArchitecture('continue')
handleJourney()           → routeViaArchitecture('journey')
handleStatus()            → routeViaArchitecture('status')
handleMap()               → routeViaArchitecture('map')
handleRest()              → routeViaArchitecture('rest')
handleEventCallback()     → routeViaArchitecture('event_choice')
```

### Legacy Handlers (to migrate in Phase 2)
```
handleShop()              → Complex shop logic (defer)
handleShopCallback()      → Complex shop logic (defer)
handleReturnToLastWaypoint() → Not yet in ActionManager
handleHunt()              → Placeholder feature
handleAbandon()           → Session management (keep as-is)
handleLeaderboard()       → Read-only view (keep as-is)
```

### Deleted Methods
```
handleContinue_LEGACY()   ❌
showDayResult()           ❌
handleGameOverResult()    ❌
showGameOver()            ❌
showVictory()             ❌
```

---

## Success Criteria

Migration is complete when:
1. All game actions route through ActionManager
2. All views render through ViewManager
3. No view content depends on routing path
4. All dead code deleted
5. All tests pass
6. Code is simpler and shorter

---

## Start Here

Begin with **Phase 1, Step 2**: Add button mappings to GameController.

Then proceed sequentially through each step, testing after each change.

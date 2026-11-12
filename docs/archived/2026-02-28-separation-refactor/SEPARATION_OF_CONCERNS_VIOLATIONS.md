# Separation of Concerns Violations

**Date:** 2026-02-28
**Status:** Analysis Complete - Refactoring Needed

---

## Summary

The architecture has degraded with multiple violations of separation of concerns between `uranus_game_bot.php` and the classes. The main issues:

1. **GameEngine building keyboards** (should be ViewManager)
2. **uranus_game_bot.php modifying session state** (should be GameEngine)
3. **uranus_game_bot.php formatting messages** (should be ViewManager)
4. **uranus_game_bot.php building keyboards** (should be ViewManager)

---

## Critical Violations

### 1. GameEngine Has 9+ Keyboard Methods ❌

**Location:** `classes/GameEngine.php`

**Methods that violate separation:**
- `getShopKeyboard()` - Line 643
- `getStationChoiceKeyboard()` - Line 712
- `getWaypointKeyboard()` - Line 829
- `getJourneyKeyboard()` - Line 2667
- `getStatusKeyboard()` - Line 2698
- `getMapKeyboard()` - Line 2712
- `getPostEventKeyboard()` - Line 2723
- `getGameOverKeyboard()` - Line 2737
- `getVictoryKeyboard()` - Line 2753

**Why This Is Wrong:**
- Keyboards are VIEW concerns (presentation layer)
- GameEngine should only contain game logic
- Makes GameEngine depend on Telegram button structure
- Violates testability (can't test game logic without Telegram concepts)

**Should Be:** All keyboard building in ViewManager

---

### 2. uranus_game_bot.php Directly Modifying Session State ❌

**Violations Found:**

#### Line 945: `handleLaunch`
```php
$session['status'] = 'in_space';
```

#### Lines 1418-1426: `createNewGameSession`
```php
$session['status'] = 'pre_launch';
$session['preferred_chat'] = $chatId < 0 ? 'group' : 'private';
$session['username'] = $username;
$session['display_name'] = $displayName;
```

#### Lines 1519-1526: `handleStationCallback` (stop action)
```php
$session['status'] = 'at_waypoint';
$session['progress']['speed_multiplier'] = 1.0;
```

#### Lines 1555-1564: `handleStationCallback` (skip action)
```php
$session['at_waypoint'] = null;
$session['progress']['speed_multiplier'] = 2.0;
```

#### Lines 1223-1224: `handlePrivateDM`
```php
$session['preferred_chat'] = 'private';
$session['original_chat_id'] = $chatId;
```

#### Line 1294: `handleBackToGroup`
```php
$session['preferred_chat'] = 'group';
```

**Why This Is Wrong:**
- Game logic scattered across bot handler
- Session manipulation should be in GameEngine (single source of truth)
- Makes it hard to test game rules
- Violates architectural principles

**Should Be:** All session state changes in GameEngine methods

---

### 3. uranus_game_bot.php Building Keyboards ❌

**Violations Found:**

#### Lines 744-753: `handleStart`
```php
'inline_keyboard' => [
    [['text' => '🎮 New Game',     'callback_data' => 'menu|startnewgame']],
    [['text' => '▶️ Continue Game', 'callback_data' => 'menu|continue']],
    [['text' => '❓ How to Play',   'callback_data' => 'menu|help']],
    [['text' => '🏆 Leaderboard',  'callback_data' => 'menu|leaderboard']],
]
```

#### Lines 844-849: `handleHelp`
```php
'inline_keyboard' => [
    [['text' => '🚀 New Journey', 'callback_data' => 'menu|startnewgame']],
    [['text' => '↩️ Back',        'callback_data' => 'menu|main']],
]
```

#### Lines 922-926: `handleNewGame`
```php
'inline_keyboard' => [
    [['text' => '✅ Yes - Launch to Uranus!', 'callback_data' => 'menu|confirmnewgame']],
    [['text' => '❌ Cancel',                   'callback_data' => 'menu|main']],
]
```

#### Lines 1122-1128: `handleAbandon`
```php
'inline_keyboard' => [
    [['text' => '✅ Yes - Abandon Journey', 'callback_data' => 'menu|abandonconfirm']],
    [['text' => '❌ No - Continue Playing', 'callback_data' => 'action|journey']],
]
```

#### Lines 1188-1197: `handlePrivateDM`
```php
'inline_keyboard' => [
    [['text' => '📱 Yes - Move to Private Chat', 'callback_data' => 'menu|confirmprivatedm']],
    [['text' => '🚫 No - Stay in Group',         'callback_data' => 'menu|confirmgroupgame']],
]
```

#### Lines 1214-1221: (inside handlePrivateDM)
```php
'inline_keyboard' => [
    [['text' => '🚀 Launch Ship',      'callback_data' => 'action|launch']],
    [['text' => '❓ How to Play',      'callback_data' => 'menu|help']],
    [['text' => '🔙 Back to Group',    'callback_data' => 'menu|backtogroup']],
]
```

#### Lines 1268-1272: `handleBackToGroup`
```php
'inline_keyboard' => [
    [['text' => '🚀 Launch Ship', 'callback_data' => 'action|launch']],
    [['text' => '❓ How to Play', 'callback_data' => 'menu|help']],
]
```

#### Lines 1444-1449: `createNewGameSession`
```php
'inline_keyboard' => [
    [['text' => '🚀 Launch Ship', 'callback_data' => 'action|launch']],
    [['text' => '❓ How to Play', 'callback_data' => 'menu|help']],
    [['text' => '❌ Cancel',      'callback_data' => 'menu|main']],
]
```

#### Line 1974: `createNewGameKeyboard`
```php
return ['inline_keyboard' => [[['text' => '🚀 New Journey', 'callback_data' => 'menu|startnewgame']]]];
```

**Why This Is Wrong:**
- Presentation logic scattered in webhook handler
- Makes keyboards inconsistent across codebase
- Hard to maintain button layouts
- Violates single responsibility principle

**Should Be:** All keyboard building in ViewManager

---

### 4. uranus_game_bot.php Formatting Messages ❌

**Violations Found:**

#### Lines 1439-1442: `createNewGameSession`
```php
$launchMessage  = "🚀 <b>JOURNEY TO URANUS BEGINS!</b>\n\n";
$launchMessage .= "Your Chad crew is assembled, ship is fueled, and supplies are loaded.\n";
$launchMessage .= "The 2.8 billion kilometer journey awaits!\n\n";
$launchMessage .= "Press <b>Launch Ship</b> to start traveling. Good luck, Captain! 🫡";
```

#### Lines 1568-1572: `handleStationCallback` (skip action)
```php
$message = "➡️ <b>Bypassing {$waypointName}</b>\n\nYou've continued past this waypoint without stopping.\n\n";
if ($wasAlreadyBoosted) {
    $message .= "🌌 <i>Gravitational assist maintained (2× speed continues)</i>";
} else {
    $message .= "🚀 <b>Gravitational Assist Engaged!</b>\n<i>Your speed is now doubled until you stop at another waypoint.</i>";
}
```

#### Line 1537: `handleStationCallback` (stop action)
```php
$message = "⚠️ <i>Gravitational assist lost (speed returned to normal)</i>\n\n" . $message;
```

**Why This Is Wrong:**
- HTML formatting belongs in ViewManager
- Message templates should be centralized
- Makes it hard to maintain consistent styling

**Should Be:** All message formatting in ViewManager

---

## Impact Analysis

### Testability ❌
- Cannot unit test GameEngine without mocking Telegram keyboard structures
- Cannot test game logic in isolation
- Business rules scattered across multiple files

### Maintainability ❌
- Keyboard layouts duplicated in 3 places (bot, GameEngine, ViewManager)
- Session state changes scattered (hard to track game flow)
- Message formatting inconsistent

### Architecture ❌
- Violates MVC-like separation documented in ARCHITECTURE.md
- Single Responsibility Principle violated
- Tight coupling between layers

---

## Recommended Refactoring

### Phase 1: Move Keyboards to ViewManager ✅ PRIORITY 1

**Goal:** All keyboard building in one place

**Steps:**
1. Create ViewManager methods for all keyboard types:
   - `getJourneyKeyboard()`
   - `getShopKeyboard()`
   - `getWaypointKeyboard()`
   - `getStatusKeyboard()`
   - `getMapKeyboard()`
   - `getMainMenuKeyboard()`
   - `getHelpKeyboard()`
   - `getNewGameKeyboard()`
   - etc.

2. Remove keyboard methods from GameEngine

3. Update all keyboard calls in uranus_game_bot.php to use ViewManager

4. Delete inline keyboard building in bot handlers

**Estimated Impact:** ~50 file changes, medium risk

---

### Phase 2: Move Session Logic to GameEngine ✅ PRIORITY 2

**Goal:** All game state changes in GameEngine

**Steps:**
1. Create GameEngine methods:
   - `launchShip($session)` - handles status change to 'in_space'
   - `stopAtWaypoint($session, $waypoint)` - handles waypoint stop logic
   - `skipWaypoint($session, $waypoint)` - handles gravitational assist
   - `setPreferredChat($session, $chatId)` - handles chat preference

2. Update bot handlers to call GameEngine methods instead of direct manipulation

3. Remove all `$session['...'] = ...` lines from uranus_game_bot.php (except metadata like username)

**Estimated Impact:** ~30 file changes, medium risk

---

### Phase 3: Move Message Formatting to ViewManager ✅ PRIORITY 3

**Goal:** All HTML formatting centralized

**Steps:**
1. Create ViewManager template methods:
   - `formatLaunchMessage($session)`
   - `formatWaypointSkipMessage($waypointName, $wasAlreadyBoosted)`
   - `formatSpeedBoostLostMessage()`

2. Update bot handlers to use ViewManager formatting

3. Remove HTML formatting from bot handlers

**Estimated Impact:** ~20 file changes, low risk

---

## Architectural Debt Score

**Current State:** 🔴 **7/10** (Major violations)

- GameEngine has 9 keyboard methods (should have 0)
- Bot handler has 15+ session modifications (should have ~3 for metadata only)
- Bot handler has 10+ keyboard builders (should have 0)
- Bot handler has 5+ message formatters (should have 0)

**Target State:** 🟢 **1/10** (Minor violations acceptable)

- GameEngine: 0 keyboard methods, 0 formatting
- Bot handler: Only routing and Telegram protocol
- ViewManager: All keyboards and formatting
- Metadata assignments in bot (username, display_name) acceptable

---

## Migration Strategy

### Option A: Big Bang Refactor 💥
- Refactor all violations at once
- High risk, fast completion
- Requires comprehensive testing

### Option B: Incremental Refactor ✅ RECOMMENDED
- Phase 1 first (keyboards to ViewManager)
- Test thoroughly
- Phase 2 next (session logic to GameEngine)
- Test thoroughly
- Phase 3 last (message formatting)
- Lower risk, slower but safer

### Option C: Freeze & Document 🧊
- Document violations as "known debt"
- Prevent NEW violations via code review
- Refactor gradually over time
- Safest but slowest

---

## Next Steps

1. **Decide:** Choose migration strategy (recommend Option B)
2. **Plan:** Create detailed task list for Phase 1
3. **Execute:** Refactor keyboards to ViewManager
4. **Test:** Verify all game flows still work
5. **Repeat:** Phases 2 and 3

---

## Conclusion

The separation of concerns has significantly degraded. The codebase has:
- **Game logic in bot handler** (session state changes)
- **View logic in GameEngine** (keyboard building)
- **View logic in bot handler** (message formatting, keyboard building)

This violates the documented architecture (ARCHITECTURE.md) and creates maintenance burden.

**Recommendation:** Execute **Option B** (Incremental Refactor) starting with Phase 1 (keyboards).

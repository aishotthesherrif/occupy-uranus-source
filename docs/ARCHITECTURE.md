# Occupy Uranus - Architecture Documentation

**Last Updated:** 2026-11-11
**Last Verified Against Code:** 2026-11-11
**Status:** Active - Reference for all code changes

For the bot-vs-Mini-App picture and the `GameService` contract, start with [`IMPLEMENTATION_GUIDE.md`](IMPLEMENTATION_GUIDE.md). This document is the rulebook for what each class may and may not do.

---

## Overview

One game, two clients (the Telegram bot and the Mini-App), separated into three kinds of code:

- **`game/`** decides what happens: rules, saves, telemetry. Plain data in and out.
- **`bot/`** and **`mini-app/`** decide how it is shown and what the player can press.
- **`api/game/`** is the Mini-App's thin door into `game/`.

`game/` never imports from the other folders. Everything a client needs to draw a screen is returned as data.

---

## Core Principles

### 1. Separation of Concerns

**CRITICAL:** Each class has ONE responsibility. Never mix concerns.

| Class | Folder | Responsibility | Must NOT |
|---|---|---|---|
| **GameEngine** | `game/` | Game rules; state transitions; win/loss; data queries (status, map, shop items, bands, warnings) | ❌ Text, HTML, emoji sentences ❌ Keyboards ❌ Screen names ❌ File I/O ❌ Telegram |
| **GameService** | `game/` | The single entry point for player actions (`act()`); load, run rule, save, telemetry; read-only queries | ❌ Rules (delegates to GameEngine) ❌ Text ❌ Screen names ❌ Anything client-specific |
| **GameSessionManager** | `game/` | Load / save / delete the per-user save file | ❌ Rules ❌ Text |
| **Telemetry** | `game/` | One log line per game event, tagged with the client | ❌ Rules |
| **BotController** | `bot/` | Button → `GameService::act()` → outcome → screen name, context and flash text | ❌ Rules ❌ Direct saves |
| **ViewManager** | `bot/` | Bot screens: messages, keyboards, image choice | ❌ Rules ❌ Changing the game |
| **GameText** | `bot/` | Bot text for journey, status, station, map, and for action results | ❌ Rules ❌ Changing the game |
| **FormatHelpers** | `bot/` | Display utilities (ETH, distance, bars) | ❌ Rules |
| **BotPreferences** | `bot/` | The bot's own per-player state: chat routing, picture set, show-once notices | ❌ Game state |
| **TelegramAPI** | `bot/` | Telegram HTTP wrapper, image file_id cache | ❌ Content decisions |
| **api/game/** | `api/game/` | Check Telegram sign-in; call `GameService`; return JSON | ❌ Rules ❌ Text ❌ Outcome translation |
| **mini-app/js** | `mini-app/` | Mini-App screens and text (`messages.js`) from outcome codes | ❌ Rules ❌ Game constants |

### 2. Data Flow

```
Bot:       Telegram → uranus_game_bot.php → BotController → GameService → GameEngine
                                                 ↓ outcome + data
                          TelegramAPI ← ViewManager / GameText ← screen choice

Mini-App:  tap → app.js → api/game/action.php → GameService → GameEngine
                              ↓ JSON outcome + data + snapshot
                    messages.js + DOM ← app.js handleOutcome()
```

### 3. Return Value Conventions

**GameEngine rule methods return plain data and reason codes.** No message text:

```php
return [
    'success'       => true,
    'session'       => $session,        // Updated state
    'hull_repaired' => 20,
    'vibes_gained'  => 30,
    'food_consumed' => 15,
];
// refusals:  ['success' => false, 'reason' => 'not_at_station', 'session' => $session]
// endings:   ['game_over' => true, 'reason' => 'starved', 'session' => $session]
```

**GameService::act() returns one shape for every action:**

```php
['session' => array|null, 'outcome' => 'rested', 'data' => ['hull_repaired' => 20, ...]]
```

**BotController produces a screen choice** (internal): `[screen name, context, flash text]`.

**ViewManager::render() returns display data:**

```php
['message' => $html, 'image_url' => $url, 'keyboard' => $inlineKeyboard, 'parse_mode' => 'HTML']
```

---

## Class Responsibilities

### GameEngine (`game/GameEngine.php`)

The brain. Public methods fall into three groups:

- **Rules** (take a session by value, return the new one in `result['session']`): `advanceDay`, `processEventChoice`, `rest`, `huntResources`, `returnToLastWaypoint`, `purchaseItem`, `stopAtWaypoint`, `skipWaypoint`, `selectStationContent`, `createNewGame`, `checkGameOver`, `saveToLeaderboard`.
- **Data queries** (read-only): `getStatusDetails`, `getMapData`, `getShopItems`, `getVibesBand`, `getHullBand`, `getCriticalWarnings`, `getNextWaypoint`, `getProgressPercent`, `calculateDaysRemaining`, `calculateReturnCosts`, `getEventById`, `describeEvent`, `getLeaderboard`.
- **Content helpers**: crew roster, quotes, templates (`replaceChadTemplates`).

Bands and warnings are **codes** (`'high'`, `'minor_damage'`, `'fuel_low'`); each client words them.

Random numbers are drawn only inside rules and content selection, never while formatting, so seeded replays are exact (see the golden-run tool).

### GameService (`game/GameService.php`)

See [`IMPLEMENTATION_GUIDE.md`](IMPLEMENTATION_GUIDE.md#the-contract-gameservice). `runRule()` is the pure, file-free core used by tests; `act()` adds load, save and telemetry.

### BotController (`bot/BotController.php`)

`route($button, $userId, $params)`: maps a button to an action (`BUTTON_ACTIONS`), calls `act()`, and `screenFor()` maps the outcome to a screen. Buttons that only show a screen (status, map, shop view) skip the action and read state. It also adds the bot's own aesthetic to the display copy of the save.

### ViewManager and GameText (`bot/`)

`ViewManager::render($view, $session, $context)` builds screens; `GameText` holds the larger text builders (journey, status, station, map, and result text such as `eventResultText`, `launchText`, `rejectionText`, `gameOverText`). Both only read the game; neither saves.

### api/game (`api/game/`)

`state.php` (read) and `action.php` (act) check the Telegram signature with `auth.php`, call `GameService`, and answer with the result plus a snapshot (`session`, `status`, `map`, `shop`, `return_costs`, `pending_event`, `ending`). No action-name or outcome translation tables. Details in [`../api/game/README.md`](../api/game/README.md).

---

## Common Violations & Fixes

### ❌ Text in GameEngine

**Wrong:**
```php
// In GameEngine::rest()
$message = "😴 <b>Rest Day</b>\n🛡️ Hull repaired: +{$hull}%";
return ['message' => $message, ...];
```

**Correct:**
```php
// GameEngine returns data
return ['success' => true, 'session' => $session, 'hull_repaired' => 20, 'vibes_gained' => 30, 'food_consumed' => 15];
// BotController → GameText::restText($data) writes the bot's sentence;
// the Mini-App's messages.js writes its own.
```

### ❌ Game rules in a client

**Wrong:** the Mini-App computing `fuel / 5` for days remaining, or the bot deciding a hull below 30 is "critical".

**Correct:** the engine returns `days` and `band`/`warnings`; the client words them. Thresholds live in one place (`GameEngine`).

### ❌ A client talking to the engine or the save directly

**Wrong:** the bot calling `$gameEngine->purchaseItem()` and `$sessionManager->saveSession()`.

**Correct:** `$game->act($userId, 'purchase', ['item' => 'fuel'], 'bot')`. Then the Mini-App, telemetry and the leaderboard all see the same move.

### ❌ Client-only state in the save

**Wrong:** `$session['preferred_chat']`, `$session['aesthetic']`.

**Correct:** `BotPreferences`. A game started in the Mini-App must work in the bot with no bot setup.

### ❌ Sending raw events to a browser

Events contain outcome tables. Use `GameEngine::describeEvent()` (the API does).

---

## Standard Pattern: Adding a Bot Handler

**View-only handler (3 lines):**
```php
private function handleMyView($userId, $chatId, ...) {
    return $this->routeViaArchitecture('my_view', $userId, $chatId, [], ...);
}
```

**Action with a validation guard:**
```php
private function handleMyAction($userId, $chatId, ...) {
    $session = $this->loadSession($userId);                 // display copy
    if ($session === null) { return $this->sendNoSessionMessage(...); }
    if ($session['status'] !== 'required_status') { return $this->sendMessage(...); }
    return $this->routeViaArchitecture('my_action', $userId, $chatId, [], ...);
}
```

**New action end to end:**
1. `GameEngine`: the rule (data + reason codes). `GameService::runRule()`: add the case and map the result to an outcome. Document the outcome.
2. Bot: add the button to `BotController::BUTTON_ACTIONS`, the outcome to `screenFor()`, the text to `GameText`, and route the callback in `handleMenuCallback` / `handleActionCallback`.
3. Mini-App: add the case to `handleOutcome()` and the words to `messages.js`.
4. Tests: golden run (rules unchanged), service/API/bot smoke tests, E2E.

---

## Refactoring Checklist

- [ ] **GameEngine has NO text** (no HTML, no sentences, no keyboards, no screen names)
- [ ] **`game/` imports nothing from `bot/`, `api/`, `mini-app/`** (`grep -rn "bot/\|api/" game/`)
- [ ] **Clients change the game only via `GameService::act()`**
- [ ] **ViewManager / GameText never change game state**
- [ ] **Client-only state is in `BotPreferences` (or the Mini-App), not in the save**
- [ ] **API responses contain no HTML and no event outcome tables** (`api_smoke.php` checks)
- [ ] **The Mini-App contains no game constants** (days, caps and prices come from `status` and `shop`)
- [ ] **SessionManager only does I/O; TelegramAPI is a thin wrapper**

## Testing Separation

1. Can you play a whole game with no Telegram? ✅ `GameService::runRule()` / `tools/golden_run.php`
2. Can you change bot wording without touching `game/`? ✅
3. Can you change a rule without touching `bot/` or `mini-app/`? ✅ (unless it adds a new outcome)
4. Can the Mini-App start, play and finish a game with no bot? ✅ `tools/mini_app_e2e.js`

If any answer is "No", you have a separation problem.

---

## Decision Log

### 2026-11-11: Untangled the bot and the Mini-App
**Issue:** The engine built Telegram HTML; the shared action layer answered in bot screen names (including a bot-only platform-selection screen); the bot bypassed the shared layer for new games, shop, docking and telemetry; bot routing fields lived in the save; the Mini-App could not start a game or shop, drew from copies of game constants and a mock that did not match the server (empty crew, ETH shown as cents), and received events with their hidden outcomes.
**Decision:** One neutral `GameService` (data in, data out) is the only door. `GameEngine` returns data and reason codes; bot text moved to `bot/GameText.php`; `ActionManager` and `GameController` were replaced by `GameService` and `BotController`; bot-only state moved to `BotPreferences`; the API became a thin pass-through; the Mini-App became a full client with its own text.
**Verification:** Seeded games compared move by move before and after (60 games, 7,736 moves, 24 victories: identical); bot screens compared (identical except launch, game-over reason, station story stability); API, bot and browser end-to-end tests.
**Behaviour changes (intended):** launch goes straight to the journey screen; `/miniapp`, Mini-App buttons and the platform-selection screen are gone; game-over screens show the real cause instead of "Unknown"; the station story chosen at docking stays the same when the screen is redrawn; chat preference and picture set are per player in `BotPreferences`.

### 2026-03-03: Completed GameController refactoring (Phase 1-3)
**Issue:** Handler code duplication - 28 handlers had repeated session loading, view rendering, and message sending patterns.
**Decision:** Migrated 15 handlers to a unified entry point (now `BotController`, then `GameController`). Superseded by the 2026-11-11 change; the handler pattern above is the same.

### 2026-03-02: Added 'at_station' status for clarity
**Issue:** `'at_waypoint'` status was semantically overloaded - used for both "at threshold deciding" and "stopped at station using services".
**Decision:** Added 4th status value `'at_station'`: `'at_waypoint'` = crossed threshold, showing the stop/skip decision; `'at_station'` = chose to stop, using services.
**Commit:** `011c16f`

### 2026-03-02: Moved waypoint state clearing to GameEngine
**Decision:** `GameEngine->advanceDay()` clears `at_waypoint` when leaving a station; the orchestration layer no longer pre-clears state. All state transitions belong to the engine.
**Commit:** `8880fe1`

### 2026-02-27: Moved formatResourcesSummary to ViewManager
**Decision:** Moved to ViewManager, GameEngine returns raw data.
**Commit:** `0638d61`

---

**Guiding principle:** When in doubt, move toward more separation, not less.

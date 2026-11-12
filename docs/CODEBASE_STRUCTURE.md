# Codebase Structure Reference

**Last Updated:** 2026-11-11
**Last Verified Against Code:** 2026-11-11

Where code lives, what loads what, and where to add things. For the rules that keep the pieces apart see [`ARCHITECTURE.md`](ARCHITECTURE.md); for the bot-vs-Mini-App picture see [`IMPLEMENTATION_GUIDE.md`](IMPLEMENTATION_GUIDE.md).

---

## Overview

A space survival game played from **two clients** that share **one game**:

- the **Telegram bot** (PHP webhook, `uranus_game_bot.php`)
- the **Mini-App** (HTML/JS in the browser, talking to a small PHP API)

**State:** one JSON save per player in `logs/sessions/`, written only by `game/`.

---

## Layout

```
uranus_game_bot.php        Webhook entry point (stays at the root so the webhook URL never changes)
config.php                 Constants and paths (no logic)
app.html                   Redirect used by BotFather's menu button → mini-app/app-v1.html

game/                      THE GAME. No Telegram, no HTML, no screen names.
├── GameService.php          The one door: act() and read-only queries
├── GameEngine.php           Rules and data queries
├── GameSessionManager.php   Load/save/delete the per-player save
└── Telemetry.php            Event log lines, tagged by client

bot/                       THE TELEGRAM CLIENT
├── BotController.php        Button → action → screen
├── ViewManager.php          Bot screens: messages, keyboards, images
├── GameText.php             Bot text builders (journey, status, station, map, results)
├── BotPreferences.php       Bot-only per-player state (chat routing, picture set)
├── FormatHelpers.php        Display utilities
└── TelegramAPI.php          Telegram HTTP wrapper

api/game/                  THE MINI-APP'S DOOR (thin)
├── bootstrap.php            Shared setup, snapshot builder
├── auth.php                 Telegram initData verification, JSON helpers
├── state.php                GET  the player's game
└── action.php               POST one action

mini-app/                  THE BROWSER CLIENT
├── app-v1.html              Active page (markup + styles)
└── js/
    ├── app.js               Flow: load, resume, act, outcome → screen
    ├── api.js               HTTP client for api/game/
    ├── messages.js          All Mini-App text, from outcome codes
    ├── data-binder.js       Draws the dashboard from server data
    ├── formatters.js        Display formatting only
    ├── dom.js               Safe DOM builder
    ├── telegram-sdk.js      Telegram Web App wrapper
    └── web-player.js        Captain-name sign-in for plain browsers (honor system)

data/                      Game content (JSON) and leaderboards
tools/                     Test and verification tools (see IMPLEMENTATION_GUIDE.md)
test_framework/            Mock Telegram + testable bot used by tools/bot_smoke.php
media/                     Images per aesthetic
logs/                      Runtime: sessions, bot prefs, message ids, logs, telemetry
docs/                      Documentation (not deployed)
```

`mini-app/option*.html`, `index.html`, `test-local.html` and `style.css` are earlier prototypes and are not loaded by the app.

---

## Dependency Rules

```
config.php          ← everything (constants only)

game/*              depends on: config.php, data/*.json         (nothing else)
bot/*               depends on: game/* (read-only queries, GameService), config.php
api/game/*          depends on: game/*, config.php               (never bot/)
mini-app/*          depends on: api/game over HTTP               (no PHP, no game rules)
uranus_game_bot.php loads game/* and bot/*
```

Check the first rule any time: `grep -rn "bot/\|api/\|Telegram" game/` should only find comments.

Who loads what: the entry points (`uranus_game_bot.php`, `api/game/bootstrap.php`, tools) `require_once` the files they need; classes do not `require` each other.

---

## File-by-File Reference

### uranus_game_bot.php
Webhook entry and routing. Parses updates, rate-limits, routes commands and callbacks, tracks message ids for cleanup, enforces per-button owner stamps, sends everything through its `sendMessage()` wrapper (message tracking, owner stamping, header). Builds `GameService`, `BotController`, `ViewManager`, `BotPreferences` in its constructor. Contains no game rules.

### game/GameEngine.php
Rules (`advanceDay`, `processEventChoice`, `rest`, `huntResources`, `returnToLastWaypoint`, `purchaseItem`, `stopAtWaypoint`, `skipWaypoint`, `selectStationContent`, `createNewGame`, `checkGameOver`, leaderboard) and read-only data queries (`getStatusDetails`, `getMapData`, `getShopItems`, bands, warnings, `describeEvent`). Returns data and reason codes only. Loads `data/events.json`, `waypoints.json`, `crew_personalities.json`.

### game/GameService.php
`act()` runs one action against the player's save (load, rule, save, telemetry). `runRule()` is the pure core. Queries: `getState`, `getShopCatalog`, `getStatusDetails`, `getMapData`, `getReturnCosts`, `getPendingEvent`, `getEnding`, `getLeaderboard`, operator tools. The class comment lists every action, outcome and reason code.

### game/GameSessionManager.php
Per-user session files with locking. Only `GameService` uses it.

### game/Telemetry.php
Appends JSON lines to `logs/telemetry.jsonl` when `TELEMETRY_ENABLED`. Each line has a `client` (`bot` / `mini-app`). Read by `telemetry_analyzer.php`.

### bot/BotController.php
`route($button, $userId, $params)`; `BUTTON_ACTIONS` maps buttons to actions; `screenFor()` maps outcomes to screens; `loadSession()` gives the display copy of the save with the bot's aesthetic added (never saved).

### bot/ViewManager.php
`render($view, $session, $context)` for every bot screen, keyboards, image selection, and the formatting for start, shop, help, leaderboard, and so on. Views: journey, status, map, event, shop, waypoint, waypoint_skipped, station, post_event, return_confirm, game_over, victory, aesthetic, start, main_menu, launch_sequence, abandon_confirm.

### bot/GameText.php
Larger text builders (journey, detailed status, station, ASCII map) and result text (`eventResultText`, `launchText`, `restText`, `returnText`, `daySummaryText`, `rejectionText`, `gameOverText`).

### bot/BotPreferences.php
`logs/bot_prefs/<user_id>.json`: where the bot replies (`chat`, `original_chat_id`), `aesthetic`, and `resource_changes` shown once on the next Journey screen.

### bot/FormatHelpers.php
Static display helpers (ETH to 3 decimals, distance, velocity, resource lines, progress bar). Used by `bot/` only.

### bot/TelegramAPI.php
cURL wrapper for the Bot API with image `file_id` caching. No content decisions.

### api/game/*
See [`../api/game/README.md`](../api/game/README.md).

### mini-app/js/*
See [`mini-app/README.md`](mini-app/README.md).

---

## Data Files

### Game Content

| File | Purpose | Loaded By |
|------|---------|-----------|
| `data/events.json` | Random events (choices with hidden outcomes, crew reactions) | GameEngine |
| `data/waypoints.json` | Waypoints with local characters and stories | GameEngine |
| `data/crew_personalities.json` | Crew names, traits and quotes (`CREW_PERSONALITIES_FILE`) | GameEngine |
| `data/crew_activities.json` | Crew "currently doing" lines. **Not loaded**: the engine looks for `chad_activities.json` (see the guide's known leftovers) | none |
| `data/items.json` | Reserved for shop expansion (shop items are in `GameEngine::getShopItems()`) | none |
| `data/aesthetics.json` | Picture sets | ViewManager |
| `data/image_config.json` | Image selection rules | ViewManager |
| `data/leaderboards.json` | Leaderboards (runtime, not deployed) | GameEngine |

### Runtime State

| File | Purpose | Written By |
|------|---------|------------|
| `logs/sessions/{userId}.json` | The player's game | GameSessionManager (via GameService) |
| `logs/bot_prefs/{userId}.json` | Bot-only preferences | BotPreferences |
| `logs/message_ids/{userId}.json` | Message cleanup tracking | uranus_game_bot.php |
| `logs/bot.log`, `logs/diagnostic.log` | Logs | bot |
| `logs/telemetry.jsonl` | Event analytics (disabled by default) | Telemetry |

---

## Not deployed

`deploy.sh` excludes: `docs/`, `tools/`, `test_framework/`, `notes/`, `.archive/`, `logs/`, deploy and setup scripts, `local_game_tester.php`, `telemetry_analyzer.php`, `test_*`, and the project documentation files at the root.

`setup.sh` is a legacy scaffolding script that still describes the old `classes/` layout.

---

## Adding New Features

See [`ARCHITECTURE.md`](ARCHITECTURE.md#standard-pattern-adding-a-bot-handler) for the end-to-end checklist (engine → service → bot → Mini-App → tests) and [`IMPLEMENTATION_GUIDE.md`](IMPLEMENTATION_GUIDE.md#adding-or-changing-a-feature).

## Critical Patterns

**Use the returned session.** Rules take a session by value; the new state is in `$result['session']`.

**Never bypass the bot's `sendMessage()` wrapper** (message tracking, owner stamping, header). In the bot, chat routing comes from `BotPreferences`.

**Never change the game outside `GameService::act()`.**

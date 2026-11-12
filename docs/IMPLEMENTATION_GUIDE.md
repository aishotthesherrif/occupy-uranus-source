# Occupy Uranus Implementation Guide

**Purpose:** The map of how the Telegram bot, the Mini-App and the game fit together, and the rules that keep them apart.
**Last verified against code:** 2026-11-11

## The shape

There is **one game** and **two equal clients**. Neither client contains game rules, and neither depends on the other.

```
 Telegram bot                              Mini-App (browser)
 uranus_game_bot.php                       mini-app/js/app.js
   BotController, ViewManager, GameText      messages.js (all its text)
          │                                          │ HTTPS JSON
          │ act() / getState()                       ▼
          │                                  api/game/  (sign-in check, thin)
          └─────────────────┬──────────────────────┘
                            ▼
                     game/GameService       ← the only door to the game
                  ┌─────────┴─────────┐
            GameEngine          GameSessionManager      Telemetry
            (rules)             (one save per player)   (tagged by client)
```

- **One saved game per Telegram user**, in `logs/sessions/<user_id>.json`. Start in the bot, continue in the Mini-App, finish in the bot: both read and write the same save through the same rules.
- **Both clients can do everything that matters**: start a journey, shop, launch, play days, answer events, dock, rest, read status, turn back, reach an ending, abandon. Features that exist in only one client are listed under [Known leftovers](#known-leftovers).
- **The game knows nothing about screens.** `game/` contains no Telegram, no HTML, no emoji text, no screen names. Each client turns results into its own screens and words.

## The contract: `GameService`

Defined in [`../game/GameService.php`](../game/GameService.php) (its class comment is the reference).

```php
$result = $game->act($userId, $action, $params, $client);
// ['session' => array|null, 'outcome' => string, 'data' => array]
```

`act()` loads the player's save, runs one rule, saves, records telemetry and returns plain data. `$client` is `'bot'` or `'mini-app'` and is only used to tag telemetry.

| Action | Params | Outcomes it can produce |
|---|---|---|
| `new_game` | `username`, `display_name` | `game_created` |
| `abandon` | none | `abandoned` |
| `launch` | none | `launched` |
| `advance_day` | none | `day_advanced`, `event_triggered`, `waypoint_reached`, `game_over`, `victory` |
| `hunt` | none | `hunted`, `event_triggered`, `game_over` |
| `rest` | none | `rested`, `game_over` |
| `process_event` | `event_id`, `choice_id` | `event_resolved`, `game_over` |
| `stop_at_waypoint` / `skip_waypoint` | none | `docked` / `waypoint_skipped` |
| `return_to_waypoint` | none | `returned`, `game_over` |
| `purchase` | `item` (`fuel`, `food`, `repair`) | `purchased` |

Any action can instead produce **`rejected`**: nothing happened, and `data.reason` is a code, never a sentence: `no_session`, `session_exists`, `not_launched`, `not_at_station`, `not_in_space`, `at_waypoint`, `no_pending_arrival`, `event_not_found`, `choice_not_found`, `insufficient_eth`, `invalid_item`, `invalid_params`, `unknown_action`.

`game_over` carries `data.reason` as one of `out_of_fuel`, `starved`, `crew_lost`, `hull_breached`.

`data` holds numbers, ids and names, for example `day_advanced` gives `distance_traveled`, `fuel_consumed`, `food_consumed`, `vibes_state`, `deserter`, `slingshot`, `milestone`. Each client decides what to say about them.

Read-only queries on the same service: `getState`, `getStatusDetails`, `getMapData`, `getShopCatalog`, `getReturnCosts`, `getPendingEvent`, `getEnding`, `getLeaderboard`, plus operator tools (`getActivePlayers`, `countGames`, `deleteGame`, `deleteAllGames`) for the bot's admin screens.

## Rules that keep it untangled

1. **`game/` imports nothing from `bot/`, `api/` or `mini-app/`.** It reads `config.php` and `data/*.json` only.
2. **Rules return data.** No message strings, no HTML, no screen names, no reason sentences. Add a code and let each client word it.
3. **Clients change the game only through `GameService::act()`.** The bot does not call `GameEngine` mutators or `GameSessionManager`; the API does not either.
4. **Client-only state stays out of the save.** The bot's chat routing (private or group), picture set and show-once notices live in [`../bot/BotPreferences.php`](../bot/BotPreferences.php) (`logs/bot_prefs/`). The save holds game state only.
5. **Hidden information stays on the server.** `GameEngine::describeEvent()` gives a client an event without its outcome tables; the API uses it. Never send raw events to a browser.
6. **Game numbers and limits are served, not copied.** The Mini-App takes days remaining, percentages, shop prices, caps and crew from the API's `status` and `shop`. It must not contain game constants.

## Where things live

| Concern | Telegram bot | Mini-App | Shared? |
|---|---|---|---|
| Entry | [`../uranus_game_bot.php`](../uranus_game_bot.php) (webhook) | [`../mini-app/app-v1.html`](../mini-app/app-v1.html) | no |
| Which screen follows which outcome | [`../bot/BotController.php`](../bot/BotController.php) | [`../mini-app/js/app.js`](../mini-app/js/app.js) (`handleOutcome`) | no |
| Text | [`../bot/GameText.php`](../bot/GameText.php), [`../bot/ViewManager.php`](../bot/ViewManager.php) | [`../mini-app/js/messages.js`](../mini-app/js/messages.js) | no |
| Client-only state | [`../bot/BotPreferences.php`](../bot/BotPreferences.php) | none | no |
| Sign-in | Telegram webhook | initData, checked in [`../api/game/auth.php`](../api/game/auth.php) | Telegram identity (`user_id`) |
| Game actions | `GameService` | `GameService` via [`../api/game/action.php`](../api/game/action.php) | **yes** |
| Rules | [`../game/GameEngine.php`](../game/GameEngine.php) | same | **yes** |
| Saves | [`../game/GameSessionManager.php`](../game/GameSessionManager.php) | same | **yes** |
| Content | [`../data/`](../data/) | same (served through the API) | **yes** |

## Request flows

```text
Bot:      Telegram update → uranus_game_bot.php → BotController::route()
            → GameService::act() → GameEngine + save + telemetry
            → outcome + data → BotController picks the screen
            → ViewManager / GameText write the text → TelegramAPI sends it

Mini-App: tap → app.js → api.js POST api/game/action.php
            → auth.php checks Telegram's signature → GameService::act()
            → JSON {outcome, data, session, status, map, shop, ...}
            → app.js handleOutcome() → messages.js words → DOM
```

The Mini-App finds the API relative to its own page (`../api/game/`), so it works under any deploy folder.

## Adding or changing a feature

1. **A game rule or number** → `GameEngine` (return data and a reason code) and, if it is a new player action, `GameService::act()`. Document the outcome in `GameService`'s class comment. Read [`GAME_MECHANICS_REVIEW.md`](GAME_MECHANICS_REVIEW.md) first: balance is playtested.
2. **Telling players about it** → once per client: bot text in `GameText`/`ViewManager` plus the screen choice in `BotController::screenFor()`; Mini-App text in `messages.js` plus `handleOutcome()` in `app.js`.
3. **A screen's data** → add to `GameEngine::getStatusDetails()` (or a sibling query), expose it in `api/game/bootstrap.php::gameSnapshot()`, use it in the client. Do not compute rules in a client.
4. Run the tests below. The golden run proves you did not change the rules; the smoke tests prove both clients still work.

## Testing

All tools are in [`../tools/`](../tools/). Run them from a **copy of the repo** if you do not want `logs/sessions/` touched (each uses a throwaway user id and deletes its game).

| Tool | Proves | Run |
|---|---|---|
| `golden_run.php` + `golden_compare.php` | Rules are unchanged: seeded games, saved state after every move, compared before vs after a refactor | `php tools/golden_run.php before.json 60 800`, change code, `php tools/golden_run.php after.json 60 800`, `php tools/golden_compare.php before.json after.json` |
| `golden_screens.php` | The bot's screens (text and keyboards) are unchanged | same pattern; run in a copy without `media/` |
| `service_smoke.php` | `GameService::act()` end to end: lifecycle, errors, no HTML in results | `php tools/service_smoke.php` |
| `api_smoke.php` | API over HTTP: Telegram and captain-name sign-in checks, every endpoint, no hidden outcomes leaked, bot and API see one game | `php tools/api_smoke.php` |
| `bot_smoke.php` | Whole bot through webhook updates and real keyboards, chat preferences, abandon | `php tools/bot_smoke.php` |
| `mini_app_e2e.js` | Mini-App in headless Chrome: play through the real buttons, both endings, abandon, then a plain-browser captain sign-in | `npm i puppeteer-core` somewhere, then `NODE_PATH=…/node_modules node tools/mini_app_e2e.js [screenshot-dir]` |

Also: `php -l` on changed PHP and `node --check` on changed JS.

**Opening the Mini-App outside Telegram** (`php -S 127.0.0.1:8080`, then `/mini-app/app-v1.html`, or `/` which redirects there) asks for a captain name and plays the real game as a web player. Telegram-only paths (the bot, `initData` sign-in) need Telegram via an HTTPS tunnel (see [`mini-app/TESTING_GUIDE.md`](mini-app/TESTING_GUIDE.md)).

## Deploying

[`../DEPLOYMENT.md`](../DEPLOYMENT.md) has the process. Things specific to this layout:

- `deploy.sh` uses `rsync --delete`: the old `classes/` and `api/mini-app/` folders disappear from the server. Deploy the bot, `game/`, `bot/`, `api/game/` and `mini-app/` **together**, or the Mini-App and the bot will not match.
- Players mid-game keep their journey. The bot's chat preference (and picture set) restart from defaults, and a player who is docked at the moment of deploy sees the station without its story until they next dock.
- The bot no longer promotes the Mini-App. Players open it through the menu button set in BotFather (private chats only), which points at `app.html`, which redirects to `mini-app/app-v1.html`.

## Known leftovers

Not bugs introduced by the split, but worth deciding:

- **Finished games stay saved** until the player abandons them (bot and Mini-App behave the same; the Mini-App shows the ending screen and offers a new game).
- **The bot's day summary is just "Day complete!"**, as before. `day_advanced` data already carries distance, fuel, food, desertions, milestones and slingshots; `GameText::daySummaryText()` is the one place to show them.
- **Crew "currently doing" text never varies**: `GameEngine::loadChadActivities()` reads `data/chad_activities.json`, but the repo has `data/crew_activities.json`, so every crew member is always "Performing routine duties".
- **Bot-only features**: aesthetics (picture sets), `/leaderboard`, `/frens`, help, the ASCII map screen. **Mini-App-only**: none. The API already serves map and leaderboard data (`getMapData`, `getLeaderboard`) if the Mini-App should grow screens for them.
- **Event text names crew at random** (`describeEvent`), so reopening an unanswered event may name different crew. The bot behaves the same way.
- `setup.sh` still describes the pre-split layout and was not updated.

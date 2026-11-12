# Mini-App Documentation

**Last Updated:** 2026-11-11
**Last Verified Against Code:** 2026-11-11

The Mini-App is the browser client of the game. It is a **full client**: it can start a journey, shop, launch, play days, answer events, dock, rest, read status, turn back, reach either ending and abandon, with no help from the bot. It shares one saved game per player with the Telegram bot. It contains **no game rules**: the server decides everything and the Mini-App draws and words the results.

Read first: [`../IMPLEMENTATION_GUIDE.md`](../IMPLEMENTATION_GUIDE.md) (how it fits with the bot and `GameService`) and [`../../api/game/README.md`](../../api/game/README.md) (the API it talks to).

---

## Files

| File | Role |
|---|---|
| [`mini-app/app-v1.html`](../../mini-app/app-v1.html) | The page: cockpit dashboard, overlays (start page, event, waypoint, general panel, endings), styles |
| `js/app.js` | Flow. `init()` → `resume()` shows whatever the game is waiting for; `act()` sends an action; `handleOutcome()` draws the result |
| `js/api.js` | `getState()` / `act()` against `../api/game/` (relative, so any deploy folder works); `ApiError` |
| `js/messages.js` | **All text** about what happened, written from outcome codes and data. The bot has its own wording in `bot/GameText.php` |
| `js/data-binder.js` | Draws the dashboard from the server's `status` and `shop`; shows only the controls that fit the situation |
| `js/formatters.js` | Display formatting only (distance, ETH to 3 decimals, velocity) |
| `js/dom.js` | `el()`: DOM builder that inserts text as text, never as HTML |
| `js/telegram-sdk.js` | Telegram Web App wrapper (initData, `isTelegram()`, haptics, close) |
| `js/web-player.js` | A web player's captain name (honor-system sign-in), remembered in `localStorage` |

`index.html`, `option*.html`, `test-local.html` and `style.css` in `mini-app/` are earlier prototypes, not part of the app.

---

## How it works

```
open → (plain browser, no stored name → sign-in page) → api.getState() → resume():
          no game             → start page (New Journey)
          finished game       → victory / game over screen
          pending event       → event modal
          pending waypoint    → stop / fly past modal
          pre_launch          → launch pad panel (shop + Launch Ship)
          otherwise           → dashboard

tap → api.act(action, params) → {outcome, data, ...snapshot}
        → handleOutcome(): panel / modal / flash message, dashboard redrawn from the snapshot
```

- **Snapshot:** every response carries `session`, `status`, `map`, `shop`, `return_costs`, `pending_event`, `ending`. The dashboard is drawn from these; nothing is calculated in the browser (days remaining, percentages, caps, prices and crew all arrive ready).
- **Panels** (`showPanel`): launch pad, station (story, shop, rest, depart), status, event result, turn-back and abandon confirmations. The station and launch panels redraw after purchases.
- **Controls** follow the game: Hunt and Turn Back in space; Rest and Shop at a station; Continue becomes Depart at a station; Status and Abandon always.
- **Polling:** every 10 s while idle it re-reads the game, so moves made in the bot appear (including a waiting event).
- **Busy state:** while a request is in flight `body.busy` makes every button inert.
- **Two ways to sign in.** Inside Telegram, Telegram signs the player in (`initData`). In a plain browser the page asks for a **captain name** (honor system, no password) and sends it as `player`; the same name opens the same game on any device, for anyone who types it. The name is kept in `localStorage`; **Switch captain** (start page, Status panel) forgets it. Web games are separate from Telegram games and never appear in the bot.

## Adding a feature

1. The rule and its outcome/data come from the server first (see the guide's checklist).
2. Add the outcome to `handleOutcome()` in `app.js` and its words to `messages.js`.
3. If it needs numbers on screen, ask for them in `GameEngine::getStatusDetails()` / `api/game/bootstrap.php::gameSnapshot()` instead of computing them here.
4. Keep strings out of `app.js`; build DOM with `el()`, never with HTML strings.

## Testing

| Check | How |
|---|---|
| Syntax | `node --check mini-app/js/*.js` |
| Whole app, real browser | `node tools/mini_app_e2e.js [screenshots-dir]` (needs `puppeteer-core`; see the guide's Testing section). Plays through every button, both endings, abandon; fails on browser errors or stray markup/`undefined`/`NaN` on screen |
| Look at it without Telegram | `php -S 127.0.0.1:8080`, open `/mini-app/app-v1.html` (read-only sample game) |
| In real Telegram | HTTPS tunnel and BotFather menu button: [`TESTING_GUIDE.md`](TESTING_GUIDE.md) |

Opening the Mini-App: BotFather's menu button (private chats) points at `app.html`, which redirects to `mini-app/app-v1.html`. The bot no longer sends Mini-App buttons or a `/miniapp` command.

## Not in the Mini-App yet

Map screen, leaderboard, who's playing, help, aesthetics (picture sets). The API already serves map and leaderboard data.

## History

Earlier planning and reference docs (written when the Mini-App was a partial client of mock data) are in `../archived/2026-03-07-mini-app-v1/` and `../archived/2026-11-11-untangle/`.

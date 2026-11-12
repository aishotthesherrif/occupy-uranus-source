# Game API (for the Mini-App)

**Purpose:** The Mini-App's door into the game. Two endpoints, JSON in and out.
**Sign-in:** Telegram Web App `initData`, verified with the bot token, **or** a web player's captain name (`player`, honor system, no password). See [Sign-in](#sign-in).
**Rules:** none. Every endpoint checks who is asking, calls `game/GameService`, and returns what it says. No action-name or outcome translation.

The authoritative list of actions, outcomes and reason codes is the class comment in [`../../game/GameService.php`](../../game/GameService.php). Overview: [`../../docs/IMPLEMENTATION_GUIDE.md`](../../docs/IMPLEMENTATION_GUIDE.md).

---

## Files

| File | Role |
|---|---|
| `bootstrap.php` | Loads `config.php` and `game/*`, builds the service, `authenticate()`, `gameSnapshot()` |
| `auth.php` | `validateTelegramWebAppData()` (HMAC check, 24 h expiry), `webPlayerFromName()` (captain name → player), `sendJsonResponse()`, `sendErrorResponse()`, CORS preflight |
| `state.php` | Read the player's game |
| `action.php` | Run one action |

Clients reach these relative to their own page (`../api/game/` from `mini-app/`), so any deploy folder works.

---

## Sign-in

Every request carries exactly one of:

| Field | Who | Checked how |
|---|---|---|
| `init_data` | Telegram (Mini-App inside Telegram) | HMAC with the bot token, at most 24 h old |
| `player` | Web player (plain browser) | Honor system: 2–20 letters/numbers, spaces and `. _ ' -`. No password |

`init_data` wins when both are sent. A captain name becomes a user id derived from the name (case and extra spaces ignored), so the same name always opens the same game, from any device, for anyone who types it. Web user ids are **negative**; Telegram user ids are positive, so a web player can never open a Telegram player's game and the bot never sees web games. A web game's leaderboard name is the captain name.

---

## GET `state.php?init_data=<initData>` · `state.php?player=<name>`

The signed-in player's game. Answers **200** with `has_session: false` when they have none.

```json
{
  "success": true,
  "has_session": true,
  "session":       { "...the saved game..." },
  "status":        { "...GameEngine::getStatusDetails()..." },
  "map":           { "...GameEngine::getMapData()..." },
  "shop":          { "eth": 14, "items": [ { "id": "fuel", "price": 1, "quantity": 100, "resource": "fuel", "current": 400, "max": 1200, "affordable": true, "full": false } ] },
  "return_costs":  { "last_waypoint": {}, "fuel_cost": 10, "food_cost": 30, "backtrack_km": 0, "days": 2 },
  "pending_event": null,
  "ending":        { "game_over": null, "victory": false }
}
```

`pending_event` is the event awaiting an answer, **without outcome tables**. `ending.game_over` is a reason code or `null`; `ending.victory` is true at Uranus.

---

## POST `action.php`

Body:

```json
{ "init_data": "<initData>", "action": "advance_day", "params": {} }
```

(Web players send `"player": "<captain name>"` instead of `init_data`.)

Actions: `new_game`, `abandon`, `launch`, `advance_day`, `hunt`, `rest`, `process_event` (`event_id`, `choice_id`), `stop_at_waypoint`, `skip_waypoint`, `return_to_waypoint`, `purchase` (`item`).

`new_game` takes the player's name from their sign-in (Telegram's signed data, or the captain name); any name in `params` is ignored.

Response: the result plus a fresh snapshot (same keys as `state.php`):

```json
{
  "success": true,
  "outcome": "purchased",
  "data": { "item": "fuel", "cost": 1, "quantity_requested": 100, "quantity_added": 100, "capped": false },
  "has_session": true, "session": {}, "status": {}, "map": {}, "shop": {}, "return_costs": {}, "pending_event": null, "ending": {}
}
```

- **`outcome`** and **`data`** are exactly `GameService`'s. A move the rules refuse is still a successful request: `outcome: "rejected"`, `data.reason` explains (for example `insufficient_eth`, with `cost` and `eth_available`).
- After `abandon`, `has_session` is false and the snapshot fields are `null`.
- Events in `data.event` (`event_triggered`) are client-safe: `id`, `title`, `description`, `reactions`, `choices[{id, text, cost, risk}]`.
- Nothing in a response contains HTML.

---

## Errors

Body: `{ "success": false, "error": "<code>", "message": "<text>" }`

| Status | `error` | When |
|---|---|---|
| 400 | `missing_sign_in` | Neither `init_data` nor `player` |
| 400 | `invalid_player_name` | `player` breaks the captain-name rules |
| 400 | `invalid_json`, `missing_action`, `unknown_action` | Bad request |
| 401 | `unauthorized` | Signature wrong or sign-in older than 24 hours |
| 404 | `no_session` | An action other than `new_game` with no saved game |
| 405 | `method_not_allowed` | Wrong HTTP method |
| 500 | `action_error` | Unexpected failure (logged server-side) |

---

## Testing

`php tools/api_smoke.php` starts a local server, signs `initData` with the bot token, exercises every endpoint and error, plays as a web captain, checks that no HTML or event outcome tables leak, and confirms the bot sees the same game. `node tools/mini_app_e2e.js` drives the real Mini-App against it in headless Chrome. See [`../../docs/IMPLEMENTATION_GUIDE.md`](../../docs/IMPLEMENTATION_GUIDE.md#testing).

Manual check:

```sh
php -S 127.0.0.1:8080
curl -s "http://127.0.0.1:8080/api/game/state.php"            # → 400 missing_sign_in
curl -s "http://127.0.0.1:8080/api/game/state.php?player=Ada"  # → 200, Ada's game (has_session false at first)
curl -s "http://127.0.0.1:8080/api/game/state.php?init_data=x" # → 401 unauthorized
```

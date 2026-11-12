# Running in Docker

**Last Updated:** 2026-11-11
**Last Verified Against Code:** 2026-11-11

One container serves everything: the bot webhook (`uranus_game_bot.php`), the Mini-App (`mini-app/`) and its API (`api/game/`). It runs Apache and PHP 8.5 with APCu, curl and mbstring, and it honours the project `.htaccess`.

## Quick start

```bash
cp .env.example .env          # set TELEGRAM_BOT_TOKEN (use a dev bot locally)
docker compose up --build     # http://localhost:8080/
docker compose watch          # optional: live-sync code edits into the running container
```

| URL | What |
|-----|------|
| `/uranus_game_bot.php` | Bot webhook (GET shows a status page; also the healthcheck) |
| `/mini-app/app-v1.html` | Mini-App |
| `/api/game/state.php`, `/api/game/action.php` | Mini-App API |

Telegram needs a public HTTPS URL for the webhook and for the Mini-App. Locally, tunnel port 8080 (for example `ngrok http 8080`), then point the webhook and the BotFather menu button at the tunnel. See [`DEVELOPMENT.md`](DEVELOPMENT.md).

## Configuration (environment)

| Variable | Default | Purpose |
|----------|---------|---------|
| `TELEGRAM_BOT_TOKEN` | `YOUR_BOT_TOKEN_HERE` (not configured) | Bot token |
| `WEBHOOK_SECRET` | empty (check disabled) | Secret Telegram sends in `X-Telegram-Bot-Api-Secret-Token` |
| `URANUS_RUNTIME_DIR` | `/var/lib/uranus` in the image; unset elsewhere | Where all runtime state goes |
| `URANUS_PORT` | `8080` | Host port (compose only) |

Everything else (balance, admin IDs, feature switches) is still edited in `config.php`.

## State

Code and game content (`data/*.json`, `media/`) are baked into the image. Everything the app writes goes to `URANUS_RUNTIME_DIR`, which compose mounts as the `uranus-state` volume:

```
/var/lib/uranus/
├── sessions/          saved games (one per Telegram user)
├── bot_prefs/         bot-only chat routing and picture set
├── message_ids/
├── leaderboards.json
├── file_ids.json      Telegram file_id cache
└── bot.log, diagnostic.log, telemetry.jsonl
```

Without `URANUS_RUNTIME_DIR` (the shared host), these files stay where they always were: `logs/`, `data/leaderboards.json` and `media/file_ids.json`.

Rebuilding or recreating the container keeps the volume. `docker compose down -v` deletes it, and every player's game with it.

**Moving production data in:** copy the server's `logs/sessions`, `logs/bot_prefs`, `data/leaderboards.json` and `media/file_ids.json` into the volume (`docker compose cp <path> uranus:/var/lib/uranus/`), then `docker compose exec uranus chown -R www-data:www-data /var/lib/uranus`.

## Running the test tools against the image

The tools aren't in the image. To run them on the image's PHP, mount the repo:

```bash
docker run --rm -v "$PWD":/src:ro -e URANUS_RUNTIME_DIR=/tmp/rt --entrypoint sh occupy-uranus \
  -c 'cp -r /src /tmp/app && cd /tmp/app && php tools/api_smoke.php'
```

## Not in the image

`docs/`, `tools/`, `test_framework/`, `notes/`, `logs/`, `.git`, `.env`, the deploy scripts and the unused `media/*/png/` originals. See `.dockerignore`.

## Web release (shared hosting, browser only)

`caseyclayton.cc/uranus` runs on Hostinger shared hosting, which serves PHP directly (no containers). It gets a **browser-only release**: the Mini-App, its API and the game. It has no Telegram bot, no webhook, no images and no bot token, and players sign in with a captain name.

```bash
tools/build_web_release.sh ../occupy-uranus-release   # the release repo's working tree (outside this repo)
cd ../occupy-uranus-release && git add -A && git commit -m "Release" && git push
```

Hostinger then pulls the release repo into `public_html/uranus` ("Deploy website Git repository", directory `uranus`). A pull only replaces files the release tracks, so saves (`logs/`) and the leaderboard (`data/leaderboards.json`) on the server survive updates. `logs/.htaccess` and the project `.htaccess` keep them out of the browser.

Never use Hostinger's whole-site deploy for this: it replaces everything in `public_html`, including the rest of the site.

> Set `TELEGRAM_BOT_TOKEN` through the runtime environment; never put a real token in source code or a public image.

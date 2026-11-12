# Occupy Uranus — Working Instructions for Claude

**Last Updated:** 2026-11-11 (bot and Mini-App untangled around a neutral GameService)
**Last Verified Against Code:** 2026-11-11

Space-themed survival game (parody of Oregon Trail): **one game, two equal clients:**
1. **PHP Telegram Bot** - Webhook-based; screens and text in `bot/`
2. **HTML Mini-App** - Telegram Web App in the browser; talks to a thin PHP API (`api/game/`)

Both play the **same saved game** (one per Telegram user) through `game/GameService`, which holds all rules. Neither client contains game rules and neither depends on the other.

> **⚡ IMPORTANT:** This file focuses on **documentation navigation** and **universal conventions**.
> For **implementation-specific patterns**, see the documentation index below based on what you're working on.

> **⚠️ DISCUSSION PROTOCOL:**
> - During exploratory discussions, **DO NOT implement or deploy fixes immediately**
> - If an issue is identified during discussion, **note it but ask before fixing**
> - User may be exploring multiple related topics - don't jump ahead
> - **Always confirm intent** before moving from discussion to implementation

---

## 🚨 BEFORE YOU START: Which Implementation?

**This project has TWO implementations. Choose the right documentation path:**

**For how the bot, the Mini-App and the game fit together (the `GameService` contract, the dependency rules, how to add a feature, how to test), read [`docs/IMPLEMENTATION_GUIDE.md`](docs/IMPLEMENTATION_GUIDE.md) first.**

```
game/       the game (rules, saves, GameService). No Telegram, no HTML, no screen names.
bot/        Telegram client (BotController, ViewManager, GameText, BotPreferences)
api/game/   the Mini-App's thin API
mini-app/   browser client (all its text is in js/messages.js)
```

### Working on the PHP Telegram Bot?
→ Go to **"PHP Bot Documentation"** section below

### Working on the HTML Mini-App?
→ Go to **"Mini-App Documentation"** section below

### Working on game design or content (both)?
→ Go to **"Universal Documentation"** section below

---

## 📱 Mini-App Documentation

**Start here for mini-app development:**

| What You're Doing | Read This First |
|-------------------|-----------------|
| **Getting started with mini-app** | [`docs/mini-app/README.md`](docs/mini-app/README.md) |
| **The API it talks to** | [`api/game/README.md`](api/game/README.md) |
| **Actions, outcomes, reason codes** | class comment in [`game/GameService.php`](game/GameService.php) |
| **Testing in real Telegram** | [`docs/mini-app/TESTING_GUIDE.md`](docs/mini-app/TESTING_GUIDE.md) |

**Key Points:**
- The server owns the game; the Mini-App only draws it and words the results (`js/messages.js`)
- No game rules or constants in the browser; numbers arrive in the API's `status` and `shop`
- Telegram Web App API integration; HTML/CSS/JavaScript (not PHP)
- Older Mini-App planning/reference docs are archived (`docs/archived/`)

---

## 🤖 PHP Bot Documentation

**Start here for PHP bot development:**

| What You're Doing | Read This First |
|-------------------|-----------------|
| **Adding features or fixing bugs** | [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) - Class responsibilities |
| **Implementation patterns** | [`docs/TELEGRAM_API.md`](docs/TELEGRAM_API.md) - PHP patterns & quick starts |
| **Working with views/UI** | [`docs/GAME_VIEWS.md`](docs/GAME_VIEWS.md) - All bot screens |
| **Session data or game state** | [`docs/SESSION_STATE.md`](docs/SESSION_STATE.md) - Field structure |
| **File organization** | [`docs/CODEBASE_STRUCTURE.md`](docs/CODEBASE_STRUCTURE.md) - Where code lives |
| **Testing locally** | [`docs/DEVELOPMENT.md`](docs/DEVELOPMENT.md) - Local setup |
| **Running in Docker** | [`docs/DOCKER.md`](docs/DOCKER.md) - One container: bot, Mini-App, API |
| **Deploying changes** | [`DEPLOYMENT.md`](DEPLOYMENT.md) - Deployment process |

**Key PHP Bot Patterns:**
- BotController pattern → See `docs/TELEGRAM_API.md` "BotController Pattern"
- Session flow → See `docs/TELEGRAM_API.md` "Session Flow Pattern"
- sendMessage wrapper → See `docs/TELEGRAM_API.md` "sendMessage Wrapper Pattern"
- Quick starts → See `docs/TELEGRAM_API.md` "Quick Start Workflows"

---

## 🌍 Universal Documentation (Both Implementations)

**Game design, content, and architecture principles:**

| Topic | Document | Applies To |
|-------|----------|------------|
| **Game mechanics & balance** | [`docs/GAME_MECHANICS_REVIEW.md`](docs/GAME_MECHANICS_REVIEW.md) | Both |
| **View state flow** | [`docs/VIEW_STATE_DIAGRAM.md`](docs/VIEW_STATE_DIAGRAM.md) | Both |
| **Feature backlog** | [`IMPLEMENTATION_BACKLOG.md`](IMPLEMENTATION_BACKLOG.md) | Both |
| **Documentation standards** | [`DOCS_PROTOCOL.md`](DOCS_PROTOCOL.md) | Both |
| **Architecture principles** | [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) | Primarily bot, but principles apply |

---

## 🔍 How to Find Documentation

### 1. Use the Tables Above
Match your task to the implementation (bot vs mini-app) and topic.

### 2. Search Documentation
```bash
# Search all active docs for a term
grep -r "keyword" docs/ --exclude-dir=archived

# Find a specific file
find docs/ -name "*pattern*"

# Search multiple terms
grep -r "waypoint\|station" docs/ --exclude-dir=archived
```

### 3. Check Documentation Structure
```
docs/
├── ARCHITECTURE.md, SESSION_STATE.md, etc.  # Bot reference
├── TELEGRAM_API.md                          # Bot patterns & quick starts
├── mini-app/                                # Mini-app implementation
├── testing/                                 # Testing guides
├── creative/                                # Image generation
└── archived/                                # Historical docs
```

### 4. When You Can't Find It

**If a topic isn't documented:**
1. **Read the code** - Code is the source of truth
2. **Ask the user** - Don't assume or invent
3. **Document it** - Add what you learn to the appropriate doc

**Never:**
- ❌ Guess based on similar features
- ❌ Assume from memory of other projects
- ❌ Implement without understanding existing patterns


## 🎯 How to Use This File

**CLAUDE.md = Documentation Navigator + Universal Conventions**
- Use this file to find the right documentation for your task
- Universal rules that apply to both implementations

**Implementation-Specific Docs:**
- **PHP Bot** → See `docs/TELEGRAM_API.md` for patterns & quick starts
- **Mini-App** → See `docs/mini-app/` for implementation details

**Golden Rule:** Don't guess. Use the tables above to find the right doc, then read it.

---

## 🏗️ Universal Architecture Principles

**Separation of Concerns:**
- Logic layer = pure game rules and calculations
- Presentation layer = formatting and display only
- Orchestration layer = coordinate flow between logic and presentation

**Applies to:**
- **Game:** `GameEngine` (rules, returns data and reason codes), `GameService` (the one door: actions, saves, telemetry)
- **Telegram bot:** `ViewManager` / `GameText` (presentation), `BotController` (which screen follows which outcome)
- **Mini-App:** `messages.js` (words), `app.js` (flow), `data-binder.js` (dashboard)

**`game/` imports nothing from `bot/`, `api/` or `mini-app/`, and clients change the game only through `GameService::act()`.**

**Key Rule:** Never mix concerns. Keep logic, presentation, and flow separate.

**Full architecture details:**
- **Rulebook:** [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)
- **Map and contract:** [`docs/IMPLEMENTATION_GUIDE.md`](docs/IMPLEMENTATION_GUIDE.md)
- **Mini-App:** [`docs/mini-app/README.md`](docs/mini-app/README.md)

---

## 📜 Universal Conventions

### Implementation Philosophy

**NO BACKWARD COMPATIBILITY THEATER**

✅ Implement changes directly and completely. Replace old code with new code.
❌ No feature flags, legacy paths, or "Phase 1" implementations that defer work.

**Why:** Single-developer project. Git provides rollback. Feature flags add complexity without benefit.

**Applies to:** Both PHP bot and mini-app implementations.

---

### Naming Conventions

**PHP (Bot):**
- Methods/functions: camelCase (`advanceDay`, `loadSession`)
- Variables: camelCase (`$userId`, `$chatId`)
- Constants: UPPER_SNAKE_CASE (`BOT_TOKEN`, `MAX_CREW`)
- Classes: PascalCase (`GameEngine`, `TelegramAPI`)
- Files: Match class names (`GameEngine.php`)

**JavaScript (Mini-App):**
- Functions: camelCase (`advanceDay`, `loadSession`)
- Variables: camelCase (`userId`, `chatId`)
- Constants: UPPER_SNAKE_CASE (`MAX_CREW`, `TOTAL_DISTANCE`)
- Classes: PascalCase (`GameEngine`, `UIManager`)

**Universal:**
- Session/state keys: snake_case (`user_id`, `created_at`, `resources`)
- File names: Descriptive, match content

---

### Error Handling

**Universal Principles:**
- Try-catch only for exceptional conditions
- Return false/null for expected failures (validation, resource limits)
- Log errors, don't silently fail
- Never use die() or exit() in application code

**Implementation-Specific:**
- **PHP Bot:** See `docs/TELEGRAM_API.md` for error handling patterns
- **Mini-App:** Console errors for development, user-friendly messages in production

---


## 🎮 Game Design & Balance

⚠️ **Do not modify game balance, events, or mechanics without reviewing [`docs/GAME_MECHANICS_REVIEW.md`](docs/GAME_MECHANICS_REVIEW.md) first.**

**Rationale:** Game has been playtested and balanced. Changes should be intentional and documented.

**Applies to:** Both bot and mini-app implementations (shared game mechanics).

---

## Git & Development Workflow

### Git Conventions

- **Commits:** Clear, concise messages; imperative mood; under 72 characters
- **Branches:** Feature branches for significant changes
- **Testing:** Verify changes before committing (tests, syntax checks, or manual testing)

**Implementation-Specific Testing:**
- **PHP Bot:** See `docs/DEVELOPMENT.md` for local testing with ngrok
- **Mini-App:** Test locally with live server, check browser console

### Before Committing

```bash
# Check status
git status

# Review changes
git diff

# Verify no sensitive data
git diff | grep -i "token\|secret\|password"
```

**PHP-specific:** Syntax check with `php -l file.php`

**JavaScript-specific:** Check browser console for errors

---

## 📊 Project Status

### Implementations

✅ **Game (`game/`)** - One `GameService` door; rules return data and reason codes only
- Verified identical to the pre-split engine over 60 seeded games (7,736 moves, both endings)
- 50+ events, 8 waypoints, full game loop

✅ **PHP Telegram Bot** - Fully functional on `GameService`
- Webhook-based; screens and text in `bot/`; bot-only state in `bot/BotPreferences.php`

✅ **HTML Mini-App** - Full client: start, shop, launch, play, events, stations, status, turn back, endings, abandon
- Draws from server data; all text in `js/messages.js`
- Tested end to end in headless Chrome and over HTTP; **not yet exercised inside real Telegram since the split** (see `docs/mini-app/TESTING_GUIDE.md`)
- Plays in a plain browser too: web players sign in with a captain name (honor system, `js/web-player.js`, `player` in `api/game/`)
- Web release (browser only, no bot) built by `tools/build_web_release.sh` for `caseyclayton.cc/uranus` (see `docs/DOCKER.md`)
- Not in the Mini-App yet: map screen, leaderboard, who's playing, help, aesthetics

⚠️ **Not deployed:** the split changes folders (`classes/` and `api/mini-app/` are gone). Read the layout note in `DEPLOYMENT.md` before the next deploy.

### Documentation

✅ **CLAUDE.md** - Navigation and universal conventions

✅ **Bot / game reference** - IMPLEMENTATION_GUIDE.md, ARCHITECTURE.md, CODEBASE_STRUCTURE.md, SESSION_STATE.md, TELEGRAM_API.md, GAME_VIEWS.md

✅ **Mini-App reference** - docs/mini-app/README.md, api/game/README.md, TESTING_GUIDE.md (older Mini-App docs are in `docs/archived/`)

**Historical documentation:** See `docs/archived/` for development history

**Last documentation refactor:** 2026-11-11

---

**Read this file at the start of every session to navigate to the right documentation.**

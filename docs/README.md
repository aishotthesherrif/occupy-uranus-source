# Documentation Index

Welcome to the Occupy Uranus documentation. This index helps you find both **active reference docs** and **archived historical work**.

---

## Active Reference Documentation

### Core Architecture & Reference
- **[IMPLEMENTATION_GUIDE.md](IMPLEMENTATION_GUIDE.md)** - ⭐ How the bot, the Mini-App and the game fit together: the `GameService` contract, dependency rules, testing, known leftovers
- **[ARCHITECTURE.md](ARCHITECTURE.md)** - Architecture guidelines and separation of concerns principles
- **[SESSION_STATE.md](SESSION_STATE.md)** - ⭐ Complete session data structure reference
- **[CODEBASE_STRUCTURE.md](CODEBASE_STRUCTURE.md)** - ⭐ File organization, dependencies, where to add code
- **[GAME_VIEWS.md](GAME_VIEWS.md)** - Complete reference for all game views, keyboards, and conditionals
- **[GAME_MECHANICS_REVIEW.md](GAME_MECHANICS_REVIEW.md)** - Game balance analysis and design recommendations

### Developer Guides
- **[DEVELOPMENT.md](DEVELOPMENT.md)** - ⭐ Local testing, debugging, development workflows
- **[TELEGRAM_API.md](TELEGRAM_API.md)** - ⭐ Telegram API limits, constraints, patterns, error handling
- **[LOGGING_GUIDE.md](LOGGING_GUIDE.md)** - Logging system and telemetry usage
- **[USERS_GUIDE.md](USERS_GUIDE.md)** - Player-facing feature documentation

### Media & Asset Generation
- **[IMAGE_SYSTEM.md](IMAGE_SYSTEM.md)** - ⭐ Image selection, view–context mapping, caching, and media reference
- **[IMAGE_GENERATION_CHECKLIST.md](IMAGE_GENERATION_CHECKLIST.md)** - Complete inventory of 45 game images
- **[IMAGE_GENERATION_INSTRUCTIONS.md](IMAGE_GENERATION_INSTRUCTIONS.md)** - ⭐ Step-by-step guide for creating new aesthetics

---

## Archived Documentation

All completed projects are archived by date in the `archived/` directory:

- **[2026-03-01-main-menu-refinement/](archived/2026-03-01-main-menu-refinement/)** - Main Menu refinement and status flow fixes
- **[2026-03-01-view-ux-improvements/](archived/2026-03-01-view-ux-improvements/)** - View UX improvements and new aesthetics
- **[2026-02-28-separation-refactor/](archived/2026-02-28-separation-refactor/)** - Three-phase separation of concerns refactor
- **[2026-02-27-waypoint-shops/](archived/2026-02-27-waypoint-shops/)** - Waypoint shops implementation
- **[2026-02-27-view-redesign/](archived/2026-02-27-view-redesign/)** - Journey/Status view redesign with chad quotes
- **[2026-02-26-code-review/](archived/2026-02-26-code-review/)** - Comprehensive code review and quality audit
- **[2026-02-26-features/](archived/2026-02-26-features/)** - Feature implementations (leaderboard, map, multiplayer fixes)
- **[2026-02-26-view-architecture/](archived/2026-02-26-view-architecture/)** - View architecture migration

See **[archived/README.md](archived/README.md)** for complete archive timeline and descriptions.

---

## Root-Level Documentation

Other important docs at project root:

### Essential Reference
- **[CLAUDE.md](../CLAUDE.md)** - Project reference for Claude/AI assistance (MOST IMPORTANT)
- **[README.md](../README.md)** - Project overview
- **[DEPLOYMENT.md](../DEPLOYMENT.md)** - Deployment guide and procedures
- **[DOCS_PROTOCOL.md](../DOCS_PROTOCOL.md)** - Documentation standards and best practices

### Planning
- **[IMPLEMENTATION_BACKLOG.md](../IMPLEMENTATION_BACKLOG.md)** - Feature backlog and deferred work

---

## Documentation Structure

```
docs/
├── README.md                          (this file)
├── IMPLEMENTATION_GUIDE.md            (bot, Mini-App and game: contract, rules, tests) ⭐
├── ARCHITECTURE.md                    (architecture guidelines)
├── SESSION_STATE.md                   (session data reference) ⭐
├── CODEBASE_STRUCTURE.md              (file organization) ⭐
├── TELEGRAM_API.md                    (API constraints) ⭐
├── DEVELOPMENT.md                     (local testing) ⭐
├── GAME_VIEWS.md                      (view reference)
├── IMAGE_SYSTEM.md                    (image selection + view mapping) ⭐
├── GAME_MECHANICS_REVIEW.md           (game design)
├── LOGGING_GUIDE.md                   (logging system)
├── USERS_GUIDE.md                     (player docs)
├── IMAGE_GENERATION_CHECKLIST.md      (task list)
└── archived/                          (completed work)
    ├── README.md                      (archive timeline)
    ├── 2026-02-28-session-work/       (documentation overhaul) ⭐ NEW
    ├── 2026-02-28-separation-refactor/
    ├── 2026-02-27-waypoint-shops/
    ├── 2026-02-27-view-redesign/
    ├── 2026-02-26-code-review/
    ├── 2026-02-26-features/
    └── 2026-02-26-view-architecture/
```

---

## Quick Reference

**Need to understand:**
- How do the bot and Mini-App relate? → `IMPLEMENTATION_GUIDE.md`
- Current architecture? → `ARCHITECTURE.md`
- Session data structure? → `SESSION_STATE.md` ⭐
- File organization? → `CODEBASE_STRUCTURE.md` ⭐
- Game views and UI? → `GAME_VIEWS.md`
- Image selection system? → `IMAGE_SYSTEM.md` ⭐
- Telegram API limits? → `TELEGRAM_API.md` ⭐
- How to test locally? → `DEVELOPMENT.md` ⭐
- How to deploy? → `../DEPLOYMENT.md`
- Working instructions for Claude? → `../CLAUDE.md`

**Looking for historical info:**
- Documentation overhaul? → `archived/2026-02-28-session-work/` ⭐
- Past refactors? → `archived/2026-02-28-separation-refactor/`
- Feature implementations? → `archived/2026-02-26-features/`
- Bug fixes? → `archived/2026-02-26-features/`

---

**Last Updated:** 2026-03-03 (documentation audit and path corrections)

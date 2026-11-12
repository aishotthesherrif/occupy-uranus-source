# 🚀 Occupy Uranus

A space survival game inspired by The Oregon Trail, playable in a **Telegram bot** and in a **Telegram Mini-App**. Both are clients of the same game: one saved game per player, switch at any move.

## Quick Start

**First time setup?** → See [PRODUCTION_SETUP.md](PRODUCTION_SETUP.md) for complete deployment guide

**Deploying updates?** → See [DEPLOYMENT.md](DEPLOYMENT.md) for ongoing deployments

**Run it in Docker?** → `cp .env.example .env && docker compose up --build`, see [docs/DOCKER.md](docs/DOCKER.md)

**Want to play?** → Message [@OccupyUranusBot](https://t.me/occupyuranusbot) on Telegram

**For developers:** → See [CLAUDE.md](CLAUDE.md) for conventions and [docs/IMPLEMENTATION_GUIDE.md](docs/IMPLEMENTATION_GUIDE.md) for how the bot, the Mini-App and the game fit together

## System Status

✅ **Complete Implementation:**
- `bot/TelegramAPI.php` - Telegram API wrapper
- `game/GameEngine.php` - Game rules (no text); `game/GameService.php` - the one entry point used by the bot and the Mini-App
- `game/GameSessionManager.php` - Session management  
- `uranus_game_bot.php` - Telegram bot (webhook)
- `bot/` - Telegram client (screens, text, controller)
- `api/game/` and `mini-app/` - Mini-App API and browser client

✅ **Data Files Populated:**
- `data/events.json` - Game events
- `data/waypoints.json` - Solar system waypoints
- `data/image_config.json` - Image system configuration
- `data/crew_personalities.json` - Chad personality system
- `data/items.json` - Shop items and resources

✅ **Image System Complete:**
- 24 images generated and configured
- Contextual image selection system
- Telegram file_id caching for performance
- Image rotation to prevent repetition

## Directory Structure

**See [docs/CODEBASE_STRUCTURE.md](docs/CODEBASE_STRUCTURE.md) for complete technical reference.**

```
/uranus-game-bot/
├── uranus_game_bot.php    # Telegram bot entry point (webhook)
├── config.php             # Configuration
├── game/                  # The game: rules, saves, GameService (no Telegram, no text)
├── bot/                   # Telegram client: screens, text, controller
├── api/game/              # Mini-App API (thin)
├── mini-app/              # Mini-App (browser client)
├── tools/                 # Test and verification tools
├── data/                  # Game content (events, waypoints, etc.)
├── logs/                  # Runtime data (sessions, message IDs, logs)
├── media/                 # Images (contextual images)
└── docs/                  # Documentation
```

## Features

- **Space Exploration Game**: Journey from Earth to Uranus (2.8 billion km)
- **Resource Management**: Manage fuel, food, chad, and hull integrity
- **Random Events**: Dynamic encounters and choices (50+ unique events)
- **Station Skip System**: Choose to stop or bypass stations for speed
- **Gravitational Assist**: Gain 2× speed boost by skipping stations
- **Strategic Gameplay**: Trade-off between speed and supplies
- **Waypoint System**: Major milestones and locations with images
- **Image System**: Contextual images with per-aesthetic caching
- **Multiple Aesthetics**: Switch between visual styles (main, anime, etc.)
- **Chad Management**: Personality-based chad interactions and traits
- **Shop System**: Resource trading and upgrades at stations
- **Leaderboard**: Track fastest completion times
- **Session Management**: Persistent game state across sessions
- **Clean Chat UI**: Message cleanup for streamlined interaction

## Deployment

**The bot is production-ready!**

For deployment instructions, see:
- **[PRODUCTION_SETUP.md](PRODUCTION_SETUP.md)** - First-time setup (webhook, tokens, permissions)
- **[DEPLOYMENT.md](DEPLOYMENT.md)** - Ongoing updates and deployments

## Commands

- `/game` - Start or resume your journey
- `/help` - Show help and instructions

**In-game actions via buttons:**
- Advance Day - Continue journey
- View Status - Check resources and progress
- Visit Shop - Buy supplies at stations
- Main Menu - Access settings and options

## Support

Check the logs at `logs/bot.log` for errors.

Good luck, Captain! 🚀🪐

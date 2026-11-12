# Occupy Uranus Bot Documentation

Welcome to the Occupy Uranus Telegram Bot documentation. This bot is a space exploration game where players guide their chad on a journey to establish humanity's first colony on Uranus.

## Documentation Index

### Core Systems
- [Image Selection System](IMAGE_SYSTEM.md) - Contextual images with bandwidth optimization
- [Events System](EVENTS_SYSTEM.md) - Random events and choices
- [Game Engine](GAME_ENGINE.md) - Core game logic and mechanics
- [Session Management](SESSION_MANAGEMENT.md) - Player session handling
- [Telegram API](TELEGRAM_API.md) - Bot communication layer

### Game Features
- [Waypoints System](WAYPOINTS_SYSTEM.md) - Journey milestones and locations
- [Shop System](SHOP_SYSTEM.md) - Resource management and trading
- [Chad System](CREW_SYSTEM.md) - Chad management and morale

### Development
- [Setup Guide](SETUP_GUIDE.md) - Installation and configuration
- [Deployment Guide](DEPLOYMENT_GUIDE.md) - Production deployment
- [API Reference](API_REFERENCE.md) - Complete API documentation
- [Testing Guide](TESTING_GUIDE.md) - Testing strategies and tools

### User Guides
- [User Guide](../USERS_GUIDE.md) - How to play the game
- [Admin Commands](ADMIN_COMMANDS.md) - Administrative functions
- [Troubleshooting](TROUBLESHOOTING.md) - Common issues and solutions

## Quick Start

1. **Setup**: Follow the [Setup Guide](SETUP_GUIDE.md)
2. **Configuration**: Configure your bot token and base URL
3. **Deploy**: Use the [Deployment Guide](DEPLOYMENT_GUIDE.md)
4. **Test**: Run through the [Testing Guide](TESTING_GUIDE.md)

## Architecture Overview

The bot is built with a modular architecture:

```
uranus_game_bot.php          # Main bot entry point
├── classes/
│   ├── GameEngine.php       # Core game logic with image system
│   ├── GameSessionManager.php # Session management
│   └── TelegramAPI.php      # Telegram communication
├── data/                    # Game data and configuration
│   ├── image_config.json    # Image system configuration
│   ├── chad_personalities.json # Chad personality system
│   └── items.json           # Shop items and resources
├── media/                   # Images and assets (24 images)
│   ├── Waypoint-*.png       # Waypoint images (9)
│   ├── milestone-*.png      # Progress images (3)
│   ├── Space-View-*.png     # Space travel images (3)
│   ├── Event-*.png         # Event images (2)
│   └── Status-*.png         # Status images (2)
├── logs/                    # Logging and telemetry
└── docs/                    # Documentation
```

## Key Features

- **Space Exploration Game**: Journey from Earth to Uranus
- **Resource Management**: Manage fuel, food, chad, and hull
- **Random Events**: Dynamic encounters and choices
- **Waypoint System**: Major milestones and locations with 9 waypoint images
- **Image System**: 24 contextual images with Telegram file_id caching
- **Chad Management**: Personality-based chad interactions
- **Shop System**: Resource trading and upgrades
- **Session Management**: Persistent game state
- **Telemetry**: Comprehensive logging and analytics

## Contributing

1. Read the relevant documentation
2. Follow the coding standards
3. Test your changes thoroughly
4. Update documentation as needed
5. Submit pull requests with clear descriptions

## Support

- **Issues**: Check [Troubleshooting](TROUBLESHOOTING.md) first
- **Documentation**: Browse the docs folder
- **Code**: Review the source code and comments
- **Logs**: Check bot.log and telemetry.jsonl

## License

This project is part of the ColonyTrail game development initiative.

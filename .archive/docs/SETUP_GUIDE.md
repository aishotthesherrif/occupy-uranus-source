# Setup Guide

This guide will help you set up the Occupy Uranus Telegram Bot for development and testing.

## Prerequisites

- PHP 7.4 or higher
- Web server (Apache/Nginx) or PHP built-in server
- Telegram Bot Token from @BotFather
- Domain or local server for image hosting

## Installation

### 1. Clone the Repository

```bash
git clone <repository-url>
cd OccupyUranusGame_bot
```

### 2. Configure the Bot

Edit `config.php`:

```php
// Your Telegram Bot API Token from @BotFather
define('BOT_TOKEN', 'YOUR_BOT_TOKEN_HERE');

// Base URL for your bot (used for media files)
define('BOT_BASE_URL', 'https://yourdomain.com'); // Replace with your actual domain
```

### 3. Set Up Web Server

#### Option A: PHP Built-in Server (Development)
```bash
php -S localhost:8000
```

#### Option B: Apache/Nginx (Production)
Configure your web server to serve the bot directory.

### 4. Configure Webhook

Set your bot's webhook to point to your server:

```bash
curl -X POST "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook" \
     -H "Content-Type: application/json" \
     -d '{"url": "https://yourdomain.com/uranus_game_bot.php"}'
```

## Image System Setup

### 1. Configure BOT_BASE_URL

The image system requires a publicly accessible URL for your media files:

```php
// In config.php
define('BOT_BASE_URL', 'https://yourdomain.com');
```

### 2. Verify Media Directory

Ensure your `media/` directory is accessible:

```
https://yourdomain.com/media/Title-01.png
https://yourdomain.com/media/Continue-01.png
```

### 3. Test Image Access

Test that images are accessible via HTTP:

```bash
curl -I https://yourdomain.com/media/Title-01.png
```

Should return HTTP 200 OK.

## Development Setup

### 1. Enable Debug Logging

```php
// In config.php
define('LOG_LEVEL', 'DEBUG');
```

### 2. Monitor Logs

Watch the logs for debugging:

```bash
tail -f logs/bot.log
tail -f logs/telemetry.jsonl
```

## Configuration Options

### Bot Settings

```php
// Admin user IDs for debug commands
define('ADMIN_USER_IDS', [123456789, 987654321]);

// Rate limiting
define('RATE_LIMIT_DEFAULT', 2);  // seconds between commands
define('RATE_LIMIT_CONTINUE', 1); // seconds for /continue
```

### Game Settings

```php
// Difficulty settings
define('DIFFICULTY_EASY', [
    'name' => 'Colonist',
    'credits' => 2000,
    'event_frequency' => 0.2
]);

// Game constants
define('TOTAL_DISTANCE', 2800000000); // 2.8 billion km to Uranus
define('TRAVEL_SPEED', 50000000);     // 50 million km per day
```

### Feature Flags

```php
define('FEATURE_LEADERBOARDS', true);
define('FEATURE_SHOP', true);
define('FEATURE_DEBUG_COMMANDS', true);
```

## Testing

### 1. Basic Functionality

Test core bot commands:

```
/start - Start the bot
/newgame - Start a new game
/help - Show help
```

### 2. Image System

Test image functionality:

1. Start a new game
2. Verify images appear in messages
3. Check `data/image_cache.json` for file_id caching
4. Test subsequent messages use cached file_ids

### 3. Game Flow

Test complete game flow:

1. Start new game
2. Choose difficulty
3. Shop for supplies
4. Launch ship
5. Continue journey
6. Reach waypoints
7. Handle events
8. Complete journey

## Troubleshooting

### Common Issues

**Bot not responding:**
- Check webhook configuration
- Verify bot token
- Check server logs

**Images not showing:**
- Verify BOT_BASE_URL is set correctly
- Check media directory permissions
- Test image URLs manually

**Session errors:**
- Check data directory permissions
- Verify JSON file formats
- Check for file locks

### Debug Commands

Use admin commands for debugging:

```
/debug - Show debug information
/reset - Reset game state
/status - Show system status
```

### Log Analysis

Check logs for errors:

```bash
# Bot logs
grep ERROR logs/bot.log

# Telemetry
grep "day_advance" logs/telemetry.jsonl
```

## Production Deployment

### 1. Security

- Use HTTPS for webhook
- Restrict file permissions
- Use environment variables for secrets
- Enable firewall rules

### 2. Performance

- Use PHP-FPM with Nginx
- Enable OPcache
- Set up log rotation
- Monitor resource usage

### 3. Monitoring

- Set up log monitoring
- Monitor webhook delivery
- Track game metrics
- Set up alerts

## Next Steps

1. **Customize Game**: Modify events, waypoints, and images
2. **Add Features**: Implement new game mechanics
3. **Optimize**: Improve performance and user experience
4. **Scale**: Handle multiple concurrent users

## Support

- Check the [Troubleshooting Guide](TROUBLESHOOTING.md)
- Review the [API Reference](API_REFERENCE.md)
- Browse the source code and comments
- Check logs for error details

# Development Guide

**Last Updated:** 2026-03-02 (Updated for logs/ directory reorganization)
**Last Verified Against Code:** 2026-03-02

Complete guide for local development and testing of Occupy Uranus bot.

---

## Overview

This guide covers setting up a local development environment, testing the bot locally, and common development workflows.

**Prerequisites:**
- PHP 7.4+ with cURL extension
- Git
- Telegram account
- ngrok or similar tunneling tool (for webhook)

---

## Initial Setup

### 1. Create Development Bot

1. Message `@BotFather` on Telegram
2. Send `/newbot`
3. Follow prompts to create bot
4. Save the bot token (looks like: `123456789:ABCdefGHIjklMNOpqrsTUVwxyz`)
5. **Important:** Use a separate bot for development (not production)

---

### 2. Clone Repository

```bash
git clone <repository-url>
cd OccupyUranusGame_bot
```

---

### 3. Configure Environment

#### Set Bot Token

```bash
# Option A: Environment variable (recommended)
export TELEGRAM_BOT_TOKEN="your_dev_bot_token_here"

# Set TELEGRAM_BOT_TOKEN in your local environment or ignored .env file.
```

#### Webhook Secret (Not Needed for Local Dev)

**For local development:** Leave `WEBHOOK_SECRET` empty in config.php (default).

The webhook secret is a production security feature. Locally, the bot skips validation when the secret is empty.

**Note:** Webhook secret setup is covered in `DEPLOYMENT.md` for production deployment.

---

### 4. Verify Permissions

```bash
# Ensure data directory is writable
chmod -R 755 data/
chmod -R 777 logs/sessions/
```

---

## Local Testing Setup

### Method 1: ngrok (Recommended)

#### Install ngrok

```bash
# macOS (Homebrew)
brew install ngrok

# Or download from https://ngrok.com/download
```

#### Start Local PHP Server

```bash
php -S localhost:8000
```

Keep this running in one terminal.

#### Start ngrok Tunnel

In a **second terminal**:

```bash
ngrok http 8000
```

You'll see output like:
```
Forwarding  https://abc123.ngrok.io -> http://localhost:8000
```

Copy the HTTPS URL.

#### Register Webhook

In a **third terminal**:

```bash
curl -X POST "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/setWebhook" \
  -d "url=https://abc123.ngrok.io/uranus_game_bot.php"
```

Replace:
- `$TELEGRAM_BOT_TOKEN` with your dev bot token
- `abc123.ngrok.io` with your ngrok URL

**Note:** No secret needed for local dev. The bot skips validation when `WEBHOOK_SECRET` is empty.

#### Verify Webhook

```bash
curl "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/getWebhookInfo" | jq
```

Look for:
- `"url"` matches your ngrok URL
- `"has_custom_certificate": false`
- `"pending_update_count": 0` (or low)
- `"last_error_message": ""` (empty)

---

### Method 2: Local.run (Alternative)

If ngrok isn't available:

```bash
ssh -R 80:localhost:8000 nokey@localhost.run
```

Follow similar webhook registration steps with the provided URL.

---

## Development Workflow

### Starting Development Session

```bash
# Terminal 1: Start PHP server
php -S localhost:8000

# Terminal 2: Start ngrok
ngrok http 8000

# Terminal 3: Monitor logs
tail -f logs/bot.log
```

---

### Testing Changes

1. **Make code changes**
2. **No restart needed** - PHP's built-in server reloads files on each request
3. **Test in Telegram** - Message your bot
4. **Check logs** - Look at `logs/bot.log` for errors

---

### Common Development Tasks

#### View Current Session State

```bash
# List all sessions
ls -lh logs/sessions/

# View specific session (formatted)
cat logs/sessions/YOUR_USER_ID.json | jq

# View without jq
cat logs/sessions/YOUR_USER_ID.json
```

#### Clear Your Session (Start Fresh)

```bash
rm logs/sessions/YOUR_USER_ID.json

# Or use bot command (if debug mode enabled)
# Send /resetall to bot
```

#### Enable Debug Mode

Edit `config.php`:

```php
define('DEBUG_MODE', true);
```

This enables:
- Verbose logging in bot.log
- Debug commands (/debug_userid, /debug_sessions)
- More detailed error messages

**Remember:** Set to `false` before deploying to production.

---

### Debug Commands (DEBUG_MODE = true)

Send these commands to your bot:

```
/debug_userid
Shows your Telegram user ID and admin status

/debug_sessions
Lists all active game sessions (admin only)

/resetall
Deletes ALL sessions - use carefully! (admin only)
```

**Admin Detection:**
Admin status is determined by `ADMIN_USER_IDS` in config.php.

**Code Reference:**
- Debug commands: `uranus_game_bot.php` (search for `/debug_userid`, `/debug_sessions`, `/resetall`)
- Feature flag: `config.php:FEATURE_DEBUG_COMMANDS`

---

## Monitoring & Debugging

### Log Files

#### bot.log
**Location:** `logs/bot.log`
**Content:** Application logs (errors, warnings, info)

```bash
# Monitor in real-time
tail -f logs/bot.log

# View recent errors only
grep ERROR logs/bot.log | tail -20

# Clear log (development only)
> logs/bot.log
```

#### diagnostic.log
**Location:** `data/diagnostic.log`
**Content:** Webhook debugging (immediate, unbuffered)
**Use:** When webhook isn't working at all

**Code Reference:** See `docs/LOGGING_GUIDE.md` for complete logging system docs

---

### Telegram API Debugging

#### Check Webhook Status

```bash
curl "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/getWebhookInfo" | jq
```

#### Get Bot Info

```bash
curl "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/getMe" | jq
```

#### Test API Connection

```bash
curl "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/sendMessage" \
  -d "chat_id=YOUR_USER_ID" \
  -d "text=Test message"
```

---

### Common Issues

#### "Webhook not receiving updates"

**Check:**
1. Is ngrok running? Look for "Forwarding" line
2. Is PHP server running? `ps aux | grep php`
3. Is webhook registered? `getWebhookInfo` should show your URL
4. Is HTTPS working? Telegram requires HTTPS (ngrok provides this)

**Debug:**
```bash
# Check if bot is reachable
curl https://your-ngrok-url.ngrok.io/uranus_game_bot.php

# Should return HTML or 403 (not connection error)
```

#### "Bot token invalid"

**Check:**
1. Is token set? `echo $TELEGRAM_BOT_TOKEN`
2. Is token correct? Check @BotFather messages
3. Is environment variable loaded? Restart terminal if needed

#### "Forbidden error (403)"

**Likely Cause:** Webhook secret mismatch (production deployments only)

**For local dev:**
- Make sure `WEBHOOK_SECRET` is empty in config.php
- Bot skips validation when secret is empty

**For production:**
- See `DEPLOYMENT.md` for webhook secret setup

**Code Reference:** `uranus_game_bot.php` (search for `WEBHOOK_SECRET` and `X-Telegram-Bot-Api-Secret-Token`)

#### "Sessions not saving"

**Check:**
1. Is `logs/sessions/` writable? `ls -la data/`
2. PHP has permission? `chmod 777 logs/sessions/`
3. Disk space available? `df -h`

**Debug:**
```bash
# Test file write
touch logs/sessions/test.txt
rm logs/sessions/test.txt
```

---

## Testing Strategies

### Manual Testing

**Test Flows:**
1. New game → Launch Ship → GO → Continue (2-3 days) → Status → Map
2. Shop → Buy fuel → Buy food → Check resources
3. Event → Make choice → Check outcome
4. Waypoint → Stop → Shop → Rest → Continue
5. Death scenario → Check game over message
6. Victory → Reach Uranus

**Use Different Statuses:**
- Pre-launch: New Game View or Launch Sequence View (before GO button)
- In-space: Normal travel (after GO button)
- At waypoint: Stopped at station

---

### Session State Testing

**Modify session directly:**

```bash
# Edit session file
nano logs/sessions/YOUR_USER_ID.json

# Changes you might make:
# - Set distance close to waypoint
# - Reduce fuel/food to test game over
# - Set distance to 99% for victory test
# - Change status to test different views
```

**Example edits:**
```json
{
  "progress": {
    "distance": 2750000000  // Almost at Uranus (2.8B total)
  },
  "resources": {
    "fuel": 10,  // Low fuel scenario
    "food": 5    // Low food scenario
  }
}
```

**After editing:** Just continue playing - next action loads new state.

---

### Event Testing

**Test specific events:**

Edit session to trigger events more frequently:

```json
{
  "progress": {
    "day": 5  // Set low day for early events
  }
}
```

**Or:** Check `data/events.json` for event IDs and their conditions.

---

## Code Style & Conventions

**Before committing, verify:**

### Naming Conventions
- Methods: `camelCase`
- Variables: `camelCase`
- Constants: `UPPER_SNAKE_CASE`
- Classes: `PascalCase`
- Files: Match class names

### PHP Syntax Check

```bash
# Check all PHP files
find . -name "*.php" -exec php -l {} \;

# Check specific file
php -l uranus_game_bot.php
```

### Code Patterns

**Session Handling:**
```php
// CORRECT
$result = $this->gameEngine->advanceDay($session);
$session = $result['session']; // Use returned copy

// WRONG
$result = $this->gameEngine->advanceDay($session);
// Not using $result['session'] - saves stale state!
```

**Message Sending:**
```php
// CORRECT
$this->sendMessage($chatId, $text, $imageUrl, $keyboard, $userId, ...);

// WRONG
$this->telegram->sendMessage(...); // Bypasses tracking
```

**For complete conventions:** See `CLAUDE.md` - "Conventions (Laws of the Codebase)" section

---

## Git Workflow

### Before Committing

```bash
# 1. Check status
git status

# 2. Verify PHP syntax
find . -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"

# 3. Check for sensitive data
git diff | grep -i "token\|secret\|password"

# 4. Review changes
git diff
```

### Commit Message Format

```
Brief description (imperative mood)

Detailed description of what changed and why.

Files changed:
- file1.php - What was modified
- file2.php - What was modified

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>
```

**Examples:**
```
Add gravitational assist mechanic

Implement speed multiplier system that doubles travel speed
until next waypoint when player skips a station.

Files changed:
- GameEngine.php - Added speed_multiplier tracking
- ViewManager.php - Show boost status in journey view
- config.php - Added GRAVITATIONAL_ASSIST constant

Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>
```

---

## Deployment

Once tested locally, deploy to production:

```bash
./deploy.sh
```

**For deployment details:** See `DEPLOYMENT.md`

---

## Cleanup

### After Development Session

```bash
# Stop webhook (switch to polling or disable)
curl -X POST "https://api.telegram.org/bot$TELEGRAM_BOT_TOKEN/deleteWebhook"

# Stop servers
# Ctrl+C in PHP server terminal
# Ctrl+C in ngrok terminal

# Optional: Clear test sessions
rm logs/sessions/*.json

# Optional: Clear logs
> logs/bot.log
```

---

## Tools & Extensions

### Recommended PHP Extensions

```bash
# Check installed extensions
php -m

# Recommended:
# - curl (required)
# - json (required)
# - mbstring (recommended)
# - apcu (for rate limiting - optional)
```

### Recommended Tools

**For API Testing:**
- `curl` - Command-line HTTP client
- `jq` - JSON pretty-printer

**For Tunneling:**
- `ngrok` - HTTPS tunnel (freemium)
- `localhost.run` - Free SSH tunnel alternative

**For Monitoring:**
- `tail` - Real-time log viewing
- `watch` - Repeat commands periodically

---

## Code Verification Log

**Last Verified:** 2026-02-28 (corrected after user review)

### Files Checked
- ✅ `config.php` - WEBHOOK_SECRET default value (empty), FEATURE_DEBUG_COMMANDS
- ✅ `uranus_game_bot.php` - Webhook validation logic (lines 2020-2030), debug command handlers
- ✅ PHP built-in server behavior (file reloading)

### Verified & Corrected
- ✅ Webhook secret is OPTIONAL for local dev (empty by default, validation skipped)
- ✅ Webhook registration does NOT need secret parameter for local testing
- ✅ Debug commands exist: `/debug_userid`, `/debug_sessions`, `/resetall`
- ✅ FEATURE_DEBUG_COMMANDS flag in config.php (line 253)
- ✅ PHP built-in server (`php -S`) reloads files automatically (not PHP-FPM)

### Corrected
- ❌ Removed webhook secret from local setup (production-only)
- ❌ Fixed webhook registration curl command (removed secret parameter)
- ❌ Fixed PHP-FPM reference (changed to "built-in server")
- ❌ Removed specific line numbers (prone to drift, use search instead)

---

**For deployment:** See `DEPLOYMENT.md`
**For architecture:** See `docs/ARCHITECTURE.md`
**For logging system:** See `docs/LOGGING_GUIDE.md`

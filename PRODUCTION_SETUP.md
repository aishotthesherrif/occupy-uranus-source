# Production Setup Guide

**Purpose:** First-time production deployment setup

---

## 📋 Already Deployed?

**If your bot is already running in production**, use [`DEPLOYMENT.md`](DEPLOYMENT.md) for ongoing updates.

DEPLOYMENT.md covers:
- Deploying code changes with deploy.sh
- Verification and rollback
- Troubleshooting deployment issues

**This guide (PRODUCTION_SETUP.md) is for:** Initial setup from scratch (webhook, tokens, permissions).

---

## Overview

This guide covers the **one-time setup** required to deploy Occupy Uranus bot to production. After completing this guide, use [`DEPLOYMENT.md`](DEPLOYMENT.md) for ongoing code updates.

---

## Prerequisites

### Server Requirements

- **PHP:** 7.4 or higher
- **PHP Extensions:**
  - `curl` (required)
  - `json` (required)
  - `mbstring` (recommended)
  - `apcu` (optional, for rate limiting)
- **Web Server:** Apache/Nginx with HTTPS
- **SSL Certificate:** Required (Telegram requires HTTPS)
- **SSH Access:** For deployment
- **File Permissions:** Web server must write to `data/` directory

### Local Requirements

- **Telegram Bot Token** (from @BotFather)
- **SSH access** to your server
- **Git** (for deployment)
- **rsync** or **SFTP** (for file transfer)

---

## Step 1: Get Bot Token

If you don't have a bot yet:

1. Message [@BotFather](https://t.me/BotFather) on Telegram
2. Send `/newbot`
3. Follow prompts:
   - Bot name: "Occupy Uranus Bot" (or your choice)
   - Bot username: Must end in "bot" (e.g., `occupyuranusbot`)
4. Save the **bot token** (looks like: `123456789:ABCdefGHIjklMNOpqrsTUVwxyz`)

**Security:** Keep this token secret! Anyone with it can control your bot.

---

## Step 2: Server Setup

### Upload Files

**Option A: Using Git (Recommended)**

```bash
# On server
cd /your/web/root/
git clone https://github.com/yourusername/OccupyUranusGame_bot.git
cd OccupyUranusGame_bot
```

**Option B: Using deploy.sh**

```bash
# On local machine
cp deploy.config.example deploy.config
nano deploy.config  # Edit with your server details
./deploy.sh
```

### Set Directory Permissions

```bash
# On server
cd /path/to/OccupyUranusGame_bot
chmod 755 data/
chmod 777 data/sessions/
chmod 777 data/backups/
chmod 644 *.php
chmod 644 game/*.php bot/*.php api/game/*.php
```

**Why:**
- `755` on `data/` - Web server can write, but not world-writable
- `777` on subdirs - Session files need full write access
- `644` on PHP files - Readable but not executable

### Verify PHP Extensions

```bash
# On server
php -m | grep -E "curl|json|mbstring"
```

Should show:
```
curl
json
mbstring
```

If missing, install:
```bash
# Debian/Ubuntu
sudo apt install php-curl php-json php-mbstring

# CentOS/RHEL
sudo yum install php-curl php-json php-mbstring
```

---

## Step 3: Configure Bot Token

### Set Environment Variable (Recommended)

**Option A: For Apache (.htaccess)**

Create or edit `.htaccess` in bot directory:
```apache
SetEnv TELEGRAM_BOT_TOKEN "123456789:ABCdefGHIjklMNOpqrsTUVwxyz"
```

**Option B: For Nginx**

Add to your server block:
```nginx
location /OccupyUranusGame_bot {
    fastcgi_param TELEGRAM_BOT_TOKEN "123456789:ABCdefGHIjklMNOpqrsTUVwxyz";
    # ... other fastcgi params
}
```

**Option C: System Environment Variable**

Add to `/etc/environment`:
```bash
TELEGRAM_BOT_TOKEN="123456789:ABCdefGHIjklMNOpqrsTUVwxyz"
```

Then restart web server:
```bash
sudo systemctl restart apache2  # or nginx
```

### Verify Token is Set

```bash
# On server
cd /path/to/bot
php -r "echo getenv('TELEGRAM_BOT_TOKEN');"
```

Should print your token.

---

## Step 4: Generate Webhook Secret

**Generate a secure random secret:**

```bash
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;"
```

Output will look like:
```
a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456
```

**Copy this value** - you'll need it in the next steps.

---

## Step 5: Configure Webhook Secret

### Edit config.php on Server

```bash
# On server
nano /path/to/OccupyUranusGame_bot/config.php
```

Find this line:
```php
define('WEBHOOK_SECRET', '');
```

Replace with your generated secret:
```php
define('WEBHOOK_SECRET', 'a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456');
```

Save and exit (`Ctrl+X`, `Y`, `Enter` in nano).

---

## Step 6: Register Webhook with Telegram

**This tells Telegram where to send updates.**

### Get Your Webhook URL

Your webhook URL format:
```
https://yourdomain.com/path/to/uranus_game_bot.php
```

**Example:**
```
https://ethlovesuranus.space/OccupyUranusGame_bot/uranus_game_bot.php
```

### Register Webhook

**Method A: Using curl (Recommended)**

```bash
curl -X POST "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook" \
  -H "Content-Type: application/json" \
  -d '{
    "url": "https://yourdomain.com/path/to/uranus_game_bot.php",
    "secret_token": "a1b2c3d4e5f6789012345678901234567890abcdef1234567890abcdef123456"
  }'
```

**Replace:**
- `<YOUR_BOT_TOKEN>` - Your bot token from Step 1
- `https://yourdomain.com/path/to/uranus_game_bot.php` - Your webhook URL
- `secret_token` value - The webhook secret from Step 4

**Expected Response:**
```json
{
  "ok": true,
  "result": true,
  "description": "Webhook was set"
}
```

**Method B: Using TelegramAPI class**

```php
// On server
cd /path/to/bot
php -r "
require 'config.php';
require 'bot/TelegramAPI.php';
\$telegram = new TelegramAPI(BOT_TOKEN);
\$result = \$telegram->setWebhook('https://yourdomain.com/path/to/uranus_game_bot.php', WEBHOOK_SECRET);
var_dump(\$result);
"
```

---

## Step 7: Verify Webhook

### Check Webhook Status

```bash
curl "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getWebhookInfo" | jq
```

**Expected Output:**
```json
{
  "ok": true,
  "result": {
    "url": "https://yourdomain.com/path/to/uranus_game_bot.php",
    "has_custom_certificate": false,
    "pending_update_count": 0,
    "max_connections": 40,
    "ip_address": "1.2.3.4"
  }
}
```

**Check For:**
- ✅ `"url"` matches your webhook URL
- ✅ `"has_custom_certificate": false` (using public CA cert)
- ✅ `"pending_update_count": 0` (no backlog)
- ✅ No `"last_error_message"` field (means no errors)

**If you see errors:**
- `"last_error_date"` - Timestamp of last error
- `"last_error_message"` - Error description
- Common issues: SSL certificate invalid, webhook returns 403/404/500

---

## Step 8: Test the Bot

### Send a Message

1. Open Telegram
2. Find your bot (search for the username you created)
3. Send `/start` or `/game`

**Expected:** Bot responds with game menu

### Check Logs

```bash
# On server
tail -f /path/to/bot/data/bot.log
```

You should see:
```
[INFO] Webhook received update from user 123456
[INFO] User 123456 started new game
```

### Common Issues

#### Bot doesn't respond

**Check:**
1. Webhook registered? `getWebhookInfo` should show your URL
2. Webhook secret matches? config.php and Telegram must match
3. File permissions? Web server must write to `data/`
4. PHP errors? Check server error logs

**Debug:**
```bash
# Check diagnostic log (immediate writes)
tail -f /path/to/bot/data/diagnostic.log

# Check server error logs
tail -f /var/log/apache2/error.log  # or nginx error.log
```

#### Webhook returns 403 Forbidden

**Cause:** Webhook secret mismatch

**Fix:**
1. Verify `WEBHOOK_SECRET` in config.php
2. Re-register webhook with same secret:
   ```bash
   curl -X POST "https://api.telegram.org/bot<TOKEN>/setWebhook" \
     -H "Content-Type: application/json" \
     -d '{"url": "...", "secret_token": "..."}'
   ```

#### Webhook returns 500 Internal Server Error

**Cause:** PHP error or missing dependency

**Fix:**
1. Check PHP error logs
2. Verify all files uploaded correctly
3. Check file permissions
4. Verify PHP extensions installed

---

## Step 9: Configure Admin Access

### Add Your User ID

1. Send `/debug_userid` to your bot (if `FEATURE_DEBUG_COMMANDS` is true)
2. Bot responds with your Telegram user ID
3. Add to `config.php`:

```php
define('ADMIN_USER_IDS', [
    123456789,  // Your user ID
]);
```

**Admin abilities:**
- `/resetall` - Delete all sessions (with confirmation)
- `/debug_sessions` - List all active sessions
- Access to admin-only features

---

## Step 10: Disable Debug Mode

### Production Settings

Edit `config.php` on server:

```php
// REQUIRED for production
define('DEBUG_MODE', false);

// RECOMMENDED for production
define('FEATURE_DEBUG_COMMANDS', false);
```

**Why:**
- `DEBUG_MODE = false` - Reduces log verbosity, improves performance
- `FEATURE_DEBUG_COMMANDS = false` - Disables debug commands for all users (including `/debug_userid`)

**Exception:** Keep `FEATURE_DEBUG_COMMANDS = true` if you need admin commands (`/resetall`, `/debug_sessions`).

---

## Step 11: Security Hardening

### File Permissions

**Verify permissions:**
```bash
ls -la /path/to/bot/
ls -la /path/to/bot/data/
```

**Correct permissions:**
```
drwxr-xr-x  data/             (755)
drwxrwxrwx  data/sessions/    (777)
drwxrwxrwx  data/backups/     (777)
-rw-r--r--  uranus_game_bot.php (644)
-rw-r--r--  config.php        (644)
```

### Protect config.php

**Add to .htaccess:**
```apache
<Files "config.php">
    Order allow,deny
    Deny from all
</Files>
```

**Or for Nginx:**
```nginx
location ~ /config\.php$ {
    deny all;
}
```

### Enable Error Logging

**Ensure errors are logged, not displayed:**

In `php.ini` or `.htaccess`:
```
display_errors = Off
log_errors = On
error_log = /path/to/bot/data/php_errors.log
```

---

## Step 12: Backup Strategy

### What to Backup

**Critical (backup regularly):**
- `config.php` - Bot configuration
- `data/sessions/` - Active game states
- `data/leaderboards.json` - Player scores

**Optional (can regenerate):**
- `data/image_cache.json` - Telegram file IDs (regenerates automatically)
- `data/message_ids/` - Per-user message tracking (cleans up automatically)

### Backup Commands

```bash
# Backup sessions (daily)
cd /path/to/bot
tar -czf backups/sessions-$(date +%Y%m%d).tar.gz data/sessions/

# Backup config
cp config.php backups/config-$(date +%Y%m%d).php

# Keep last 30 days of backups
find backups/ -name "sessions-*.tar.gz" -mtime +30 -delete
```

### Automated Backup (Cron)

```bash
# Edit crontab
crontab -e

# Add daily backup at 2 AM
0 2 * * * cd /path/to/bot && tar -czf backups/sessions-$(date +\%Y\%m\%d).tar.gz data/sessions/
```

---

## Verification Checklist

Before announcing your bot to users:

- [ ] Bot token configured (environment variable or config.php)
- [ ] Webhook secret generated and set in config.php
- [ ] Webhook registered with Telegram (`getWebhookInfo` shows correct URL)
- [ ] Bot responds to `/start` and `/game` commands
- [ ] Sessions are saved (check `data/sessions/` directory)
- [ ] Logs are working (`data/bot.log` updates on each request)
- [ ] Admin access configured (your user ID in `ADMIN_USER_IDS`)
- [ ] Debug mode disabled (`DEBUG_MODE = false`)
- [ ] File permissions correct (755 for data/, 777 for subdirs)
- [ ] Backup strategy in place (manual or automated)
- [ ] SSL certificate valid (Telegram requires HTTPS)
- [ ] Server has enough disk space (sessions grow over time)

---

## Next Steps

### Ongoing Deployments

For code updates after initial setup:
- See [`DEPLOYMENT.md`](DEPLOYMENT.md) for using `deploy.sh`

### Monitoring

- Check logs daily: `tail -f data/bot.log`
- Monitor disk space: `df -h`
- Check webhook status weekly: `getWebhookInfo`

### Troubleshooting

- **Bot slow?** Check server load and PHP memory limits
- **Sessions lost?** Verify file permissions and disk space
- **Webhook errors?** Check `getWebhookInfo` for error messages
- **High traffic?** Consider rate limiting or Redis for sessions

---

## Reference

### Important Files

| File | Purpose | Backup? |
|------|---------|---------|
| `config.php` | Bot configuration | ✅ Yes |
| `data/sessions/` | Active game states | ✅ Yes |
| `data/leaderboards.json` | Player scores | ✅ Yes |
| `data/bot.log` | Application logs | ❌ No |
| `.htaccess` | Server configuration | ✅ Yes |

### Important URLs

| Purpose | URL |
|---------|-----|
| **Set Webhook** | `https://api.telegram.org/bot<TOKEN>/setWebhook` |
| **Get Webhook Info** | `https://api.telegram.org/bot<TOKEN>/getWebhookInfo` |
| **Delete Webhook** | `https://api.telegram.org/bot<TOKEN>/deleteWebhook` |
| **Get Updates** | `https://api.telegram.org/bot<TOKEN>/getUpdates` (polling mode) |

### Webhook Secret Header

Telegram sends webhook secret in HTTP header:
```
X-Telegram-Bot-Api-Secret-Token: your_secret_here
```

Bot validates in `uranus_game_bot.php`:
```php
$receivedToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
if (!hash_equals(WEBHOOK_SECRET, $receivedToken)) {
    http_response_code(403);
    exit;
}
```

---

## Code Verification Log

**Last Verified:** 2026-02-28

### Files Checked
- ✅ `config.php` - WEBHOOK_SECRET constant, ADMIN_USER_IDS, DEBUG_MODE
- ✅ `uranus_game_bot.php` - Webhook validation (lines 2020-2030)
- ✅ `bot/TelegramAPI.php` - setWebhook method

### Verified
- ✅ Webhook secret uses `X-Telegram-Bot-Api-Secret-Token` header
- ✅ Validation uses `hash_equals()` for timing-attack prevention
- ✅ Empty secret skips validation (for local dev)
- ✅ setWebhook accepts URL and secret_token parameters

---

**Setup Complete!** Your bot is now running in production.

For ongoing code updates, see [`DEPLOYMENT.md`](DEPLOYMENT.md).

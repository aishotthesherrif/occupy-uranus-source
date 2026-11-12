# Logging Configuration Guide

**Last Updated:** 2026-03-02
**Last Verified Against Code:** 2026-03-02

---

## Overview

The bot uses a two-tier logging system controlled by the `DEBUG_MODE` flag:

1. **bot.log** - Regular application logging (buffered)
2. **diagnostic.log** - Immediate webhook debugging (unbuffered)

---

## Configuration

### DEBUG_MODE Flag

**Location:** `config.php` line ~143

```php
define('DEBUG_MODE', false);  // Change to true for debugging
```

| Setting | Effect | When to Use |
|---------|--------|-------------|
| `false` | **Production mode** - Minimal logging, only errors | Normal operation |
| `true`  | **Debug mode** - Verbose logging, all details | Troubleshooting issues |

---

## Logging Levels

Both log files use the same levels:

| Level | Always Logged? | Purpose |
|-------|----------------|---------|
| `DEBUG` | ❌ Only if `DEBUG_MODE=true` | Detailed step-by-step execution |
| `INFO` | ✅ Always | Normal operations (game actions, etc.) |
| `WARNING` | ✅ Always | Unexpected but handled situations |
| `ERROR` | ✅ Always | API failures, validation errors |
| `CRITICAL` | ✅ Always | Fatal errors, crash scenarios |

---

## Log Files

### 1. bot.log

**Location:** `logs/bot.log`
**Purpose:** Application-level logging
**Buffered:** Yes (10 lines by default)

**What gets logged:**

#### DEBUG_MODE = false (Production):
```
[2026-02-26 14:23:45] [WARNING] Admin 123456 requested /resetall
[2026-02-26 14:23:46] [ERROR] Failed to send message to chat -123456
[2026-02-26 14:25:12] [INFO] User completed game on day 142
```

- Warnings (rate limits, unauthorized access)
- Errors (API failures)
- Important game events (victories, game overs)

#### DEBUG_MODE = true (Debug):
```
[2026-02-26 14:23:45] [DEBUG] User @alice (123456) in chat -789: /game
[2026-02-26 14:23:45] [DEBUG] Callback from user 123456: menu|continue
[2026-02-26 14:23:45] [DEBUG] Created session abc-123 for user 123456
[2026-02-26 14:23:46] [WARNING] Admin 789 requested /resetall
[2026-02-26 14:25:12] [INFO] User completed game on day 142
```

- Everything above PLUS:
- Every command received
- Every callback button pressed
- Session creation/loading
- Internal state changes

### 2. diagnostic.log

**Location:** `logs/diagnostic.log`
**Purpose:** Webhook-level debugging
**Buffered:** No (immediate write)

**What gets logged:**

#### DEBUG_MODE = false (Production):
```
[2026-02-26 14:23:45] [ERROR] Webhook secret validation FAILED - returning 403
[2026-02-26 14:23:46] [ERROR] TelegramAPI: HTTP 401 ERROR on .../sendMessage: Unauthorized
[2026-02-26 14:23:47] [CRITICAL] FATAL ERROR in webhook: Call to undefined function
```

- API authentication failures
- Network/connection errors
- Fatal PHP errors with stack traces

#### DEBUG_MODE = true (Debug):
```
[2026-02-26 14:23:45] [DEBUG] === WEBHOOK POST REQUEST RECEIVED ===
[2026-02-26 14:23:45] [DEBUG] Checking webhook secret...
[2026-02-26 14:23:45] [DEBUG] Webhook secret validated OK
[2026-02-26 14:23:45] [DEBUG] Flushing HTTP 200 response...
[2026-02-26 14:23:45] [DEBUG] Response flushed
[2026-02-26 14:23:45] [DEBUG] Instantiating OccupyUranusBot...
[2026-02-26 14:23:45] [DEBUG] Constructor: Creating TelegramAPI...
[2026-02-26 14:23:45] [DEBUG] Constructor: TelegramAPI created OK
[2026-02-26 14:23:45] [DEBUG] processWebhook: Reading php://input...
[2026-02-26 14:23:45] [DEBUG] processWebhook: Received 342 bytes
[2026-02-26 14:23:45] [DEBUG] sendMessage: Calling sendMessage to chat -123456
[2026-02-26 14:23:46] [ERROR] TelegramAPI: HTTP 401 ERROR on .../sendMessage: Unauthorized
[2026-02-26 14:23:46] [DEBUG] === WEBHOOK PROCESSING SUCCESSFUL ===
```

- Everything above PLUS:
- Every webhook lifecycle step
- HTTP 200 response timing
- Class instantiation
- API call attempts
- Message routing

---

## Typical Usage Patterns

### Production (Recommended)

```php
// config.php
define('DEBUG_MODE', false);
```

**Log sizes:**
- `bot.log`: ~5-10 KB per 1000 requests
- `diagnostic.log`: Near-empty (only errors)

**What you see:**
- Admin actions
- API failures
- Critical errors
- Game completions

### Troubleshooting

```php
// config.php
define('DEBUG_MODE', true);
```

**Log sizes:**
- `bot.log`: ~50-100 KB per 1000 requests
- `diagnostic.log`: ~100-200 KB per 1000 requests

**What you see:**
- Every user action
- Every API call
- Full webhook lifecycle
- Detailed error context

**⚠️ Remember to set back to `false` after debugging!**

---

## Log Rotation

For production use with `DEBUG_MODE=false`, logs are manageable but you may want rotation:

### Manual Rotation

```bash
# Compress and archive logs monthly
cd /path/to/bot/data
gzip bot.log
mv bot.log.gz bot-$(date +%Y-%m).log.gz
touch bot.log
chmod 666 bot.log

# Same for diagnostic.log
gzip diagnostic.log
mv diagnostic.log.gz diagnostic-$(date +%Y-%m).log.gz
touch diagnostic.log
chmod 666 diagnostic.log
```

### Automatic Rotation (logrotate)

Create `/etc/logrotate.d/occupy-uranus`:

```
/path/to/bot/logs/bot.log {
    weekly
    rotate 4
    compress
    delaycompress
    missingok
    notifempty
    create 0666 www-data www-data
}

/path/to/bot/logs/diagnostic.log {
    weekly
    rotate 4
    compress
    delaycompress
    missingok
    notifempty
    create 0666 www-data www-data
}
```

---

## Monitoring Logs

### Check for Errors

```bash
# Recent errors in bot.log
grep -i error logs/bot.log | tail -20

# Recent errors in diagnostic.log
grep -i error logs/diagnostic.log | tail -20

# Critical errors only
grep CRITICAL logs/diagnostic.log
```

### Watch Live

```bash
# Watch both logs in real-time
tail -f logs/bot.log logs/diagnostic.log
```

### Analyze API Failures

```bash
# Count API error types
grep "TelegramAPI:" logs/diagnostic.log | cut -d: -f4 | sort | uniq -c | sort -rn
```

---

## Performance Impact

| Mode | bot.log | diagnostic.log | Disk I/O | Impact |
|------|---------|----------------|----------|---------|
| Production (`DEBUG_MODE=false`) | Minimal | Near-zero | Low | ✅ Negligible |
| Debug (`DEBUG_MODE=true`) | Verbose | Very verbose | Moderate | ⚠️ Acceptable for debugging |

**Recommendation:** Use `DEBUG_MODE=true` only when actively troubleshooting. The performance impact is acceptable but logs grow quickly.

---

## Troubleshooting Common Issues

### 1. Bot Not Responding

```bash
# Enable debug mode
vim config.php  # Set DEBUG_MODE = true

# Trigger a webhook (send /game to bot)

# Check diagnostic.log
cat logs/diagnostic.log

# Look for:
# - HTTP 401/403 errors → BOT_TOKEN issue
# - cURL errors → Network/firewall issue
# - CRITICAL errors → PHP crash
```

### 2. Logs Growing Too Large

```bash
# Check current size
du -h data/*.log

# If DEBUG_MODE is true, set it to false
vim config.php  # Set DEBUG_MODE = false

# Rotate logs immediately
./rotate-logs.sh  # Or manual commands above
```

### 3. No Logs Being Written

```bash
# Check directory permissions
ls -la data/

# Should be writable by web server user (www-data, nginx, etc.)
sudo chown -R www-data:www-data data/
sudo chmod 755 data/
sudo chmod 666 data/*.log
```

---

## Code Examples

### Adding New Log Statements

```php
// In uranus_game_bot.php or GameEngine.php:

// DEBUG - only logged when DEBUG_MODE=true
$this->log("Processing event: {$event['id']}", 'DEBUG');

// INFO - always logged, normal operations
$this->log("User completed game on day {$day}", 'INFO');

// WARNING - always logged, unexpected situations
$this->log("Rate limited user $userId", 'WARNING');

// ERROR - always logged, failures
$this->log("Failed to send message: {$e->getMessage()}", 'ERROR');

// Diagnostic logging (for webhook/API debugging)
OccupyUranusBot::logDiagnostic("Custom checkpoint reached", 'DEBUG');
OccupyUranusBot::logDiagnostic("API call failed: $error", 'ERROR');
```

---

## Summary

✅ **Production:** `DEBUG_MODE = false` (small logs, errors only)
🔧 **Debugging:** `DEBUG_MODE = true` (verbose logs, everything)
🔄 **After Fix:** Set back to `false` and rotate logs

The logging system is designed to be minimal in production while providing comprehensive debugging when needed.

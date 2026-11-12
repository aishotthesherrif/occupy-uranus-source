# Webhook Debugging Summary

**Date:** 2026-02-26
**Issue:** Bot not responding to webhooks

---

## Root Cause Analysis

Based on diagnostic logs from the remote server:

### ✅ What's Working:
1. **Webhooks are being received** - POST requests reach the server
2. **All classes instantiate correctly** - No fatal errors during initialization
3. **JSON parsing works** - Telegram updates are decoded successfully
4. **Command routing works** - Commands like `/resetall` are properly identified and routed

### ❌ The Problem:
**Telegram API calls are failing silently**

The diagnostic logs show:
```
[2026-02-26 22:18:54] processWebhook: Routing command '/resetall' with params ''
[2026-02-26 22:18:55] processWebhook: routeCommand returned false  ← API call failed
```

The bot receives the command, processes it, tries to send a response via `TelegramAPI::sendMessage()`, but the API call returns `false`.

---

## Changes Made

### 1. **Added Diagnostic Logging System**

**File: `config.php`**
- Added `DIAGNOSTIC_LOG` constant → `data/diagnostic.log`

**File: `uranus_game_bot.php`**
- Added `OccupyUranusBot::logDiagnostic()` static method
- Logs written immediately to `data/diagnostic.log` (no buffering)

### 2. **Webhook Entry Point Diagnostics**

**Location:** `uranus_game_bot.php` lines ~1955-1995

Logs every step:
- Webhook POST request received
- Webhook secret validation
- HTTP 200 response flush
- Bot instantiation (each dependency)
- processWebhook() execution
- Fatal error capture with stack trace

### 3. **processWebhook() Diagnostics**

**Location:** `uranus_game_bot.php` lines ~390-475

Logs:
- php://input reading (byte count)
- JSON decoding status
- Callback query vs message detection
- Command parsing and routing
- Return values from handlers

### 4. **TelegramAPI Error Logging**

**File: `classes/TelegramAPI.php`**

Added `logDiagnostic()` method that logs to `data/diagnostic.log`:
- cURL errors (connection failures, timeouts)
- HTTP error codes (400, 401, 403, 404, etc.)
- Telegram API error descriptions
- Retry exhaustion

### 5. **sendMessage() Wrapper Diagnostics**

**Location:** `uranus_game_bot.php` lines ~1543-1575

Logs:
- API method calls (sendPhoto vs sendMessage)
- Success/failure of each API call
- Fallback behavior when photo send fails
- Message ID storage for cleanup

---

## How to Use Diagnostic Logs

### 1. Deploy Updated Files
```bash
# Upload to server:
# - uranus_game_bot.php
# - config.php
# - classes/TelegramAPI.php
```

### 2. Trigger a Webhook
Send any command to your bot:
```
/game
/help
/resetall
```

### 3. Check Diagnostic Log
```bash
cat data/diagnostic.log
```

### Sample Output (Success):
```
[2026-02-26 22:18:54] === WEBHOOK POST REQUEST RECEIVED ===
[2026-02-26 22:18:54] No webhook secret configured, skipping validation
[2026-02-26 22:18:54] Flushing HTTP 200 response...
[2026-02-26 22:18:54] Response flushed
[2026-02-26 22:18:54] Instantiating OccupyUranusBot...
[2026-02-26 22:18:54] Constructor: Creating TelegramAPI...
[2026-02-26 22:18:54] Constructor: TelegramAPI created OK
[2026-02-26 22:18:54] Constructor: Creating GameSessionManager...
[2026-02-26 22:18:54] Constructor: GameSessionManager created OK
[2026-02-26 22:18:54] Constructor: Creating GameEngine...
[2026-02-26 22:18:54] Constructor: GameEngine created OK
[2026-02-26 22:18:54] Bot instantiated OK
[2026-02-26 22:18:54] Calling processWebhook()...
[2026-02-26 22:18:54] processWebhook: Reading php://input...
[2026-02-26 22:18:54] processWebhook: Received 486 bytes
[2026-02-26 22:18:54] processWebhook: Decoding JSON...
[2026-02-26 22:18:54] processWebhook: JSON decoded OK
[2026-02-26 22:18:54] processWebhook: Processing message...
[2026-02-26 22:18:54] processWebhook: Routing command '/resetall' with params ''
[2026-02-26 22:18:54] sendMessage: Calling sendMessage (text-only) to chat -1002860538306
[2026-02-26 22:18:55] TelegramAPI: HTTP 401 ERROR on .../sendMessage: Unauthorized  ← BOT_TOKEN issue!
[2026-02-26 22:18:55] sendMessage: sendMessage returned FALSE
[2026-02-26 22:18:55] sendMessage: API call FAILED - returning false to caller
[2026-02-26 22:18:55] processWebhook: routeCommand returned false
[2026-02-26 22:18:55] processWebhook() completed
[2026-02-26 22:18:55] Log buffer flushed, webhook processing complete
[2026-02-26 22:18:55] === WEBHOOK PROCESSING SUCCESSFUL ===
```

---

## Common Issues to Look For

### 1. **HTTP 401 Unauthorized**
```
TelegramAPI: HTTP 401 ERROR on .../sendMessage: Unauthorized
```
**Fix:** Check `BOT_TOKEN` in config.php or environment variable

### 2. **HTTP 403 Forbidden**
```
TelegramAPI: HTTP 403 ERROR on .../sendMessage: Forbidden: bot was blocked by the user
```
**Fix:** User blocked the bot. Have them unblock and restart the bot.

### 3. **HTTP 400 Bad Request**
```
TelegramAPI: HTTP 400 ERROR on .../sendMessage: Bad Request: chat not found
```
**Fix:** Invalid chat ID. Check session's `preferred_chat` field.

### 4. **cURL Timeout**
```
TelegramAPI: cURL ERROR on .../sendMessage: Operation timed out after 10000 milliseconds
```
**Fix:** Network/firewall issue. Check server's outbound HTTPS access.

### 5. **cURL DNS Error**
```
TelegramAPI: cURL ERROR on .../sendMessage: Could not resolve host: api.telegram.org
```
**Fix:** DNS configuration issue on server.

---

## Next Steps

1. **Check `BOT_TOKEN`** - Most likely cause is misconfigured token
2. **Verify network access** - Ensure server can reach `api.telegram.org`
3. **Check PHP version** - Requires PHP 7.4+ for typed properties
4. **Review file permissions** - Ensure `data/` is writable

---

## Cleanup

Once the issue is resolved, you can:

1. **Keep diagnostic logging** - Useful for future debugging
2. **Disable it** - Comment out `logDiagnostic()` calls for performance
3. **Clean log file** - `rm data/diagnostic.log` to start fresh

The diagnostic system has minimal performance impact since it writes directly to disk without buffering.

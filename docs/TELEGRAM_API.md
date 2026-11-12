# Telegram API Reference

**Last Updated:** 2026-03-02
**Last Verified Against Code:** 2026-03-02

Complete reference for Telegram Bot API integration patterns, constraints, and behaviors in Occupy Uranus.

---

## Overview

This bot uses the **Telegram Bot API** via webhook. All API interactions go through `bot/TelegramAPI.php`.

**Integration Method:** Webhook (not polling)
**Parse Mode:** HTML (everywhere)
**Message Strategy:** Edit-in-place (avoid chat spam)

---

## Hard Limits (Telegram-Imposed)

### callback_data

**Limit:** 64 bytes maximum
**Enforced:** Yes - `stampOwnerIntoKeyboard()` logs error if exceeded
**Pattern:** `category|action|param|u{userId}`

**Examples:**
```php
// Valid (36 bytes)
menu|continue|u123456

// Valid (44 bytes)
shop|fuel|100|u123456

// Valid (59 bytes)
event|asteroid_field|navigate|u123456

// INVALID (67 bytes) - would be rejected
event|very_long_event_identifier|choice_id|u123456
```

**Code Reference:**
- Enforcement: `uranus_game_bot.php:stampOwnerIntoKeyboard()`
- Format: Lines 226-236

**Best Practices:**
- Use short IDs in events.json (`asteroid_field`, not `asteroid_field_dense_region_v2`)
- Validate length before creating buttons
- Log errors (don't silently truncate)

---

### Message Text

**Limit:** 4,096 characters
**Currently Enforced:** No (normal gameplay doesn't approach this)
**When to Enforce:** If adding verbose features (long stories, detailed logs)

**Code Reference:**
- Not currently enforced
- Add check in `TelegramAPI::sendMessage()` if needed

---

### Photo Caption

**Limit:** 1,024 characters
**Enforced:** Yes - `TelegramAPI::sendMessage()` truncates with warning

**Code Reference:**
- Enforcement: `TelegramAPI.php:sendMessage()`
- Truncation: `substr($caption, 0, 1020) . '...'`

**Best Practices:**
- Keep captions under 900 characters for safety margin
- Put important info early (in case of truncation)
- Use message text for verbose content, not caption

---

### answerCallbackQuery Text

**Limit:** 200 characters
**Currently Enforced:** No (all responses are short)

**Code Reference:**
- Usage: `uranus_game_bot.php:handleCallbackQuery()`
- Typical use: `answerCallbackQuery($callbackId)` (no text)

---

## Performance Constraints

### Webhook Timeout

**Limit:** Telegram expects HTTP 200 within ~10 seconds
**Our Approach:** Flush response immediately, then process

**Implementation:**
```php
TelegramAPI::flushResponse();
// HTTP 200 sent immediately
// Now process the request (no timeout risk)
```

**Code Reference:**
- `TelegramAPI.php:flushResponse()` - Lines 422-428
- Called in: `uranus_game_bot.php` - Early in request handling

**Why:** Prevents Telegram from retrying the request if processing takes too long.

---

### Rate Limits

**Limit:** ~30 messages/second per chat (not per bot)
**Currently Enforced:** No (normal gameplay ~1 message every 5-30 seconds)
**When Needed:** Bulk operations, admin commands, rapid-fire events

**If Needed:**
- Install `php-apcu` extension
- Implement rate limiting in TelegramAPI
- Track message timestamps per chat

**Code Reference:**
- Not currently implemented
- Would go in: `TelegramAPI.php:sendMessage()`

---

### Retry Behavior

**Telegram's Behavior:**
- Retries on 5xx errors
- Disables webhook after repeated failures (typically 10+ consecutive)
- Sends warning to bot owner

**Our Retry Logic:**
```php
// In TelegramAPI::makeRequest()
$maxRetries = 3;
$retryDelay = 1000; // ms, doubles each attempt

// Exponential backoff: 1s, 2s, 4s
```

**Code Reference:**
- Retry logic: `TelegramAPI.php:makeRequest()` - Lines 385-410
- Error handling: Lines 412-420

**Best Practices:**
- Don't retry 4xx errors (client error, won't succeed)
- Do retry 5xx errors (server error, might be transient)
- Log all failures for debugging

---

## UX Requirements

### answerCallbackQuery

**CRITICAL:** Must call for EVERY callback_query

**Why:** Removes loading spinner on user's device

**Pattern:**
```php
private function handleCallbackQuery(array $callbackQuery) {
    $callbackId = $callbackQuery['id'];

    // ... route to handler ...

    // ALWAYS call this before returning
    $this->telegram->answerCallbackQuery($callbackId);
}
```

**What Happens If Forgotten:**
- User sees loading spinner for ~30 seconds
- Poor UX (looks broken)
- User may spam-click button

**Code Reference:**
- Implementation: `TelegramAPI.php:answerCallbackQuery()`
- Called in: `uranus_game_bot.php:handleCallbackQuery()` - Line ~1600

---

### Message Editing

**Preferred:** Edit messages in place
**Avoid:** Sending new messages repeatedly

**Why:**
- Avoids chat spam
- Preserves scroll position
- Better UX in groups
- Uses fewer API calls

**Pattern:**
```php
// Get previous message_id
$previousMessageId = $this->getMessageId($userId);

if ($previousMessageId) {
    // Edit existing message
    $this->telegram->editMessageText($chatId, $previousMessageId, $text, $keyboard);
} else {
    // Send new message (first time)
    $messageId = $this->telegram->sendMessage($chatId, $text, $keyboard);
    $this->saveMessageId($userId, $messageId);
}
```

**Code Reference:**
- Message tracking: `uranus_game_bot.php:getLastMessageId()`, `storeLastMessageId()`, `deleteLastMessage()`
- Storage: Per-user files in `logs/message_ids/{userId}.json` (one file per user, no race conditions)

---

### Button Text Length

**Recommendation:** Keep under 20 characters
**Why:** Mobile display constraints

**Examples:**
```
✅ Good:
▶️ Continue (9 chars)
🛒 Shop (6 chars)
🗺️ Map (5 chars)

⚠️ Acceptable:
📊 Journey Status (16 chars)
🏪 Visit Station Shop (19 chars)

❌ Too Long:
📊 View Detailed Journey Status and Progress (45 chars)
```

**Code Reference:**
- Button creation: `ViewManager.php:buildActionKeyboard()`

---

## Parse Mode

### HTML Everywhere

**Setting:** `parse_mode: 'HTML'` on every message
**Never Use:** Markdown (inconsistent)

**Enforcement:**
```php
// In TelegramAPI.php
$payload['parse_mode'] = 'HTML';  // Lines 128, 192, 208, 248
```

**Supported Tags:**
```html
<b>Bold text</b>
<i>Italic text</i>
<code>Monospace</code>
<pre>Preformatted block</pre>
<a href="url">Link text</a>
```

**Not Supported:**
- ❌ Markdown (**bold**, *italic*)
- ❌ Custom HTML (divs, spans, classes)
- ❌ JavaScript

**Escaping Required:**
- `<` → `&lt;`
- `>` → `&gt;`
- `&` → `&amp;`

**Current Status:** Not escaping (bot-generated content only, no user input)

**Code Reference:**
- Parse mode setting: `TelegramAPI.php:sendMessage()` - Line 128

---

## Security Patterns

### Owner Stamping

**Pattern:** Add `|u{userId}` to all callback_data

**Why:** Prevents button hijacking in groups

**Example:**
```php
// Without owner stamp (VULNERABLE)
'callback_data' => 'shop|fuel|100'
// Any user in group could press Alice's shop button!

// With owner stamp (SECURE)
'callback_data' => 'shop|fuel|100|u123456'
// Validated: only user 123456 can use this button
```

**Implementation:**
```php
$keyboard = stampOwnerIntoKeyboard($keyboard, $userId);
// Automatically adds |u{userId} to all callback_data
```

**Validation:**
```php
// In callback handler
if (/* owner doesn't match */) {
    $this->telegram->answerCallbackQuery($callbackId,
        "❌ This button belongs to another player");
    return;
}
```

**Code Reference:**
- Stamping: `uranus_game_bot.php:stampOwnerIntoKeyboard()`
- Validation: `uranus_game_bot.php:handleCallbackQuery()` - Owner check

---

### Webhook Secret

**Pattern:** Validate all incoming webhooks

**Implementation:**
```php
$webhookSecret = $_GET['secret'] ?? '';
if (!hash_equals(WEBHOOK_SECRET, $webhookSecret)) {
    http_response_code(403);
    exit('Forbidden');
}
```

**Why:** Prevents unauthorized webhook POSTs

**Code Reference:**
- Validation: `uranus_game_bot.php` - Lines 80-84
- Secret: `config.php` - `WEBHOOK_SECRET` constant

---

## Common Patterns

### Sending a Message with Image

```php
$text = "Your journey continues!";
$imageUrl = "https://example.com/space.jpg";
$keyboard = [/* inline keyboard */];

$this->sendMessage($chatId, $text, $imageUrl, $keyboard, $userId,
    $threadId, $username, $displayName);
```

**Code Reference:**
- Wrapper: `uranus_game_bot.php:sendMessage()`
- Delegates to: `TelegramAPI.php:sendMessage()`

---

### Editing a Message

```php
$messageId = $this->getMessageId($userId);
if ($messageId) {
    $this->telegram->editMessageText($chatId, $messageId, $newText, $newKeyboard);
}
```

**Code Reference:**
- `TelegramAPI.php:editMessageText()`

---

### Deleting a Message

```php
$this->telegram->deleteMessage($chatId, $messageId);
// Remove from tracking
$this->deleteMessageId($userId);
```

**Code Reference:**
- `TelegramAPI.php:deleteMessage()`
- Tracking: `uranus_game_bot.php:deleteMessageId()`

---

### Answering a Callback

```php
// No notification
$this->telegram->answerCallbackQuery($callbackId);

// With notification
$this->telegram->answerCallbackQuery($callbackId, "✅ Purchased!");
```

**Code Reference:**
- `TelegramAPI.php:answerCallbackQuery()`

---

## PHP Implementation Patterns

### BotController Pattern (Standard for Handlers)

**Purpose:** Unified entry point for game interactions (`bot/BotController.php`). A button becomes a `GameService::act()` call and the outcome becomes a screen. Use this pattern for most handlers.

#### When to Use BotController

✅ **Use for:**
- View-only handlers (show screen, no state change)
- Standard actions (advance_day, rest, hunt, return)
- Handlers that fit: validate → action → view flow

❌ **Don't use for:**
- Callback routing dispatchers
- Admin/debug commands
- Complex multi-step flows (onboarding, chat switching)
- Special cases that don't fit standard pattern

#### Pattern A: View-Only Handler (3 lines)

```php
private function handleMyView($userId, $chatId, $threadId, $username, $displayName) {
    return $this->routeViaArchitecture('my_view', $userId, $chatId, [],
        $threadId, $username, $displayName);
}
```

**Use for:** Status, Map, Shop, Station, Main Menu, etc.

#### Pattern B: Handler with Validation (12 lines)

```php
private function handleMyAction($userId, $chatId, $threadId, $username, $displayName) {
    // Validation only (business rules stay in GameEngine)
    $session = $this->loadSession($userId);   // display copy (the save plus the bot's aesthetic)
    if ($session === null) {
        return $this->sendNoSessionMessage($chatId, $userId, $threadId, $username, $displayName);
    }
    if ($session['status'] !== 'required_status') {
        return $this->sendMessage($chatId, "❌ Cannot perform this action now",
            null, null, $userId, $threadId, $username, $displayName);
    }

    // Delegate to BotController
    return $this->routeViaArchitecture('my_action', $userId, $chatId, [],
        $threadId, $username, $displayName);
}
```

**Use for:** Actions that need pre-validation before BotController

#### Pattern C: Handler with Context

```php
private function handleMyView($userId, $chatId, $threadId, $username, $displayName) {
    return $this->routeViaArchitecture('my_view', $userId, $chatId, [
        'chatId' => $chatId,
        'special_data' => $someData,
    ], $threadId, $username, $displayName);
}
```

**Use for:** Views that need extra context data

**Code Reference:**
- BotController: `bot/BotController.php`
- 15 handlers use this pattern in `uranus_game_bot.php`

---

### Session Flow Pattern (CRITICAL)

**Rule:** The bot changes the game only through `GameService::act()`. It never calls a GameEngine rule or saves a session itself.

#### ✅ Correct Pattern

```php
// The service loads the save, runs the rule, saves, records telemetry
$result = $this->game->act($userId, 'advance_day', [], 'bot');
// $result = ['session' => ..., 'outcome' => 'day_advanced', 'data' => [...]]
```

For a screen that only needs to *show* the game, read it: `$this->loadSession($userId)` (the save plus the bot's aesthetic). That copy is for display; it is never saved.

#### ❌ Wrong Pattern

```php
$session = $this->sessionManager->loadSession($userId);
$result = $this->gameEngine->advanceDay($session);   // ❌ bypasses GameService
$this->sessionManager->saveSession($userId, $session); // ❌ and would save stale state
```

**Why:** the Mini-App, telemetry and the leaderboard all depend on every move going through the same door. (Rules take a session BY VALUE and return the new one in `result['session']`, which is why saving the old variable would lose the move.)

**Code Reference:**
- `game/GameService.php` class comment (actions, outcomes, reason codes)
- See `docs/ARCHITECTURE.md` for the rules between layers

---

### sendMessage Wrapper Pattern (CRITICAL)

**Rule:** ALWAYS use the sendMessage wrapper, never bypass to TelegramAPI directly.

#### ✅ Correct Pattern

```php
// Use the wrapper in uranus_game_bot.php
$this->sendMessage($chatId, $text, $imageUrl, $keyboard, $userId,
    $threadId, $username, $displayName);
```

#### ❌ Wrong Pattern (Bypasses Tracking)

```php
// ❌ WRONG - Bypasses message tracking, owner stamping, GAMER header
$this->telegram->sendMessage($chatId, $text, $keyboard);
```

**Why:** Wrapper provides:
- Message ID tracking for edit-in-place
- Owner stamping (security)
- GAMER header injection
- Consistent routing logic

**Exceptions:** `deleteMessage()` and `answerCallbackQuery()` can call TelegramAPI directly.

**Code Reference:**
- Wrapper implementation: `uranus_game_bot.php:sendMessage()`

---

## Quick Start Workflows

### Adding a New View (5 minutes)

```bash
# 1. Read the view catalog first
grep "View Name" docs/GAME_VIEWS.md

# 2. Add view method to ViewManager.php (e.g., renderMyView())
# 3. Add case to ViewManager::render() switch statement
# 4. Add button mapping in BotController::getViewForButton()
# 5. Create handler using Pattern A:
```

```php
private function handleMyView($userId, $chatId, $threadId, $username, $displayName) {
    return $this->routeViaArchitecture('my_view', $userId, $chatId, [],
        $threadId, $username, $displayName);
}
```

```bash
# 6. Route callback in handleMenuCallback() or handleActionCallback()
```

**Reference:** See `docs/GAME_VIEWS.md` for all existing views

---

### Debugging a Button Not Working (2 minutes)

```bash
# 1. Check logs
tail -f logs/bot.log

# 2. Look for callback_data in logs
# 3. Verify format: category|action|param|u{userId}
# 4. Check handler exists in handleMenuCallback/handleActionCallback
# 5. Verify answerCallbackQuery is called (prevents loading spinner)
```

**Common issues:**
- Missing `answerCallbackQuery()` → User sees loading spinner forever
- Wrong callback_data format → Handler not found
- Exceeds 64 bytes → Telegram rejects it
- Not using sendMessage wrapper → Message not tracked

---

### Testing a Change Locally (3 minutes)

```bash
# Terminal 1: Start PHP server
php -S localhost:8000

# Terminal 2: Start ngrok
ngrok http 8000

# Terminal 3: Monitor logs
tail -f logs/bot.log

# In Telegram: Test your change
# Check logs for errors
```

**Full setup:** See `docs/DEVELOPMENT.md`

---

### Adding a New Action (10 minutes)

```bash
# 1. Add the rule to GameEngine.php (returns data + reason codes, never text)
# 2. Add the action to GameService::runRule() and map its result to an outcome
# 3. Add the button to BotController::BUTTON_ACTIONS and the outcome to screenFor()
# 4. Write the bot text in GameText; the handler works via routeViaArchitecture()
# 5. The Mini-App needs its own handleOutcome() case and words in messages.js
```

**Example - New action in GameEngine.php:**
```php
public function myNewAction(array $session): array {
    // Game logic here
    $session['some_field'] = $newValue;

    return [
        'success' => true,
        'session' => $session,
        'some_amount' => 20,          // data, not text
    ];
}
```

**Example - Route in GameService.php:**
```php
// runRule()
'my_action' => $this->myAction($session),

private function myAction(array $session): array {
    $r = $this->engine->myNewAction($session);
    return $this->out($r['session'], 'my_action_done', ['some_amount' => $r['some_amount']]);
}
```

**Reference:** See `docs/ARCHITECTURE.md` for class responsibilities

---

## Where to Add New Code

### New Game Mechanic
1. **Constant** → `config.php`
2. **Logic** → `game/GameEngine.php` (data and reason codes only)
3. **Action and outcome** → `game/GameService.php`
4. **Bot UI** → `bot/ViewManager.php`, `bot/GameText.php`, screen choice in `bot/BotController.php`
5. **Mini-App UI** → `mini-app/js/app.js`, `messages.js`

### New View/Screen
1. Create view method in `ViewManager.php` (e.g., `renderMyView()`)
2. Add case to `ViewManager::render()` switch statement
3. Add button-to-view mapping in `BotController::getViewForButton()`
4. Create handler in `uranus_game_bot.php` using the BotController pattern
5. Route callback in `handleMenuCallback()` or `handleActionCallback()`

### New Action (Game State Change)
1. Add the rule to `game/GameEngine.php` (returns `['session' => ..., 'success' => ...]` plus data; refusals carry a `reason` code)
2. Add the action to `GameService::runRule()` and map it to an outcome
3. Add the button to `BotController::BUTTON_ACTIONS` and the outcome to `BotController::screenFor()`
4. Handler works via `routeViaArchitecture()`

### New Event
- Add to `data/events.json` (no code changes needed)

### New Waypoint
- Add to `data/waypoints.json` + image to `media/`

**Full codebase structure:** See `docs/CODEBASE_STRUCTURE.md`

---

## Common Pitfalls

### 1. Bypassing the sendMessage Wrapper

⚠️ **Always use the sendMessage wrapper** (see "sendMessage Wrapper Pattern" above)

❌ **Wrong:** `$this->telegram->sendMessage(...)` - Bypasses tracking, owner stamping, routing

✅ **Correct:** `$this->sendMessage($chatId, $text, null, $keyboard, $userId, ...)`

---

### 2. Not Using Returned Session

⚠️ **GameEngine returns modified session - you MUST use it!** (see "Session Flow Pattern" above)

❌ **Wrong:** Using original `$session` after GameEngine call - saves stale state!

✅ **Correct:** `$session = $result['session'];` - Use returned copy before saving

---

### 3. Forgetting answerCallbackQuery

❌ **Wrong:**
```php
private function handleMenuCallback(...) {
    // Process callback
    return $this->sendMessage(...); // User sees loading spinner forever!
}
```

✅ **Correct:**
```php
private function handleCallbackQuery(array $callbackQuery) {
    $callbackId = $callbackQuery['id'];
    // ... route to handler ...
    $this->telegram->answerCallbackQuery($callbackId); // Always called!
}
```

**Why:** Telegram shows loading spinner until answered.

---

### 4. Exceeding callback_data Limit

❌ **Wrong:**
```php
'callback_data' => 'event|' . $veryLongEventId . '|' . $veryLongChoiceId . '|u' . $userId
// Could exceed 64 bytes!
```

✅ **Correct:**
- Use short IDs in events.json (`asteroid_field`, not `asteroid_field_dense_region_v2`)
- Validate in `stampOwnerIntoKeyboard()` (already implemented)
- Log error if exceeded, don't silently truncate

See "callback_data" section above for details.

---

### 5. Mixing Concerns Between Classes

❌ **Wrong:** Formatting in GameEngine, game logic in ViewManager

✅ **Correct:** See `docs/ARCHITECTURE.md` for separation of concerns

**Quick check:**
- GameEngine = pure logic, returns data and reason codes (no text)
- GameService = the one door: runs rules, saves, telemetry
- ViewManager / GameText / BotController = bot screens and wording only, no game rules

---

## Error Handling

### API Error Types

| HTTP Code | Meaning | Retry? | Example |
|-----------|---------|--------|---------|
| 200 | Success | - | Request succeeded |
| 400 | Bad Request | No | Invalid chat_id |
| 401 | Unauthorized | No | Bad bot token |
| 403 | Forbidden | No | Bot blocked by user |
| 404 | Not Found | No | Message to edit doesn't exist |
| 429 | Too Many Requests | Yes | Rate limit hit |
| 500 | Internal Server Error | Yes | Telegram server issue |
| 502/503 | Bad Gateway / Unavailable | Yes | Temporary outage |

**Retry Logic:**
- 4xx errors: Log and return (no retry)
- 5xx errors: Retry with exponential backoff
- 429 errors: Respect Retry-After header (if present)

**Code Reference:**
- Error handling: `TelegramAPI.php:makeRequest()` - Lines 412-420

---

### Common Error Scenarios

**"Message to edit not found"**
- Cause: User deleted message or it expired
- Solution: Send new message instead of editing

**"Forbidden: bot was blocked by the user"**
- Cause: User blocked the bot
- Solution: Delete session, log event, don't retry

**"Bad Request: message text is empty"**
- Cause: Empty text passed to API
- Solution: Validate text before sending

**Code Reference:**
- Error logging: `TelegramAPI.php:makeRequest()` - Error handling block

---

## Message Management

### Message ID Tracking

**Purpose:** Know which message to edit/delete

**Storage:** Per-user files in `data/message_ids/{userId}.json`

**Structure (per user file):**
```json
{
    "chatId": {
        "id": 789,
        "ts": 1709251200
    },
    "chatId_threadId": {
        "id": 456,
        "ts": 1709251300
    }
}
```

**Operations:**
```php
// Save
$this->storeLastMessageId($userId, $chatId, $messageId, $threadId);

// Retrieve
$messageId = $this->getLastMessageId($userId, $chatId, $threadId);

// Delete (and clear tracking)
$this->deleteLastMessage($userId, $chatId, $threadId);
```

**Pruning:** Stale per-user files removed probabilistically (about 2% of requests) based on `MESSAGE_IDS_MAX_AGE`.

**Code Reference:**
- Implementation: `uranus_game_bot.php:storeLastMessageId()`, `getLastMessageId()`, `deleteLastMessage()`
- Pruning: `uranus_game_bot.php:pruneMessageIds()`

---

## Webhook Setup

### Registering the Webhook

```bash
curl -X POST https://api.telegram.org/bot$BOT_TOKEN/setWebhook \
  -d "url=https://yourdomain.com/uranus_game_bot.php?secret=YOUR_SECRET"
```

**Parameters:**
- `url` - Your webhook endpoint (HTTPS required)
- `secret` - Query parameter for validation (recommended)

---

### Checking Webhook Status

```bash
curl https://api.telegram.org/bot$BOT_TOKEN/getWebhookInfo | jq
```

**Check For:**
- `url` - Correct endpoint
- `has_custom_certificate` - Should be false (using public CA)
- `pending_update_count` - Should be 0 or low
- `last_error_message` - Should be empty

---

### Removing Webhook (Development)

```bash
curl -X POST https://api.telegram.org/bot$BOT_TOKEN/deleteWebhook
```

**Use Case:** Switching to polling for local development

---

## Development & Testing

**For local testing setup:** See `docs/DEVELOPMENT.md`
**For deployment:** See `DEPLOYMENT.md`

---

## Code Verification Log

**Last Verified:** 2026-02-28

### Files Checked
- ✅ `TelegramAPI.php` - All API methods and limits
- ✅ `uranus_game_bot.php` - Message handling patterns
- ✅ `config.php` - Bot token, webhook secret

### Verified
- ✅ callback_data limit enforcement (64 bytes)
- ✅ Photo caption limit enforcement (1024 chars)
- ✅ Parse mode setting (HTML everywhere)
- ✅ answerCallbackQuery usage (all callbacks)
- ✅ Owner stamping pattern
- ✅ Retry logic (3 attempts, exponential backoff)

---

**For architecture:** See `docs/ARCHITECTURE.md`
**For codebase structure:** See `docs/CODEBASE_STRUCTURE.md`
**For session data:** See `docs/SESSION_STATE.md`

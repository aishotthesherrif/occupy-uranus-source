# Multiplayer Bug Analysis

**Reported Issue:** "When one person started their game, the other in-progress game disappeared and when that player did not proceed we were stuck"

---

## Hypothesis

The bug is likely **NOT** a technical issue with message deletion or session management, but rather a **UX confusion** caused by:

1. **Both players' messages look identical** - Same format, same buttons, only difference is the "GAMER:" header
2. **Messages appear in chronological order** - New player's message appears below, pushing old message up
3. **Both messages have owner-stamped buttons** - Each user can only click their own buttons
4. **If one player stops responding**, their message remains in the chat but becomes "stale"

---

## Test Scenario

### Setup:
- Group chat: -1002860538306
- User A (ID: 123456)
- User B (ID: 789012)

### Expected Flow:

**User A starts game:**
```
[10:00] 👤 GAMER: Alice

🚀 OCCUPY URANUS 🪐
Welcome to the journey...

[Start Game] [Help]
```
Message tracking: `message_ids['123456_-1002860538306'] = msgA`

**User B starts game:**
```
[10:01] 👤 GAMER: Bob

🚀 OCCUPY URANUS 🪐
Welcome to the journey...

[Start Game] [Help]
```
Message tracking: `message_ids['789012_-1002860538306'] = msgB`

**Both messages exist in the chat.** They look almost identical except for the name in the header.

### Problem Cases:

#### Case 1: User Can't Tell Which Message Is Theirs
- User A clicks button on User B's message
- Bot responds: "This journey belongs to another player. Start your own with /game."
- User A is confused: "But I already started a game!"

#### Case 2: Stale Messages Create Visual Clutter
- User A clicks "Easy" difficulty → Message updates to show shop
- User B clicks "Hard" difficulty → Message updates to show shop
- Now there are TWO shop messages in the group
- User A clicks "Continue" → Their message updates
- User B doesn't click anything → Their message stays as "shop" view
- User A's game progresses → Many messages accumulate
- User B's stale "shop" message is still visible, causing confusion

#### Case 3: Message Deletion Race Condition
**Possible Issue:**
When User B starts their game:
1. Line 1555 in `sendMessage()`: `deleteLastMessage(userId, targetChatId, threadId)`
2. This tries to delete User B's previous message
3. But there IS no previous message for User B
4. **However**, if there's a bug in the message key generation or a race condition in file I/O...
5. It might accidentally delete User A's message?

---

## Code Analysis

### Message Key Generation
```php
private function getMessageKey(int $userId, $chatId, ?int $threadId = null): string {
    return $threadId ? "{$userId}_{$chatId}_{$threadId}" : "{$userId}_{$chatId}";
}
```

**Keys for our scenario:**
- User A: `123456_-1002860538306`
- User B: `789012_-1002860538306`

These are UNIQUE per user, so there should be no collision.

### Message Deletion Flow

**When button is clicked (line 612):**
```php
$this->telegram->deleteMessage($chatId, $messageId);
```
This deletes the SPECIFIC message that contains the clicked button.

**When new message is sent (line 1555):**
```php
$this->deleteLastMessage($userId, $targetChatId, $threadId);
```
This deletes the LAST message sent by the bot for THIS userId in THIS chat.

**These operations should be independent per user.**

---

## Actual Bug: File I/O Race Condition?

### The Issue

`message_ids.json` is a shared file accessed by multiple webhook requests:

```php
private function loadMessageIds(): array {
    if (!file_exists(MESSAGE_IDS_FILE)) {
        return [];
    }
    $content = @file_get_contents(MESSAGE_IDS_FILE);
    return $content ? json_decode($content, true) : [];
}

private function saveMessageIds(array $ids): void {
    $dir = dirname(MESSAGE_IDS_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    file_put_contents(MESSAGE_IDS_FILE, json_encode($ids, JSON_PRETTY_PRINT), LOCK_EX);
}
```

### Race Condition Scenario:

**Time T0:** User A clicks button
- Webhook 1 reads `message_ids.json`: `{userA: msgA, userB: msgB}`
- Webhook 1 deletes userA's entry: `{userB: msgB}`

**Time T1:** User B clicks button (while Webhook 1 is still processing)
- Webhook 2 reads `message_ids.json`: `{userA: msgA, userB: msgB}` (OLD DATA, before Webhook 1 wrote)
- Webhook 2 deletes userB's entry: `{userA: msgA}`

**Time T2:** Webhook 1 writes to file: `{userB: msgB}`
**Time T3:** Webhook 2 writes to file: `{userA: msgA}` (OVERWRITES Webhook 1's write!)

**Result:** One user's message tracking is lost!

### Why LOCK_EX Doesn't Help Here

`LOCK_EX` only locks during the WRITE operation. The READ operation is NOT locked, so:
1. Two webhooks can READ the same old data simultaneously
2. Both modify their in-memory copy
3. Both WRITE back sequentially (with lock)
4. Second write OVERWRITES first write

This is a **lost update problem**.

---

## Evidence This Is The Bug

From user report: "when one person started their game the other in progress game disappeared"

- User A starts game → msgA tracked
- User B starts game → reads old message_ids, processes, writes back
- **Accidentally overwrites User A's tracking**
- User A's message is now "orphaned" (not tracked)
- Next time User A acts, the bot can't delete their old message
- Multiple messages accumulate → confusion
- OR: User A's message gets deleted when User B acts (if there's another bug)

---

## The Fix

### Option 1: File Locking on Read+Write (Atomic Read-Modify-Write)

```php
private function loadMessageIds(): array {
    if (!file_exists(MESSAGE_IDS_FILE)) {
        return [];
    }

    $fp = fopen(MESSAGE_IDS_FILE, 'r');
    if (!$fp) {
        return [];
    }

    flock($fp, LOCK_SH); // Shared lock for reading
    $content = fread($fp, filesize(MESSAGE_IDS_FILE));
    flock($fp, LOCK_UN);
    fclose($fp);

    return $content ? json_decode($content, true) : [];
}

private function saveMessageIds(array $ids): void {
    $dir = dirname(MESSAGE_IDS_FILE);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    file_put_contents(MESSAGE_IDS_FILE, json_encode($ids, JSON_PRETTY_PRINT), LOCK_EX);
}
```

**Problem:** This still doesn't solve the race condition! The lock is released between read and write.

### Option 2: Read-Modify-Write Under Single Lock

```php
private function updateMessageIds(callable $modifier): void {
    $fp = fopen(MESSAGE_IDS_FILE, 'c+'); // Open for read/write, create if needed
    if (!$fp) {
        return;
    }

    flock($fp, LOCK_EX); // Exclusive lock for entire operation

    // Read
    $size = filesize(MESSAGE_IDS_FILE);
    $content = $size > 0 ? fread($fp, $size) : '';
    $ids = $content ? json_decode($content, true) : [];

    // Modify
    $ids = $modifier($ids);

    // Write
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($ids, JSON_PRETTY_PRINT));

    flock($fp, LOCK_UN);
    fclose($fp);
}
```

**Usage:**
```php
// Store message ID
$this->updateMessageIds(function($ids) use ($key, $messageId) {
    $ids[$key] = ['id' => $messageId, 'ts' => time()];
    return $ids;
});

// Delete message ID
$this->updateMessageIds(function($ids) use ($key) {
    unset($ids[$key]);
    return $ids;
});
```

### Option 3: Per-User Message ID Files (Simplest)

Instead of one shared `message_ids.json`, use `message_ids/{userId}.json`:

```php
private function getMessageIdsFile(int $userId): string {
    return DATA_DIR . '/message_ids/' . $userId . '.json';
}

private function loadUserMessageIds(int $userId): array {
    $file = $this->getMessageIdsFile($userId);
    if (!file_exists($file)) {
        return [];
    }
    $content = @file_get_contents($file);
    return $content ? json_decode($content, true) : [];
}

private function saveUserMessageIds(int $userId, array $ids): void {
    $file = $this->getMessageIdsFile($userId);
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    file_put_contents($file, json_encode($ids, JSON_PRETTY_PRINT), LOCK_EX);
}
```

**Advantages:**
- No race condition between different users
- Simpler code (no complex locking)
- Better scalability (no single file bottleneck)
- Auto-cleanup easier (delete user file on abandon)

**Disadvantages:**
- More files to manage
- Slightly more disk I/O

---

## Recommended Solution

**Use Option 3: Per-User Message ID Files**

This completely eliminates the race condition between users and is the simplest to implement correctly.

---

## Testing Plan

1. Create two test accounts
2. Both join same group
3. Both send `/game` simultaneously (within 1 second)
4. Both select difficulty simultaneously
5. Verify BOTH games persist and function independently
6. Check `message_ids/` directory for correct per-user files

---

## Additional UX Improvements

Even with the race condition fixed, there are UX issues:

### Issue: Can't Tell Which Message Is Yours

**Solution:** Make the header more prominent:
```
╔══════════════════════════════╗
║  👤 YOUR GAME: Alice         ║
╠══════════════════════════════╣
║ 🚀 OCCUPY URANUS 🪐          ║
...
```

### Issue: Stale Messages Clutter the Chat

**Solution:** Add timestamps to messages:
```
👤 GAMER: Alice
🕐 Updated: 10:45 AM

🚀 OCCUPY URANUS 🪐
...
```

### Issue: Wrong Button Click Shows Confusing Error

**Current:** "This journey belongs to another player"
**Better:** "This is Alice's journey. Your game is above/below."

---

## Priority

**HIGH** - This is a game-breaking bug for group play, which seems to be a core use case.

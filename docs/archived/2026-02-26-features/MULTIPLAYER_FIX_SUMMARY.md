# Multiplayer Race Condition Fix

**Date:** 2026-02-26
**Issue:** Two players in the same group couldn't play simultaneously - one game would "disappear"
**Status:** ✅ FIXED

---

## The Bug

### What Was Happening:

When two users played in the same Telegram group:
1. User A starts game → Bot tracks message in shared `message_ids.json`
2. User B starts game (while User A is playing) → Bot reads `message_ids.json`
3. **RACE CONDITION**: Both webhooks read/modify/write the same file simultaneously
4. Second write overwrites first write → User A's message tracking is LOST
5. User A's message becomes "orphaned" - can't be deleted properly
6. UI becomes confusing with stale messages

### Root Cause:

**Single shared file** (`data/message_ids.json`) was accessed by multiple concurrent webhook requests without proper locking:

```php
// OLD CODE (BUGGY):
private function storeLastMessageId(...) {
    $ids = $this->loadMessageIds();           // Read shared file
    $ids[$key] = $messageId;                  // Modify in memory
    $this->saveMessageIds($ids);              // Write back (overwrites!)
}
```

**Problem:** Two users' webhooks can execute simultaneously:
1. Webhook A reads file: `{userA: msg1}`
2. Webhook B reads file: `{userA: msg1}` (same old data!)
3. Webhook A writes: `{userA: msg2}`
4. Webhook B writes: `{userB: msg3}` ← **OVERWRITES User A's update!**
5. Result: `{userB: msg3}` (User A's tracking LOST)

---

## The Fix

### Per-User Message ID Files

Instead of one shared `message_ids.json`, use **one file per user**:

```
data/message_ids/
  ├── 123456.json  (User A's messages)
  ├── 789012.json  (User B's messages)
  └── 345678.json  (User C's messages)
```

**Why this works:**
- Different users modify different files → NO race condition
- Simple to implement (no complex locking needed)
- Better scalability (no single file bottleneck)
- Easier cleanup (delete user file when they abandon game)

### Code Changes:

**New functions:**
```php
private function getUserMessageIdsFile(int $userId): string {
    return MESSAGE_IDS_DIR . '/' . $userId . '.json';
}

private function loadUserMessageIds(int $userId): array {
    // Load from user's dedicated file
}

private function saveUserMessageIds(int $userId, array $ids): void {
    // Save to user's dedicated file with LOCK_EX
}
```

**Updated key format:**
- OLD: `userId_chatId` or `userId_chatId_threadId`
- NEW: `chatId` or `chatId_threadId` (userId is now the filename)

**Example:**

User 123456 in group -1002860538306:
- OLD: Key in shared file = `123456_-1002860538306`
- NEW: File `123456.json` contains key `-1002860538306`

---

## Files Modified

1. **config.php**
   - Added `MESSAGE_IDS_DIR` constant

2. **uranus_game_bot.php**
   - Added `getUserMessageIdsFile()`, `loadUserMessageIds()`, `saveUserMessageIds()`
   - Updated `getMessageKey()` to not include userId (it's now the filename)
   - Updated `getLastMessageId()`, `storeLastMessageId()`, `deleteLastMessage()`
   - Updated `pruneMessageIds()` to delete old files instead of pruning entries

---

## Testing

### Test Script:
`test_multiplayer_fix.php` - Simulates two users storing message IDs simultaneously

### Manual Testing:
1. Two users in same group
2. Both send `/game` at the same time
3. Both select difficulty
4. Both click "Continue"
5. **Verify:** Both games persist and work independently

### Test Results:
```
✅ User A's message ID preserved: 1001
✅ User B's message ID preserved: 1002
✅ User B's message ID still correct after User A's update
```

---

## Migration

### Automatic Migration (Not Required):

The fix is **immediately compatible**:
- Old shared `message_ids.json` can coexist (not used anymore)
- New per-user files are created as needed
- No migration script required
- Old file can be deleted after confirming everything works

### Optional Cleanup:

After deploying and confirming everything works:
```bash
# Backup old file (optional)
mv data/message_ids.json data/message_ids.json.backup

# Verify new directory is being used
ls -la data/message_ids/

# Should see files like: 123456.json, 789012.json, etc.
```

---

## Deploy Instructions

### 1. Upload Modified Files:
```bash
config.php
uranus_game_bot.php
```

### 2. Create New Directory:
```bash
mkdir -p data/message_ids
chmod 755 data/message_ids
```

### 3. Test:
- Send `/game` from two different users in same group
- Both games should work independently
- Check `data/message_ids/` for per-user files

### 4. Cleanup (Optional):
```bash
# After confirming everything works
rm data/message_ids.json  # Remove old shared file
```

---

## Performance Impact

### Before:
- ⚠️ Race condition with concurrent users
- ⚠️ Single file bottleneck (all users wait for same file)
- ⚠️ Large JSON file with all users' data

### After:
- ✅ No race condition (different users = different files)
- ✅ Parallel writes (users don't block each other)
- ✅ Small JSON files (one user's data each)
- ✅ Faster I/O (smaller files)

**Net result:** Faster AND more reliable!

---

## Edge Cases Handled

### 1. User in Multiple Groups:
User 123456 plays in two groups:
- File `123456.json` contains:
  ```json
  {
    "-1001": {"id": 5001, "ts": 1234567890},
    "-1002": {"id": 5002, "ts": 1234567891}
  }
  ```

### 2. User in Group + Private Chat:
User 123456 plays in group AND private DM:
- File `123456.json` contains:
  ```json
  {
    "-1001": {"id": 5001, "ts": 1234567890},
    "123456": {"id": 5003, "ts": 1234567892}
  }
  ```

### 3. Cleanup (Pruning):
Old shared file pruned entries based on timestamp.
New per-user files: Delete entire file if not modified in 24 hours.

---

## Related Issues

### Issue: "Can't Tell Which Message Is Mine"

Even with the race condition fixed, there's a UX issue:
- Both players see identical-looking messages in the group
- Hard to tell which game is yours

**Potential future enhancement:**
```
╔═══════════════════════════════╗
║ 🎮 YOUR GAME (Alice)          ║
╠═══════════════════════════════╣
║ 🚀 OCCUPY URANUS              ║
...
```

This is a separate UX issue, not related to the race condition.

---

## Verification

### How to Confirm It's Working:

1. **Check for per-user files:**
   ```bash
   ls -la data/message_ids/
   # Should see: 123456.json, 789012.json, etc.
   ```

2. **Simulate race condition:**
   ```bash
   # Two users click buttons within 1 second
   # Both games should continue working
   ```

3. **Check logs:**
   ```bash
   grep "Pruned" data/bot.log
   # Should see: "Pruned N stale message ID files"
   ```

---

## Summary

✅ **Bug Fixed:** Race condition eliminated
✅ **No Migration Required:** Works immediately
✅ **Performance:** Improved (parallel I/O)
✅ **Scalability:** Better (no single file bottleneck)
✅ **Tested:** Test script passes

**Deploy with confidence!** 🚀

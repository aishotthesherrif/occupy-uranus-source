# Occupy Uranus - Telegram Bot Deployment Guide

## 📁 Directory Structure

Create this exact folder structure on your server:

```
/uranus-game-bot/
├── uranus_game_bot.php          # Main bot file (webhook handler)
├── config.php                    # Configuration file
├── .htaccess                     # Apache configuration (optional)
├── classes/
│   ├── TelegramAPI.php          # Telegram API wrapper
│   ├── GameEngine.php           # Core game logic
│   └── GameSessionManager.php   # State management
├── data/
│   ├── events.json              # Game events
│   ├── waypoints.json           # Solar system waypoints
│   ├── game_state.json          # (auto-created) Active sessions
│   ├── game_state.backup.json  # (auto-created) Backup
│   └── leaderboards.json        # (auto-created) High scores
└── logs/
    └── bot.log                   # (auto-created) Application log
```

---

## 🚀 Step-by-Step Deployment

### Step 1: Create Bot with BotFather

1. Open Telegram and search for `@BotFather`
2. Send `/newbot`
3. Choose a name: `Occupy Uranus`
4. Choose a username: `OccupyUranusBot` (or your choice)
5. **SAVE THE TOKEN** - you'll need it!

Example token: `1234567890:ABCdefGHIjklMNOpqrsTUVwxyz`

### Step 2: Upload Files

1. Upload all PHP files to your web server
2. Ensure the directory is accessible via HTTPS (required for Telegram webhooks)
3. Set proper permissions:
   ```bash
   chmod 755 /uranus-game-bot/
   chmod 644 /uranus-game-bot/*.php
   chmod 755 /uranus-game-bot/classes/
   chmod 644 /uranus-game-bot/classes/*.php
   chmod 755 /uranus-game-bot/data/
   chmod 666 /uranus-game-bot/data/*.json
   chmod 755 /uranus-game-bot/logs/
   chmod 666 /uranus-game-bot/logs/bot.log
   ```

### Step 3: Configure Bot

1. Open `config.php`
2. Replace `YOUR_BOT_TOKEN_HERE` with your actual bot token
3. Add your Telegram user ID to `ADMIN_USER_IDS` (get it from @userinfobot)
4. Save the file

**Example:**
```php
define('BOT_TOKEN', '1234567890:ABCdefGHIjklMNOpqrsTUVwxyz');
define('ADMIN_USER_IDS', [123456789]); // Your user ID
```

### Step 4: Set Webhook

**First, verify your bot token.** Open this URL in your browser (replace with your real token):

```
https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getMe
```

You should see something like `{"ok":true,"result":{"id":...,"is_bot":true,"first_name":"..."}}`. If you get `{"ok":false,"error_code":404,"description":"Not Found"}` here, your token is wrong—double-check it in BotFather (no spaces, full string) or use BotFather’s menu to see/regenerate the token.

Then set your webhook with this URL format:

```
https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook?url=https://yourdomain.com/uranus-game-bot/uranus_game_bot.php
```

**Example:**
```
https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setWebhook?url=https://ethlovesuranus.space/OccupyUranusGame_bot/uranus_game_bot.php
```

(Use your real token; the example in the guide is a placeholder. Never share your token publicly.)

Open the setWebhook URL in your browser. You should see:
```json
{"ok":true,"result":true,"description":"Webhook was set"}
```

### Step 5: Set Bot Commands

This makes commands appear in the Telegram menu. Run this curl command (or use a tool like Postman):

```bash
curl -X POST "https://api.telegram.org/bot<YOUR_BOT_TOKEN>/setMyCommands" \
  -H "Content-Type: application/json" \
  -d '{
    "commands": [
      {"command": "start", "description": "🎮 Start or return to main menu"},
      {"command": "newgame", "description": "🚀 Begin a new journey to Uranus"},
      {"command": "continue", "description": "▶️ Advance one day"},
      {"command": "status", "description": "📊 View current mission status"},
      {"command": "map", "description": "🗺️ Display solar system map"},
      {"command": "shop", "description": "🛒 Visit space station shop"},
      {"command": "rest", "description": "😴 Rest to restore chad morale"},
      {"command": "help", "description": "❓ Show help and instructions"}
    ]
  }'
```

### Step 6: Test the Bot

1. Open Telegram and search for your bot
2. Send `/start`
3. You should see the welcome message with buttons!

---

## 🧪 Testing Checklist

Test these features to ensure everything works:

### Basic Commands
- [ ] `/start` - Shows welcome message with menu buttons
- [ ] `/help` - Shows help text
- [ ] `/newgame` - Shows difficulty selection
- [ ] Click difficulty button - Creates game session
- [ ] `/shop` - Shows shop interface
- [ ] Click shop buttons - Purchases items
- [ ] `/status` - Shows full status report
- [ ] `/continue` - Advances one day

### Game Flow
- [ ] Start new game (easy mode)
- [ ] Buy some supplies (fuel + food)
- [ ] Launch ship
- [ ] Continue for several days
- [ ] Encounter an event
- [ ] Make event choice - resources change correctly
- [ ] Continue until victory or defeat

### Edge Cases
- [ ] Try to `/continue` without active game - Shows error
- [ ] Try to start game when already have one - Shows warning
- [ ] Run out of fuel - Game over message
- [ ] Run out of food - Game over message
- [ ] Reach Uranus - Victory message
- [ ] `/abandon` - Deletes session

### Map & Advanced
- [ ] `/map` - Shows ASCII solar system map
- [ ] Check waypoint notifications appear correctly
- [ ] Space station waypoints restore morale
- [ ] `/rest` - Increases morale, consumes food

---

## 🔍 Troubleshooting

### Bot Doesn't Respond

**Check webhook status:**
```
https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getWebhookInfo
```

Look for:
- `url` should be your webhook URL
- `pending_update_count` should be 0
- `last_error_message` shows any errors

**Common issues:**
1. URL must be HTTPS (not HTTP)
2. SSL certificate must be valid
3. PHP must be 7.4 or higher
4. File permissions must allow writing to data/ and logs/

### setWebhook returns 404 "Not Found"

Telegram often returns **404** when the **bot token is invalid**, not when the webhook URL is wrong (they don’t distinguish for security).

**Do this:**
1. **Check the token** – Open `https://api.telegram.org/bot<YOUR_BOT_TOKEN>/getMe` in your browser. If you get 404 there too, the token is wrong.
2. **Fix the token** – In Telegram, open @BotFather → select your bot → "API Token". Copy the token again (no spaces, no missing characters). If you’re unsure, use "Revoke current token" and paste the new token into `config.php` and your setWebhook URL.
3. **URL format** – The setWebhook URL must be exactly: `https://api.telegram.org/bot` + token + `/setWebhook?url=` + your HTTPS webhook URL. The word `bot` before the token is required.
4. **Encode the webhook URL** – If your webhook path has special characters, URL-encode the `url` parameter (e.g. use `%2F` for `/` in the path if needed). Most of the time a simple path like `https://yourdomain.com/uranus-game-bot/uranus_game_bot.php` is fine without encoding.

### Bot Responds Slowly

- Check `logs/bot.log` for errors
- Ensure `data/` directory is writable
- Check server PHP memory limits

### Events Not Working

1. Verify `data/events.json` exists and is valid JSON
2. Check file permissions (must be readable)
3. Look in `logs/bot.log` for JSON parsing errors

### Session Lost

1. Check `data/game_state.json` exists
2. Verify file permissions (must be writable)
3. Look for `game_state.backup.json` to restore

---

## 🛠️ Optional: .htaccess Configuration

Create `.htaccess` in the root directory for better security:

```apache
# Protect config file
<Files "config.php">
    Order Allow,Deny
    Deny from all
</Files>

# Protect data files
<FilesMatch "\.(json|log)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Allow webhook access
<Files "uranus_game_bot.php">
    Order Allow,Deny
    Allow from all
</Files>

# Prevent directory listing
Options -Indexes

# PHP settings
php_value upload_max_filesize 2M
php_value post_max_size 2M
php_value memory_limit 128M
```

---

## 📊 Monitoring

### Check Active Sessions

Visit your bot URL in a browser:
```
https://yourdomain.com/uranus-game-bot/uranus_game_bot.php
```

You'll see a status page showing if the bot is configured.

### Check Logs

Monitor `logs/bot.log` for errors:
```bash
tail -f logs/bot.log
```

### Cleanup Old Sessions

Sessions older than 7 days are automatically cleaned up. To manually trigger cleanup, the `GameSessionManager` class has a `cleanupOldSessions()` method.

---

## 🎮 Playing the Game

### For Players

1. Start the bot: `/start`
2. Begin a new journey: Click "🚀 New Journey" or `/newgame`
3. Select difficulty: Easy, Normal, or Hard
4. Shop for supplies: Use `/shop` or click buttons
5. Launch your ship: Click "🚀 Launch Ship"
6. Play each day: Use `/continue` to advance
7. Make choices during events: Click the button for your decision
8. Check status anytime: `/status` or `/map`
9. Need a break? `/rest` to restore morale

### Tips for Success

- **Stock up!** Get at least 200 fuel and 150 food before launching
- **Watch resources** - Check `/status` regularly
- **Space stations** - They restore morale when you reach them
- **Risky choices** - Sometimes pay off big, sometimes backfire
- **Difficulty matters** - Hard mode is brutal!

---

## 🔐 Security Notes

1. **Never commit** your bot token to version control
2. **Protect** config.php from web access
3. **Use HTTPS** - Telegram requires it
4. **Set proper permissions** - Don't make files world-writable
5. **Monitor logs** - Watch for suspicious activity
6. **Rate limiting** - Built-in to prevent spam

---

## 🆘 Getting Help

### Check These First

1. `logs/bot.log` - Application logs
2. Telegram webhook info - Shows errors
3. Browser console - For web page errors
4. PHP error logs - Server-level issues

### Common Error Messages

**"Empty webhook content"**
- Telegram isn't reaching your server
- Check URL and HTTPS

**"JSON decode error"**
- Corrupted data file
- Check `data/*.json` files are valid

**"Session expired"**
- Session cleaned up due to age
- Player needs to start new game

**"Failed to write session"**
- File permission issue
- Check `data/` directory permissions

---

## 📈 What's Next?

Once the bot is running, you can:

1. **Add more events** - Edit `data/events.json`
2. **Adjust difficulty** - Modify values in `config.php`
3. **Add features** - Extend classes in `classes/`
4. **Track analytics** - Monitor player behavior in logs
5. **Build leaderboards** - Complete the leaderboard system
6. **Add achievements** - Create achievement tracking
7. **Multi-language** - Add translation support

---

## 🎉 Launch Checklist

Before announcing your bot:

- [ ] All files uploaded
- [ ] Bot token configured
- [ ] Webhook set and working
- [ ] Commands configured in BotFather
- [ ] Tested full game flow
- [ ] Logs are being written
- [ ] Sessions save correctly
- [ ] Events trigger properly
- [ ] Victory/defeat working
- [ ] Map displays correctly
- [ ] Shop purchases work
- [ ] Ready to handle traffic!

---

**You're ready to Occupy Uranus! 🚀🪐**
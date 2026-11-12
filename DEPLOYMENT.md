# Deployment Guide

**Last Updated:** 2026-03-02

This guide covers deploying code updates to your production server.

---

## ⚠️ First Time Setup?

**If this is your first deployment**, use [`PRODUCTION_SETUP.md`](PRODUCTION_SETUP.md) instead.

This guide is for deploying updates to an **already-configured** production bot.

---

## Quick Start

```bash
# 1. Configure deployment (first time only)
cp deploy.config.example deploy.config
nano deploy.config  # Add your server details

# 2. Preview what will deploy
./deploy.sh --dry-run

# 3. Deploy
./deploy.sh
```

---

## Configuration

### deploy.config

Create from example:
```bash
cp deploy.config.example deploy.config
```

Edit with your server details:
```bash
DEPLOY_HOST="ethlovesuranus.space"
DEPLOY_USER="your_username"
DEPLOY_PATH="/home/your_username/public_html/OccupyUranusGame_bot"

# Optional
DEPLOY_PORT="22"
DEPLOY_SSH_KEY="~/.ssh/id_rsa"
```

**Note:** `deploy.config` is gitignored (keeps credentials private).

---

## How It Works

### What Gets Deployed

The script uses **rsync** to sync your **local folder** to the server:

**✅ Included:**
- `*.php` files (bot code)
- `game/`, `bot/`, `api/game/` and `mini-app/` (deploy these together: the bot and the Mini-App share `game/`)
- `data/` (events, waypoints, chad_personalities, aesthetics)
- `media/` (all images in current folder)

**❌ Excluded:**
- `logs/` (runtime data stays on server)
- `docs/` (documentation)
- `test_framework/` and `tools/` (test files)
- `deploy.sh`, `deploy.config` (deployment tools)
- `CLAUDE.md`, `README.md`, etc. (project docs)
- `data/backups/`, `data/game_state*.json`, `data/leaderboards.json` (preserved on server)

> **Layout change (2026-11-11):** the old `classes/` and `api/mini-app/` folders no longer exist. Because the deploy uses `rsync --delete`, the first deploy of this layout removes them from the server. Deploy the whole tree at once: the bot, the Mini-App and the API must match. The bot no longer sends Mini-App buttons; the Mini-App is opened from the BotFather menu button (see `docs/mini-app/TESTING_GUIDE.md`). Players keep their journeys; their chat routing and picture set reset to defaults.

### Key Behavior

**Deploys folder contents, not git:**
- Modified files deploy (even if uncommitted)
- New files deploy (even if untracked)
- Deleted files removed from server (`--delete`)

This means:
- ✅ Your media changes deploy automatically
- ✅ Local modifications deploy immediately
- ⚠️ Make sure unwanted changes aren't in your folders

---

## Deployment Process

### 1. Dry Run (Recommended)

Preview what will change:
```bash
./deploy.sh --dry-run
```

This shows:
- Files that will be sent
- Files that will be deleted on server
- Files that will be updated

### 2. Deploy

```bash
./deploy.sh
```

Output:
```
Deploying to: user@host:/path

✅ Deployed successfully

Test: Send /game to bot in Telegram
Logs: ssh user@host tail -f /path/logs/bot.log
```

---

## Post-Deployment

### 1. Test the Bot

Send `/game` to your bot in Telegram:
- Should respond immediately
- Try starting a game
- Test a few interactions

### 2. Check Logs

```bash
ssh user@host tail -f /path/to/logs/bot.log
```

Look for:
- ✅ No ERROR lines
- ✅ Normal INFO/DEBUG logs
- ❌ Parse errors or warnings

### 3. Verify Webhook (Optional)

```bash
TOKEN=$(php -r "require 'config.php'; echo BOT_TOKEN;")
curl "https://api.telegram.org/bot$TOKEN/getWebhookInfo" | jq
```

Should show:
- Your webhook URL
- `pending_update_count` = 0 or low
- No error messages

---

## Troubleshooting

### Permission Denied

**Problem:** Can't SSH to server

**Solutions:**
```bash
# Test SSH manually
ssh user@host

# Use SSH key
ssh-copy-id user@host

# Check deploy.config settings
```

### Bot Not Responding

**Check logs first:**
```bash
ssh user@host tail -50 /path/to/logs/bot.log | grep ERROR
```

**Common causes:**

1. **PHP syntax error**
   ```bash
   # Check locally before deploying
   php -l uranus_game_bot.php
   php -l config.php
   ```

2. **File permissions**
   ```bash
   ssh user@host
   chmod 755 /path/to/bot
   chmod 755 /path/to/bot/data
   chmod 755 /path/to/bot/logs
   ```

3. **Webhook broken**
   - See [`PRODUCTION_SETUP.md`](PRODUCTION_SETUP.md) to re-register webhook

### Files Not Updating

**Problem:** Changes don't appear on server

**Check:**
```bash
# 1. Did deployment complete?
./deploy.sh --dry-run  # Check what would deploy

# 2. Are files excluded?
# Check deploy.sh exclusions list

# 3. File permissions on server
ssh user@host ls -la /path/to/bot/file.php
```

### Deployment Too Slow

**Problem:** Takes too long to deploy

**Causes:**
- Large media files
- Slow network connection
- Many files changed

**Solutions:**
```bash
# Use --dry-run to see what's being transferred
./deploy.sh --dry-run

# Check media file sizes
du -sh media/

# Consider removing old/unused media files
```

---

## Advanced Usage

### Custom SSH Port

In `deploy.config`:
```bash
DEPLOY_PORT="2222"
```

### Custom SSH Key

In `deploy.config`:
```bash
DEPLOY_SSH_KEY="~/.ssh/custom_key"
```

### Selective Deployment

To deploy only specific files, use rsync directly:
```bash
rsync -avz -e "ssh -p $PORT" \
  uranus_game_bot.php \
  user@host:/path/to/bot/
```

---

## Safety Notes

**Before deploying:**
- ✅ Test locally: `php local_game_tester.php`
- ✅ Check syntax: `php -l uranus_game_bot.php`
- ✅ Run dry-run: `./deploy.sh --dry-run`

**After deploying:**
- ✅ Test bot immediately
- ✅ Check logs for errors
- ✅ Keep terminal open for quick rollback if needed

**Rollback:**
If deployment breaks something, deploy previous version:
```bash
git checkout HEAD~1 uranus_game_bot.php
./deploy.sh
git checkout main  # Restore to current
```

---

## Deployment Checklist

- [ ] Run `./deploy.sh --dry-run` to preview
- [ ] Check no unwanted local changes exist
- [ ] Deploy: `./deploy.sh`
- [ ] Test bot: Send `/game` in Telegram
- [ ] Check logs: `tail -f logs/bot.log` on server
- [ ] Verify no errors in first few interactions

---

## Related Documentation

- **First deployment:** [`PRODUCTION_SETUP.md`](PRODUCTION_SETUP.md)
- **Local testing:** [`docs/DEVELOPMENT.md`](docs/DEVELOPMENT.md)
- **Architecture:** [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)

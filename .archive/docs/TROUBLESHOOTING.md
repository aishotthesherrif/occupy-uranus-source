# Troubleshooting Guide

This guide helps you diagnose and fix common issues with the Occupy Uranus Telegram Bot.

## Common Issues

### Bot Not Responding

**Symptoms:**
- Bot doesn't respond to commands
- Webhook errors in logs
- Commands timeout

**Diagnosis:**
```bash
# Check webhook status
curl "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"

# Check bot logs
tail -f logs/bot.log

# Test webhook endpoint
curl -X POST "https://yourdomain.com/uranus_game_bot.php" \
     -H "Content-Type: application/json" \
     -d '{"update_id":1,"message":{"message_id":1,"from":{"id":123,"is_bot":false,"first_name":"Test"},"chat":{"id":123,"type":"private"},"date":1234567890,"text":"/start"}}'
```

**Solutions:**
1. **Verify webhook URL**: Ensure it points to your bot file
2. **Check SSL certificate**: Webhook requires HTTPS
3. **Verify bot token**: Check config.php
4. **Check server logs**: Look for PHP errors
5. **Test file permissions**: Ensure bot file is executable

### Images Not Showing

**Symptoms:**
- Messages appear without images
- "Failed to load image" errors
- Images show as broken links

**Diagnosis:**
```bash
# Test image accessibility
curl -I https://yourdomain.com/media/Title-01.png

# Check image cache
cat data/image_cache.json

# Check BOT_BASE_URL
grep BOT_BASE_URL config.php
```

**Solutions:**
1. **Configure BOT_BASE_URL**: Set to your actual domain
2. **Check media directory**: Ensure files exist and are accessible
3. **Verify file permissions**: Media files must be readable
4. **Test image URLs**: Manually verify in browser
5. **Check cache file**: Ensure image_cache.json is writable

### Game State Issues

**Symptoms:**
- Game doesn't advance past certain days
- Duplicate messages appearing
- Session data corruption

**Diagnosis:**
```bash
# Check game state file
cat data/game_state.json

# Check session data
grep "session" logs/telemetry.jsonl

# Check for file locks
ls -la data/*.lock
```

**Solutions:**
1. **Reset game state**: Use `/reset` admin command
2. **Check file permissions**: Ensure data directory is writable
3. **Clear locks**: Remove any .lock files
4. **Verify JSON format**: Check for syntax errors
5. **Check session manager**: Review session handling logic

### Event System Problems

**Symptoms:**
- Events not triggering
- Game stuck on same day
- Event choices not working

**Diagnosis:**
```bash
# Check event files
ls -la data/events.json data/enhanced_events.json

# Check telemetry for events
grep "event_occurred" logs/telemetry.jsonl

# Check event configuration
grep "event_frequency" config.php
```

**Solutions:**
1. **Verify event files**: Ensure events.json exists and is valid
2. **Check event frequency**: Adjust in difficulty settings
3. **Review event logic**: Check GameEngine.php event handling
4. **Test event triggers**: Use debug commands
5. **Check pending events**: Look for stuck pending_event states

### Performance Issues

**Symptoms:**
- Slow response times
- High memory usage
- Timeout errors

**Diagnosis:**
```bash
# Check PHP memory usage
php -i | grep memory_limit

# Check log file sizes
ls -lh logs/

# Monitor system resources
top -p $(pgrep php)
```

**Solutions:**
1. **Increase memory limit**: Set memory_limit in php.ini
2. **Enable OPcache**: For better performance
3. **Rotate logs**: Prevent large log files
4. **Optimize queries**: Review database/file operations
5. **Use file_id caching**: Ensure image caching is working

## Debug Commands

### Admin Commands

Use these commands to debug issues:

```
/debug - Show system debug information
/reset - Reset all game data
/status - Show current system status
/telemetry - Show recent telemetry data
```

### Manual Testing

Test specific functionality:

```bash
# Test image system
curl -X POST "https://api.telegram.org/bot<TOKEN>/sendPhoto" \
     -F "chat_id=123" \
     -F "photo=https://yourdomain.com/media/Title-01.png" \
     -F "caption=Test image"

# Test webhook
curl -X POST "https://yourdomain.com/uranus_game_bot.php" \
     -H "Content-Type: application/json" \
     -d '{"update_id":1,"message":{"message_id":1,"from":{"id":123},"chat":{"id":123},"text":"/start"}}'
```

## Log Analysis

### Bot Logs

Check `logs/bot.log` for errors:

```bash
# Find errors
grep ERROR logs/bot.log

# Find warnings
grep WARNING logs/bot.log

# Find specific user issues
grep "user_id:123456" logs/bot.log

# Check recent activity
tail -50 logs/bot.log
```

### Telemetry Logs

Analyze `logs/telemetry.jsonl` for game flow:

```bash
# Check day advancement
grep "day_advance" logs/telemetry.jsonl

# Check event occurrences
grep "event_occurred" logs/telemetry.jsonl

# Check session lifecycle
grep "session_created\|session_deleted" logs/telemetry.jsonl

# Find stuck sessions
grep "success.*false" logs/telemetry.jsonl
```

### System Logs

Check system-level issues:

```bash
# Check web server logs
tail -f /var/log/apache2/error.log
tail -f /var/log/nginx/error.log

# Check PHP logs
tail -f /var/log/php/error.log

# Check system resources
df -h
free -m
```

## Configuration Issues

### Bot Token

**Problem**: Invalid or expired bot token

**Solution**:
1. Get new token from @BotFather
2. Update config.php
3. Restart webhook

### Webhook URL

**Problem**: Webhook not set or incorrect

**Solution**:
```bash
# Set webhook
curl -X POST "https://api.telegram.org/bot<TOKEN>/setWebhook" \
     -H "Content-Type: application/json" \
     -d '{"url": "https://yourdomain.com/uranus_game_bot.php"}'

# Check webhook status
curl "https://api.telegram.org/bot<TOKEN>/getWebhookInfo"
```

### File Permissions

**Problem**: Files not writable

**Solution**:
```bash
# Set correct permissions
chmod 755 uranus_game_bot.php
chmod 755 config.php
chmod -R 755 data/
chmod -R 755 logs/
chmod -R 755 media/

# Set ownership
chown -R www-data:www-data .
```

## Data Corruption

### Game State Corruption

**Symptoms**: Invalid JSON, missing data

**Solution**:
1. Backup current data
2. Restore from backup
3. Reset specific user sessions
4. Recreate data files

### Session Corruption

**Symptoms**: Users stuck, invalid session data

**Solution**:
```bash
# Reset specific user
curl -X POST "https://yourdomain.com/uranus_game_bot.php" \
     -H "Content-Type: application/json" \
     -d '{"update_id":1,"message":{"from":{"id":USER_ID},"text":"/reset"}}'

# Clear all sessions
rm data/game_state.json
```

## Network Issues

### SSL Certificate

**Problem**: Webhook fails due to SSL issues

**Solution**:
1. Verify SSL certificate is valid
2. Check certificate chain
3. Test with SSL checker tools
4. Update certificate if expired

### Firewall

**Problem**: Requests blocked

**Solution**:
1. Check firewall rules
2. Allow HTTPS traffic
3. Check IP restrictions
4. Test connectivity

## Recovery Procedures

### Complete Reset

If all else fails:

```bash
# Backup current state
cp -r data/ data_backup/
cp -r logs/ logs_backup/

# Reset everything
rm data/game_state.json
rm data/image_cache.json
rm logs/bot.log
rm logs/telemetry.jsonl

# Restart services
systemctl restart apache2
systemctl restart php-fpm
```

### Selective Recovery

Recover specific components:

```bash
# Reset image cache
rm data/image_cache.json

# Reset specific user
# Edit data/game_state.json to remove user session

# Reset events
# Restore from backup or recreate events.json
```

## Prevention

### Regular Maintenance

1. **Monitor logs** daily
2. **Check disk space** regularly
3. **Update dependencies** monthly
4. **Backup data** weekly
5. **Test functionality** after changes

### Monitoring

Set up monitoring for:

- Webhook delivery success rate
- Response times
- Error rates
- Disk space usage
- Memory usage

### Backup Strategy

1. **Automated backups** of data directory
2. **Version control** for configuration
3. **Documentation** of changes
4. **Recovery procedures** documented

## Getting Help

If you can't resolve an issue:

1. **Check logs** thoroughly
2. **Document the problem** with steps to reproduce
3. **Include relevant log excerpts**
4. **Describe your environment** (PHP version, OS, etc.)
5. **Try the issue in a clean environment**

## Emergency Contacts

- **System Administrator**: For server issues
- **Developer**: For code issues
- **Telegram Support**: For bot API issues
- **Hosting Provider**: For infrastructure issues

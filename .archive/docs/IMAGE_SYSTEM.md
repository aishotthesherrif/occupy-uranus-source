# Image Selection System Documentation

## Overview

The Occupy Uranus bot features a sophisticated image selection system that provides contextual images based on game state, with automatic bandwidth optimization through Telegram file_id caching.

## Key Features

- **Contextual Image Selection**: Images match what's happening in the game
- **Bandwidth Optimization**: Uses Telegram file_id caching for 10-100x faster loading
- **Image Rotation**: Prevents repetition by rotating through image pools
- **Graceful Fallbacks**: Handles missing images and invalid URLs
- **Automatic Caching**: Builds file_id cache during normal gameplay

## Architecture

### Configuration Files

#### `data/image_config.json`
Defines which images to use for different game contexts:

```json
{
  "waypoints": {
    "Earth Orbit": "Waypoint-Earth_Launch.png",
    "Mars Orbit": "Waypoint-Mars-Flyby.png",
    "Asteroid Belt": ["Waypoint-Asteroid_Belt.png", "Waypoint-Asteroid_Belt2.png"],
    "Saturn Orbit": "Waypoint-Saturn_Rings.png",
    "Jupiter Slingshot": "Waypoint-Jupiter_Slingshot.png",
    "Moon": "Waypoint-Moon.png.jpg",
    "Ceres Station": "Waypoint-Ceres_Station.png.jpg",
    "Europa Outpost": "Waypoint-Europa_Outpost.png.jpg",
    "Titan Colony": "Waypoint-Titan_Colony.png.jpg"
  },
  "milestones": {
    "25": "milestone-25.png",
    "50": "milestone-50.png",
    "75": "milestone-75.png"
  },
  "space_views": ["Space-View-02.png", "Space-View-03.png", "Space-View-04.png"],
  "events": ["Event-01.png", "Event-02.png"],
  "status": ["Status-01.png", "Status-02.png"],
  "title": "Title-01.png",
  "continue": "Continue-01.png",
  "bulk": "BulkImage.png",
  "fallback": "Continue-01.png"
}
```

#### `data/image_cache.json` (Auto-generated)
Stores Telegram file_id mappings for bandwidth optimization:

```json
{
  "Continue-01.png": "AgACAgIAAxkBAAI...",
  "Title-01.png": "AgACAgIAAxkBAAJ...",
  "Waypoint-Earth_Launch.png": "AgACAgIAAxkBAAK..."
}
```

### Core Classes

#### GameEngine.php
Main image selection logic:

- `selectContextualImage($session, $result, $context)` - Primary image selection
- `getImageIdentifier($imageName)` - Returns file_id if cached, URL if not
- `loadImageConfig()` / `saveImageCache()` - Configuration and caching
- `validateImageUrl($url)` - BOT_BASE_URL validation

#### TelegramAPI.php
Handles file_id extraction:

- `sendPhoto()` - Extracts file_id from Telegram responses
- Returns file_id in result array for caching

#### uranus_game_bot.php
Orchestrates the system:

- `sendMessage()` - Caches file_ids after successful sends
- Automatic cache building during gameplay

## Image Selection Priority

The system selects images in this priority order:

1. **Waypoint Images** - When reaching specific waypoints
2. **Milestone Images** - At 25%, 50%, 75% progress
3. **Event Images** - When events occur
4. **Space View Pool** - Rotated for regular travel
5. **Fallback Image** - Default when nothing else applies

## File ID Caching System

### How It Works

1. **First Send**: Image sent via URL → Telegram returns file_id → Cached automatically
2. **Subsequent Sends**: Uses cached file_id → Instant, no bandwidth
3. **Persistence**: Cache survives bot restarts and sessions

### Benefits

- **10-100x faster loading** after first send
- **Zero bandwidth usage** for cached images
- **No re-uploading** of same images
- **Telegram handles hosting** for cached images

## Image Rotation

### Space Views
Rotates through `space_views` array to prevent repetition:
```json
"space_views": ["Space-View-02.png", "Space-View-03.png", "Space-View-04.png"]
```

### Waypoint Variants
Multiple images per waypoint with rotation:
```json
"Asteroid Belt": ["Waypoint-Asteroid_Belt.png", "Waypoint-Asteroid_Belt2.png"]
```

### Session Tracking
Tracks which images shown in session data:
```php
$session['last_shown_images']['space_views'] = 2; // Next will be index 2
```

## Configuration

### BOT_BASE_URL Setup

Required for first-time image sends:

```php
// In config.php
define('BOT_BASE_URL', 'https://yourdomain.com');
```

Images must be accessible at: `BOT_BASE_URL/media/image-name.png`

### Adding New Images

1. **Add image file** to `media/` directory
2. **Update image_config.json** with new mappings
3. **Test first send** - file_id will be cached automatically

### Adding New Waypoints

1. **Add waypoint** to `data/waypoints.json`
2. **Add image mapping** to `data/image_config.json`
3. **Ensure names match exactly**

## Error Handling

### Invalid BOT_BASE_URL
- Logs warning when URL is placeholder
- Returns `null` for image_url
- Falls back to text-only messages

### Missing Images
- Uses fallback image from config
- Logs errors for debugging
- Continues game without crashing

### Cache Corruption
- Automatically recreates empty cache
- Continues with URL-based sends
- Self-healing system

## Performance Considerations

### Bandwidth Usage
- **First send**: Full image upload (one-time cost)
- **Subsequent sends**: File_id only (minimal bandwidth)
- **Cache hit rate**: Near 100% after initial gameplay

### Memory Usage
- **Config cache**: Static, loaded once
- **File_id cache**: Small JSON file
- **Session tracking**: Minimal overhead

### File System
- **Cache file**: Auto-created, auto-updated
- **Config file**: Read-only during runtime
- **Media files**: Served by web server

## Development Workflow

### Testing New Images
1. Add image to `media/` directory
2. Update `image_config.json`
3. Test with bot - first send uses URL
4. Verify file_id cached in `image_cache.json`
5. Test second send - should use file_id

### Debugging
- Check `image_cache.json` for cached file_ids
- Verify `BOT_BASE_URL` is configured correctly
- Check logs for image-related warnings
- Test image URLs manually in browser

### Production Deployment
1. Ensure `BOT_BASE_URL` points to production domain
2. Verify all images accessible via HTTP
3. Test first-time image sends
4. Monitor cache file creation
5. Verify file_id usage in subsequent sends

## Future Enhancements

### Planned Features
- **Event-specific images** - When event data structure supports it
- **Dynamic image generation** - Based on game state
- **Image pre-warming** - Send all images to admin on startup
- **Cache invalidation** - For updated images

### Extensibility
- **Custom image providers** - Beyond file system
- **Image processing** - Resize, optimize, watermark
- **Analytics** - Track image usage and performance
- **A/B testing** - Different images for different users

## Troubleshooting

### Common Issues

**Images not showing:**
- Check BOT_BASE_URL configuration
- Verify image files exist in media/ directory
- Check image_cache.json for cached file_ids

**Slow image loading:**
- First send is always slow (URL upload)
- Subsequent sends should be instant (file_id)
- Check network connectivity

**Cache not working:**
- Verify image_cache.json is writable
- Check file_id extraction in TelegramAPI.php
- Monitor bot logs for caching errors

**Wrong images showing:**
- Verify waypoint names match exactly
- Check image_config.json mappings
- Test image selection logic manually

### Debug Commands

```php
// Check image config
$config = $gameEngine->loadImageConfig();
var_dump($config);

// Check cache
$cache = $gameEngine->loadImageCache();
var_dump($cache);

// Test image selection
$image = $gameEngine->selectContextualImage($session, $result, $context);
echo $image;
```

## API Reference

### GameEngine Methods

#### `selectContextualImage($session, $result, $context)`
Primary image selection method.

**Parameters:**
- `$session` - Current game session
- `$result` - Day advancement result
- `$context` - Additional context data

**Returns:** Image identifier (file_id or URL)

#### `getImageIdentifier($imageName)`
Get image identifier with caching.

**Parameters:**
- `$imageName` - Name of image file

**Returns:** file_id if cached, URL if not

#### `saveImageCache($imageName, $fileId)`
Save file_id to cache.

**Parameters:**
- `$imageName` - Name of image file
- `$fileId` - Telegram file_id

**Returns:** void

### Configuration Schema

#### Image Config Structure
```json
{
  "waypoints": {
    "Waypoint Name": "image.png" | ["image1.png", "image2.png"]
  },
  "milestones": {
    "25": "milestone-25.png",
    "50": "milestone-50.png",
    "75": "milestone-75.png"
  },
  "space_views": ["image1.png", "image2.png", "image3.png"],
  "events": ["event1.png", "event2.png"],
  "status": ["status1.png", "status2.png"],
  "title": "title-image.png",
  "continue": "continue-image.png",
  "bulk": "bulk-image.png",
  "fallback": "fallback-image.png"
}
```

#### Cache Structure
```json
{
  "image-name.png": "AgACAgIAAxkBAAI...",
  "another-image.png": "AgACAgIAAxkBAAJ..."
}
```

## Conclusion

The image selection system provides a robust, performant, and user-friendly way to display contextual images in the Occupy Uranus bot. The automatic file_id caching ensures optimal performance while the flexible configuration system allows for easy customization and expansion.

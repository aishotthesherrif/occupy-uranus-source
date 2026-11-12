# Image System Reference

**Last Updated:** 2026-03-02
**Last Verified Against Code:** 2026-03-02

Complete documentation of the image selection system in Occupy Uranus bot. **Out the window** is used only for zones, waypoints, and events—i.e. views and activities outside the ship (travel vistas, waypoint approaches/docks, random events). Other contexts (title, game over, victory, post-event, shop/station when generic) are not given “out the window” descriptions.

**Recent Changes:**
- 2026-03-02: Added event-specific image support (62 images for main aesthetic, Event-{event_id}.jpg naming)
- 2026-03-02: Cache keys now include aesthetic (format: `aesthetic:filename`) to support aesthetic switching during gameplay
- 2026-03-01: Added 'newgame' and 'launch' contexts for New Game View and Launch Sequence View
- 2026-03-01: Renamed "Launch View" to "New Game View" for clarity
- 2026-03-01: "Out the window" limited to zones, waypoints, and events (activities outside the ship)
- 2026-03-01: Aligned doc with code: session key `aesthetic`, filenames .jpg, post_event context, directory structure, line numbers
- 2026-03-01: Swapped 'station' and 'waypoint' context terminology for clarity
- 2026-02-28: Implemented zone-based day/status images with 3+ variants per zone (auto-discovered)
- 2026-02-28: All images use .jpg format for consistency
- 2026-02-28: Added multiple zone variant support with random selection

---

## Overview

The bot uses a contextual image system that selects appropriate images based on view type, game state, and random factors. All images are served via Telegram's automatic file_id caching system for optimal performance.

**Image Selection:** `getContextImageUrl()` in ViewManager.php (presentation logic)
**Caching:** TelegramAPI.php (automatic, transparent file_id caching)
**Cache File:** `data/image_cache.json` (Telegram file_id storage)
**Media Directory:** `media/{aesthetic}/` (e.g. `media/main/`; image count varies by aesthetic and zone variants)

### Architecture

- **ViewManager** decides WHICH image to show based on game context (presentation responsibility)
- **TelegramAPI** handles HOW to send efficiently with automatic file_id caching (optimization responsibility)
- Caching is completely transparent - ViewManager returns local paths, TelegramAPI handles upload/cache automatically

---

## View–Image Mapping

Which views show an image and which context they use. See **Image Selection Criteria by Context** below for per-context details.

### Views with images

| View | Image context | Selection |
|------|----------------|-----------|
| Journey View | `'day'` | Milestone → waypoint → zone (random variant) |
| Status View | `'status'` | Zone-based, random variant |
| Event View | `'event'` | Random from Event-01…04 |
| Post-Event View | `'post_event'` | Success/failure outcome, random variant |
| Shop View | `'shop'` | Waypoint image or `Continue-01.jpg` |
| Station Choice View | `'waypoint'` | Waypoint-specific image |
| Waypoint View | `'station'` | Waypoint-specific image |
| Game Over View | `'gameover'` | Static `gameover.jpg` |
| Victory View | `'victory'` | Static `victory.jpg` |
| Start View | `'title'` | Static `Title-01.jpg` (conditional) |
| Main Menu View | `'title'` | Static `Title-01.jpg` |
| Aesthetic View | `'title'` | Static `Title-01.jpg` |
| New Game View | `'newgame'` | Static `NewGame-01.jpg` |
| Launch Sequence View | `'launch'` | Static `Launch-01.jpg` |
| Leaderboard View | Static | Static `Leaderboard.jpg` |
| Who's Playing View | Static | Static `WhosPlaying.jpg/png` |

### Views without images

These views set `image_url => null` (text/keyboard only):

| View | Rationale |
|------|-----------|
| Map View | ASCII art map; image would conflict |
| Return Confirmation View | Simple decision prompt |
| Help View | Text-heavy instructions |
| Abandon Confirmation View | Simple yes/no |
| Private DM Prompt | Short informational |
| Group Confirmation | Mode switch prompt |

**In code:** Views with images call `getContextImageUrl($context, $session, …)` in their render return; views without set `'image_url' => null`. To add image support to a view that has none, see **Adding image support to an existing view** under Adding New Images.

---

## Image Selection Criteria by Context

### Title Context (`'title'`)

**When Used:** Main menu, start screen
**Criteria:** Static image, no conditions
**Image:** `Title-01.jpg`

**Code:** ViewManager.php (getContextImageUrl method)

---

### New Game Context (`'newgame'`)

**When Used:** New Game View (pre-launch setup screen)
**Criteria:** Static image, no conditions
**Image:** `NewGame-01.jpg`

**Code:** ViewManager.php (getContextImageUrl method)

---

### Launch Context (`'launch'`)

**When Used:** Launch Sequence View (dramatic launch moment)
**Criteria:** Static image, no conditions
**Image:** `Launch-01.jpg`

**Out the window:** Launch from Earth orbit—engines igniting, Earth falling away, beginning of journey.

**Code:** ViewManager.php (getContextImageUrl method)

---

### Status Context (`'status'`)

**When Used:** Detailed status view (chad dashboard)
**Criteria:** Based on distance zone, **random selection from zone variants**
**Images:**
- `status_earth*.jpg/png` - 0-10% progress (Earth departure)
- `status_inner*.jpg/png` - 10-35% progress (Mars region)
- `status_belt*.jpg/png` - 35-55% progress (Asteroid belt / Jupiter)
- `status_outer*.jpg/png` - 55-80% progress (Saturn / mid-journey)
- `status_uranus*.jpg/png` - 80-100% progress (Final approach)

**Out the window:** Same region as the current **Distance zone** (see table below)—e.g. Earth and Moon still dominant, inner system with Sun/Mars, belt/Jupiter, Saturn and rings, or Uranus filling the view. The status image is a “dashboard” or cockpit-style framing of that same stretch of the journey.

**Selection Logic:**
1. Calculate distance zone from `session['progress']['distance']`
2. Find all images matching `status_{zone}*.{jpg,png}` pattern
3. **Randomly select one** from available zone images
4. Return selected image

**Naming Convention:**
- Single image: `status_earth.jpg`
- Multiple images: `status_earth_01.jpg`, `status_earth_02.jpg`, `status_earth_03.jpg`, etc.
- Mixed formats: `status_earth.jpg`, `status_earth_01.png`, `status_earth_02.jpg`

**Code:** ViewManager.php (selectRandomZoneImage method)

---

### Game Over Context (`'gameover'`)

**When Used:** Death conditions (no chad, no fuel, hull destroyed)
**Criteria:** Static image, no conditions
**Image:** `gameover.jpg`

**Code:** ViewManager.php (getContextImageUrl method, gameover context)

---

### Victory Context (`'victory'`)

**When Used:** Reaching Uranus (distance >= TOTAL_DISTANCE)
**Criteria:** Static image, no conditions
**Image:** `victory.jpg`

**Code:** ViewManager.php (getContextImageUrl method, victory context)

---

### Leaderboard Context (static)

**When Used:** Leaderboard View (viewing rankings)
**Criteria:** Static image, respects user's aesthetic preference
**Image:** `Leaderboard.jpg`

**Selection:** Shows trophy/rankings themed image. Uses user's aesthetic from session if available, defaults to 'main' if no session.

**Code:** uranus_game_bot.php (handleLeaderboard method, lines 1088-1103)

---

### Who's Playing Context (static)

**When Used:** Who's Playing View (viewing active players)
**Criteria:** Static image, respects user's aesthetic preference
**Image:** `WhosPlaying.jpg/png`

**Selection:** Shows multiplayer/community themed image. Uses user's aesthetic from session if available, defaults to 'main' if no session. Supports multiple extensions via glob pattern.

**Code:** uranus_game_bot.php (handleWhosPlaying method)

---

### Event Context (`'event'`)

**When Used:** Random events during day advance
**Criteria:** Event-specific image priority, with fallback to generic images
**Images:**
- **Event-specific (main aesthetic):** `Event-{event_id}.jpg` (e.g., `Event-distress_signal.jpg`, `Event-asteroid_field.jpg`)
- **Generic fallback:** `Event-01.jpg`, `Event-02.jpg`, `Event-03.jpg`, `Event-04.jpg`

**Out the window:** The specific event scenario — distress signal shows derelict vessel, asteroid field shows tumbling rocks, space storm shows plasma arcs, etc. Each event has unique visual identity (main aesthetic) or uses generic space event imagery (other aesthetics).

**Selection Logic:**
1. Get event ID from `session['pending_event']`
2. Try event-specific image: `Event-{event_id}.jpg`
3. Check if file exists in current aesthetic folder
4. If found: use event-specific image
5. If not found: fall back to random generic (`Event-01.jpg` through `Event-04.jpg`)
6. Log warning if main aesthetic missing event-specific image (DEBUG_MODE only)

**Aesthetic Support:**
- **Main aesthetic:** 62 event-specific images available (one per event)
- **Other aesthetics (anime, pixel, rick):** Use generic fallback images (4 images randomized)

**Code:** ViewManager.php (getContextImageUrl method, event context, lines 1211-1234)

**Reference:** See `EVENT_IMAGE_REFERENCE.md` for complete event-to-image mapping

---

### Post-Event Context (`'post_event'`)

**When Used:** After resolving a random event (success or failure outcome screen)
**Criteria:** Event outcome: `'success'` or `'failure'`
**Images:**
- Success: `post_event_success_01.jpg`, `post_event_success_02.jpg`, `post_event_success_03.jpg` (random one)
- Failure: `post_event_failure_01.jpg`, `post_event_failure_02.jpg`, `post_event_failure_03.jpg` (random one)

**Selection Logic:**
1. `getContextImageUrl('post_event', $session, $eventOutcome)` with `$eventOutcome` from view context
2. Default outcome is `'success'` if not provided
3. Pick one of the three variants at random for that outcome

**Code:** ViewManager.php (getContextImageUrl method, post_event context; renderPostEventView passes event_outcome)

---

### Waypoint Context (`'waypoint'`)

**When Used:** Approaching a waypoint, deciding whether to stop or continue
**Criteria:** Current waypoint name
**Images:** See Waypoint Mapping table below

**Out the window:** See **Waypoint Image Mapping** table below—each waypoint has a specific vista (Earth orbit, Moon, Mars, Ceres, Jupiter, Saturn, deep space, Uranus arrival).

**Selection Logic:**
1. Get current waypoint from `session['at_waypoint']` or `getCurrentWaypoint()`
2. Map waypoint name to image filename
3. Return null if no specific image (falls back to zone-based)

**Code:** ViewManager.php (getContextImageUrl method, waypoint context)

---

### Station Context (`'station'`)

**When Used:** Stopped/docked at a station
**Criteria:** Current waypoint name
**Images:** See Waypoint Mapping table below

**Out the window:** Same as waypoint—see **Waypoint Image Mapping** table (view from the dock).

**Selection Logic:**
1. Get current waypoint from `session['at_waypoint']` or `getCurrentWaypoint()`
2. Map waypoint name to image filename
3. Return null if no specific image (falls back to zone-based)

**Code:** ViewManager.php (getContextImageUrl method, station context)

---

### Shop Context (`'shop'`)

**When Used:** Shop view (at waypoint or general shop)
**Criteria:** Current waypoint, with fallback
**Images:** Waypoint image OR `Continue-01.jpg` fallback

**Selection Logic:**
1. Get current waypoint from `session['at_waypoint']` or `getCurrentWaypoint()`
2. Map waypoint name to image filename
3. If no waypoint image, use `Continue-01.jpg` fallback

**Code:** ViewManager.php (getContextImageUrl method, shop context)

---

### Day Context (`'day'`)

**When Used:** Journey view (main travel screen)
**Criteria:** 3-tier priority system
**Images:** Milestone images, waypoint images, zone images

**Selection Logic (in priority order):**

#### 1. Milestone Images (HIGHEST PRIORITY)
- **Trigger:** `session['milestone_just_reached']` is set
- **Values:** 25, 50, or 75
- **Images:**
  - `milestone-25.jpg` - 25% progress celebration
  - `milestone-50.jpg` - 50% progress celebration
  - `milestone-75.jpg` - 75% progress celebration
- **Shown:** Once per milestone per session
- **Out the window:** **25%** — Mars region; red planet in the distance, “quarter of the way” vibe. **50%** — Belt/Jupiter area; halfway marker, gas giant or asteroids. **75%** — Saturn region; rings in view, “almost there” to Uranus.
- **Code:** ViewManager.php (getContextImageUrl method, milestone priority)

#### 2. Waypoint Images
- **Trigger:** `session['at_waypoint']` is set (player docked at station)
- **Images:** See Waypoint Mapping table below
- **Out the window:** Same as Waypoint/Station context—the current stop (Earth orbit, Moon, Mars, Ceres, Jupiter, Saturn, deep space, or Uranus arrival).
- **Code:** ViewManager.php (getContextImageUrl method, waypoint priority)

#### 3. Zone-Based Day Images (FALLBACK)
- **Trigger:** No milestone, not at waypoint
- **Images:**
  - `day_earth*.jpg/png` - 0-10% progress
  - `day_inner*.jpg/png` - 10-35% progress
  - `day_belt*.jpg/png` - 35-55% progress
  - `day_outer*.jpg/png` - 55-80% progress
  - `day_uranus*.jpg/png` - 80-100% progress
- **Selection:** Find all matching zone images, **randomly select one**
- **Out the window:** Same regions as the **Distance zone** table below—Earth and near-Earth space, inner system (Mars/Sun), belt/Jupiter, Saturn and outer space, or Uranus filling the view. The “daily” travel vista for that leg of the journey.
- **Code:** ViewManager.php (selectRandomZoneImage method)

**Naming Convention:**
- Single image: `day_earth.jpg`
- Multiple images: `day_earth_01.jpg`, `day_earth_02.jpg`, `day_earth_03.jpg`, etc.
- Mixed formats: `day_earth.jpg`, `day_earth_01.png`, `day_earth_02.jpg`

**Selection Priority:** Milestone → waypoint → random zone variant

---

## Waypoint Image Mapping

**Handler:** `getWaypointImageName()` in ViewManager.php

| Waypoint Name | Image Filename | Out the window | Status |
|---------------|----------------|----------------|--------|
| Earth Orbit | `Waypoint-Earth_Launch.jpg` | Earth from low orbit—blue planet, clouds, maybe ISS or launch facilities; “leaving home.” | ✅ Active |
| Moon | `Waypoint-Moon.jpg` | Lunar surface, base or station, Earth small in the sky; first stop. | ✅ Active |
| Mars Base | `Waypoint-Mars-Flyby.jpg` | Red planet, flyby or approach; Mars base or orbital presence. | ✅ Active |
| Ceres Asteroid Station | `Waypoint-Ceres_Station.jpg` | Dwarf planet Ceres and/or asteroid station; belt region. | ✅ Active |
| Jupiter Gas Mine | `Waypoint-Jupiter_Slingshot.jpg` | Jupiter dominant, gas mine or slingshot geometry; gas giant and moons. | ✅ Active |
| Saturn Outpost | `Waypoint-Saturn_Rings.jpg` | Saturn and rings, outpost or station; iconic ringed view. | ✅ Active |
| Deep Space 69 | `Waypoint-Deep_Space_69.jpg` | Empty deep space between Saturn and Uranus—distant stars, maybe nebula; alone in the void. | ✅ Active |
| Uranus Arrival | `Waypoint-Uranus_Arrival.jpg` | Uranus filling the view, blue-green disk, maybe rings or moons; final destination. | ✅ Active |

**Note:** If a waypoint image file is missing, the code falls back to zone-based imagery (path check in getContextImageUrl).

---

## Distance Zone Calculation

**Handler:** `getDistanceZone()` in ViewManager.php

| Zone | Progress % | Distance Range | Region | Out the window |
|------|-----------|----------------|--------|----------------|
| `earth` | 0-10% | 0-280M km | Earth departure / near-Earth space | Earth still large, Moon nearby; familiar space, “just left.” |
| `inner` | 10-35% | 280M-980M km | Inner solar system (Mars region) | Sun/Mars region; inner system, warmer space. |
| `belt` | 35-55% | 980M-1,540M km | Asteroid belt / Jupiter region | Asteroids, Jupiter in the distance; belt and gas giant. |
| `outer` | 55-80% | 1,540M-2,240M km | Saturn / mid-journey | Saturn and rings, outer solar system; “past the giants.” |
| `uranus` | 80-100% | 2,240M-2,800M km | Final approach to Uranus | Uranus growing in the view; blue-green disk, final approach. |

**Calculation:**
```php
$pct = ($distance / TOTAL_DISTANCE) * 100;
if ($pct < 10)  return 'earth';
if ($pct < 35)  return 'inner';
if ($pct < 55)  return 'belt';
if ($pct < 80)  return 'outer';
return 'uranus';
```

**Purpose:** Provides visual progression through the solar system as players travel.

---

## File_id Caching System

**Handler:** TelegramAPI.php (automatic, transparent)
**Cache File:** `data/image_cache.json`

### How It Works

**Automatic Caching (New Architecture):**

The caching system is now completely automatic and transparent to callers:

**First Send:**
1. ViewManager returns local file path from `getContextImageUrl()`
2. TelegramAPI's `sendPhoto()` checks cache - not found
3. TelegramAPI uploads image by local file path to Telegram
4. Telegram returns `file_id` (permanent reference)
5. TelegramAPI automatically saves `file_id` to cache
6. Message delivered to user

**Subsequent Sends:**
1. ViewManager returns same local file path from `getContextImageUrl()`
2. TelegramAPI's `sendPhoto()` checks cache - found!
3. TelegramAPI uses cached `file_id` instead of uploading
4. Telegram uses existing file (no upload needed)
5. Message delivered instantly

**Key Improvement:** No manual cache management needed! The image cache is now an internal optimization detail of TelegramAPI, not something ViewManager or uranus_game_bot.php need to manage.

### Benefits

- **Faster delivery:** No image upload delay after first send
- **Reduced bandwidth:** File sent once, referenced forever
- **Better UX:** Messages appear instantly
- **Reliability:** Cached file_id never expires (per Telegram docs)
- **Simpler code:** Automatic caching, no manual intervention
- **Proper separation:** Telegram optimization detail stays in TelegramAPI layer

### Code References

- **Automatic caching:** TelegramAPI.php `sendPhoto()` method
- **Cache check:** TelegramAPI.php `getImageFromCache()`
- **Cache save:** TelegramAPI.php `saveImageToCache()`
- **Cache load:** TelegramAPI.php `loadImageCache()`
- **Cache path:** TelegramAPI.php `getImageCacheFilePath()`

### Cache Structure

```json
{
  "main:Title-01.jpg": "AgACAgIAAxkBAAIBY2V...",
  "main:Waypoint-Earth_Launch.jpg": "AgACAgIAAxkBAAIBZGV...",
  "anime:milestone-25.jpg": "AgACAgIAAxkBAAIBZWV...",
  "pixel:day_earth_01.jpg": "AgACAgIAAxkBAAIBZmV...",
  "rick:status_belt_02.jpg": "AgACAgIAAxkBAAIBZnV..."
}
```

**Key:** Cache key in format `{aesthetic}:{filename}` (e.g., `main:Title-01.jpg`, `anime:status_belt_02.jpg`)
**Value:** Telegram file_id string

**Important:** Each aesthetic has separate cache entries for the same filename. This ensures players switching aesthetics mid-game see the correct cached images for their selected aesthetic.

**Migration Note:** Old cache format (filename-only keys) is not compatible. Use `/resetcache` admin command to clear old cache entries before deploying this version.

---

## Media Directory Structure

**Location:** `media/{aesthetic}/` (e.g. `media/main/`)
**Image counts:** Vary by aesthetic (see IMAGE_GENERATION_CHECKLIST.md for current inventory)

### Zone-Based Images

**Day Images:**
- Naming pattern: `day_{zone}_{NN}.jpg` where zone is earth, inner, belt, outer, or uranus
- Example: `day_earth_01.jpg`, `day_earth_02.jpg`, `day_earth_03.jpg`, etc.
- Auto-discovered via glob pattern - any number of variants can exist per zone

**Status Images:**
- Naming pattern: `status_{zone}_{NN}.jpg`
- Example: `status_earth_01.jpg`, `status_earth_02.jpg`, etc.
- Auto-discovered via glob pattern - any number of variants can exist per zone

### Waypoint Images
- `Waypoint-Earth_Launch.jpg` - Earth Orbit
- `Waypoint-Moon.jpg` - Moon
- `Waypoint-Mars-Flyby.jpg` - Mars Base
- `Waypoint-Ceres_Station.jpg` - Ceres Asteroid Station
- `Waypoint-Jupiter_Slingshot.jpg` - Jupiter Gas Mine
- `Waypoint-Saturn_Rings.jpg` - Saturn Outpost
- `Waypoint-Deep_Space_69.jpg` - Deep Space 69
- `Waypoint-Uranus_Arrival.jpg` - Uranus Arrival

### Milestone Images
- `milestone-25.jpg` - 25% progress celebration
- `milestone-50.jpg` - 50% progress celebration
- `milestone-75.jpg` - 75% progress celebration

### Event Images

**Event-Specific (Main Aesthetic):**
- Naming pattern: `Event-{event_id}.jpg` (e.g., Event-distress_signal.jpg, Event-asteroid_field.jpg)
- One unique image per event (62 total for main aesthetic)
- Provides specific visual identity for each event
- See `EVENT_IMAGE_REFERENCE.md` for complete list

**Generic Fallback (All Aesthetics):**
- Naming pattern: `Event-{NN}.jpg` (e.g., Event-01.jpg, Event-02.jpg, Event-03.jpg, Event-04.jpg)
- Random selection from available generic event images
- Used when event-specific image not found or for non-main aesthetics
- Count: 4 per aesthetic (generic)

### Post-Event Outcome Images
- Naming pattern: `post_event_{outcome}_{NN}.jpg`
- Success variants: `post_event_success_01.jpg`, `post_event_success_02.jpg`, `post_event_success_03.jpg`
- Failure variants: `post_event_failure_01.jpg`, `post_event_failure_02.jpg`, `post_event_failure_03.jpg`
- Graceful fallback if missing (no image shown)

### End-Game Images
- `gameover.jpg` - Game over screen
- `victory.jpg` - Victory screen

### Utility Images
- `Title-01.jpg` - Title screen
- `NewGame-01.jpg` - New game setup screen
- `Launch-01.jpg` - Launch sequence dramatic moment
- `Continue-01.jpg` - Continue/fallback image

**Auto-Discovery:** Zone-based images use glob patterns (`day_earth*.jpg`) to find all variants at runtime. Just add numbered files - no code changes needed.

---

## Aesthetic System

The bot supports **multiple visual themes** that players can select from the New Game View or Main Menu. Each aesthetic provides a unique visual style while maintaining the same game mechanics.

### Available Aesthetics

**Image Storage:** `media/{aesthetic_id}/` directories (e.g., `media/main/`, `media/anime/`)

| Aesthetic ID | Display Name | Description | Status |
|--------------|--------------|-------------|--------|
| `main` | Main | Classic space aesthetic with realistic visuals | ✅ Active (default) |
| `anime` | Anime | Japanese anime-inspired style | ✅ Active |
| `pixel` | Pixel Art | Retro 8-bit/16-bit pixel art style | ✅ Active |
| `rick` | Rick & Morty | Rick and Morty animated universe style | ✅ Active |

### Aesthetic Selection

**User Flow:**
1. Player accesses aesthetic selection from Launch View or Main Menu
2. Grid of aesthetic buttons shown (2 per row)
3. Player selects aesthetic
4. `session['aesthetic']` updated (e.g. `'main'`, `'anime'`, `'pixel'`, `'rick'`)
5. All future images use that aesthetic’s folder under `media/{aesthetic}/`

**Default:** If no aesthetic selected, the default from `data/aesthetics.json` is used (key `"default"`; typically `'main'`).

**Code References:**
- Aesthetic selection: `handleAestheticCallback()` in uranus_game_bot.php (sets `$session['aesthetic'] = $action`)
- Image path resolution: `getContextImageUrl()` in ViewManager.php
- Aesthetic config: `data/aesthetics.json`

### Image Organization

All images are organized by aesthetic (directory name = aesthetic `id` from aesthetics.json):
```
media/
├── main/
│   ├── Title-01.jpg
│   ├── Waypoint-Earth_Launch.jpg
│   ├── milestone-25.jpg
│   └── ...
├── anime/
│   ├── Title-01.jpg
│   ├── Waypoint-Earth_Launch.jpg
│   └── ...
├── pixel/
│   ├── Title-01.jpg
│   └── ...
└── rick/
    ├── Title-01.jpg
    └── ...
```

**Image Naming:** Identical across all aesthetics (ensures consistent selection logic)

**Format:** Primarily JPG (smaller file sizes, faster loading). PNG used only when transparency required.

---

## Image Specifications

### Technical Requirements

- **Format:** JPG preferred (smaller file sizes), PNG only when transparency required
- **Resolution:** 512x512 or 1024x1024 pixels (Telegram optimized)
- **Color Depth:** 24-bit (JPG) or 32-bit with alpha (PNG)
- **File Size:** < 1MB recommended (faster uploads, JPG typically 100-300KB)
- **Naming:** Descriptive, kebab-case or PascalCase, consistent across aesthetics

### Style Guidelines

- **Theme:** Space/sci-fi aesthetic
- **Palette:** Deep space blues, purples, cosmic colors
- **Style:** Modern, clean, visually appealing
- **Consistency:** Maintain visual coherence across all images

### Content Guidelines

- **Waypoint Images:** Represent specific location/object (planets, stations)
- **Milestone Images:** Convey achievement and progress
- **Space Views:** Show various space environments (variety)
- **Event Images:** Versatile for different event types (generic enough)
- **Status/Zone Images:** Represent region of solar system
- **Victory/Game Over:** Clear emotional tone (celebration/failure)

---

## Adding New Images

### Adding image support to an existing view

If a view currently returns `'image_url' => null` and you want it to show an image:

1. **Choose a context** from the View–Image Mapping table (or add a new context in `getContextImageUrl()`).
2. **Update the view renderer** in ViewManager so the return includes:
   ```php
   'image_url' => $this->getContextImageUrl('your_context', $session),
   ```
   (Use the same signature as other views; pass `$eventOutcome` for `'post_event'`.)
3. **Implement the context** in `getContextImageUrl()` if it’s new (waypoint map, static file, etc.).
4. **Add or reuse images** in `media/{aesthetic}/` and ensure filenames match what the context returns.
5. **Update this doc** (View–Image Mapping and any new context section).

### Step-by-Step Process (new image assets)

1. **Create image** following specifications above
2. **Name image** using the appropriate naming convention:
   - **Zone images (auto-discovered):** Use pattern `{context}_{zone}_NN.ext`
     - Examples: `day_earth_01.jpg`, `status_inner_02.png`, `day_belt_03.jpg`
     - No code changes needed - system auto-discovers all matching files
   - **Waypoint images:** Add to `getWaypointImageName()` map in ViewManager.php
   - **Milestone images:** Add to `milestoneImages` array in `getContextImageUrl()` method
   - **Event images:** Add to `$eventImages` array in `getContextImageUrl()` method
3. **Place in `media/` directory**
4. **Test locally:**
   - Verify image loads via BOT_BASE_URL
   - Confirm correct context triggers image
   - For zone images: trigger context multiple times to verify random selection
   - Verify file_id caching works after first send
5. **Deploy** and monitor logs for image loading errors

**Adding More Zone Variants (Easiest):**
- Just drop new files in `media/` with the naming pattern
- No code changes required!
- Examples:
  - Add `day_earth_04.jpg` to earth zone day images
  - Add `status_outer_03.png` to outer zone status images
  - System automatically discovers and includes them

### Code Locations

- **Waypoint mapping:** ViewManager.php `getWaypointImageName()` method
- **Milestone array:** ViewManager.php `getContextImageUrl()` method
- **Event array:** ViewManager.php `getContextImageUrl()` method
- **Zone calculation:** ViewManager.php `getDistanceZone()` method
- **Main selection logic:** ViewManager.php `getContextImageUrl()` method

---

## Image Configuration

**Location:** All image selection logic is in `bot/ViewManager.php`

**Key methods:**
- `getContextImageUrl()` (line ~1176) - Main image selection dispatcher
- `selectRandomZoneImage()` (line ~1279) - Zone-based variant selection
- `getWaypointImageName()` (line ~1303) - Waypoint-specific image mapping
- `getDistanceZone()` (line ~1325) - Distance zone calculation

**Design:** Image selection is hardcoded in PHP for performance and reliability (no config file reads per request).

---

## Troubleshooting

### Image Not Appearing

**Check:**
1. Image file exists in `media/` directory
2. Filename matches exactly (case-sensitive)
3. File permissions are readable (644 or 755)
4. MEDIA_DIR constant is defined in config.php
5. BOT_BASE_URL is set correctly
6. Image context matches view type

**Debug:**
- Check `data/bot.log` for image loading errors
- Verify `getContextImageUrl()` returns expected filename
- Test image URL directly in browser

### Wrong Image Showing

**Check:**
1. Distance zone calculation (verify progress percentage)
2. Session state (`at_waypoint`, `milestone_just_reached`)
3. Random selection (20% space view chance in day context)
4. Waypoint name spelling (must match exactly)

### Images Not Caching

**Check:**
1. `data/image_cache.json` is writable (permissions)
2. Cache file is being created after first send
3. File_id format is valid (starts with "AgAC...")
4. TelegramAPI `sendPhoto()` is automatically caching after successful upload

---

## Summary

The image system provides contextual, dynamic imagery that enhances the player experience:

- **12 contexts** for different view types: title, newgame, launch, status, gameover, victory, event, post_event, waypoint, station, shop, day
- **Flexible image count** - supports unlimited zone variants (auto-discovered)
- **3-tier priority** for Journey view (milestone → waypoint → zone)
- **5 distance zones** for progressive visual storytelling
- **Random zone variants** - multiple images per zone for visual variety
- **Auto-discovery** - zone images discovered at runtime (no code changes to add more)
- **file_id caching** for optimal performance

Zone image selection uses glob patterns to auto-discover all matching files, then randomly selects one. Event images also randomize for variety. All other contexts are deterministic. Images are automatically cached after first send using Telegram's file_id system (handled by TelegramAPI).

---

**For view-specific details (triggers, handlers, keyboards):** See [`GAME_VIEWS.md`](GAME_VIEWS.md)
**For implementation details:** See `bot/ViewManager.php` (getContextImageUrl, getWaypointImageName, getDistanceZone methods) and `bot/TelegramAPI.php` (sendPhoto method with automatic caching)

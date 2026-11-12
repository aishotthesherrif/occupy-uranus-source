# Image Generation Checklist for Occupy Uranus Bot

**Last Updated:** 2026-03-01
**Status:** Multiple aesthetics with varying image counts

---

## Overview

This document tracks the image system for the Occupy Uranus bot. The system uses **auto-discovery** with glob patterns - each aesthetic can have any number of zone variants, and the game automatically finds and randomly selects from whatever exists.

**Key Principle:** There are no "tiers" or "minimum sets." Each aesthetic has whatever images have been generated. The system gracefully handles missing images with fallbacks.

---

## Aesthetic Inventory

Complete breakdown of what exists per aesthetic (verified 2026-03-01):

| Aesthetic | Total | Day Zone | Status Zone | Waypoint | Milestone | Event | Post-Event | End-Game | Utility |
|-----------|-------|----------|-------------|----------|-----------|-------|------------|----------|---------|
| **main** | **97** | 35 | 35 | 10 | 3 | 4 | 6 ✅ | 2 | 2 |
| **simpsons** | **66** | 20 | 20 | 9 | 3 | 4 | 6 ✅ | 2 | 2 |
| **rick** | **68** | 30 | 22 | 7 | 3 | 2 | 0 ❌ | 2 | 2 |
| **classic** | **45** | 15 | 15 | 6 | 3 | 2 | 0 ❌ | 2 | 2 |
| **pixel** | **45** | 15 | 15 | 6 | 3 | 2 | 0 ❌ | 2 | 2 |

**Zone Variant Distribution (day+status):**

| Aesthetic | earth | inner | belt | outer | uranus | Notes |
|-----------|-------|-------|------|-------|--------|-------|
| **main** | 7+7 | 7+7 | 7+7 | 7+7 | 7+7 | Highest variety |
| **simpsons** | 4+4 | 4+4 | 4+4 | 4+4 | 4+4 | Consistent across zones |
| **rick** | 6+6 | 6+5 | 6+4 | 6+4 | 6+3 | Uneven (more day than status) |
| **classic** | 3+3 | 3+3 | 3+3 | 3+3 | 3+3 | Consistent across zones |
| **pixel** | 3+3 | 3+3 | 3+3 | 3+3 | 3+3 | Consistent across zones |

**Auto-Discovery:** The system uses glob patterns (`day_earth*.jpg`) to find all variants. You can add more variants by simply adding numbered files - no code changes needed.

---

## Image Categories

### Zone-Based Day Images
**Purpose:** Journey view background images that change based on distance
**Naming Pattern:** `day_{zone}_{NN}.jpg` where NN is 01, 02, 03, etc.
**Zones:** earth, inner, belt, outer, uranus (5 zones)
**Auto-Discovery:** System finds all matching files via glob pattern and randomly selects

**Zones:**
- **Earth Zone** (0-10% progress) - `day_earth_*.jpg`
- **Inner Zone** (10-35% progress) - `day_inner_*.jpg`
- **Belt Zone** (35-55% progress) - `day_belt_*.jpg`
- **Outer Zone** (55-80% progress) - `day_outer_*.jpg`
- **Uranus Zone** (80-100% progress) - `day_uranus_*.jpg`

**Current counts per aesthetic:** See Aesthetic Inventory table above

---

### Zone-Based Status Images
**Purpose:** Status view background images that change based on distance
**Naming Pattern:** `status_{zone}_{NN}.jpg`
**Auto-Discovery:** System finds all matching files via glob pattern and randomly selects

**Zones:**
- **Earth Zone** (0-10% progress) - `status_earth_*.jpg`
- **Inner Zone** (10-35% progress) - `status_inner_*.jpg`
- **Belt Zone** (35-55% progress) - `status_belt_*.jpg`
- **Outer Zone** (55-80% progress) - `status_outer_*.jpg`
- **Uranus Zone** (80-100% progress) - `status_uranus_*.jpg`

**Current counts per aesthetic:** See Aesthetic Inventory table above

---

### Waypoint Images
**Purpose:** Specific location images when approaching or docked at waypoints
**Naming Pattern:** `Waypoint-{Location}.jpg`

**Waypoint Locations:**
- `Waypoint-Earth_Launch.jpg` - Earth Orbit
- `Waypoint-Moon.jpg` - Moon
- `Waypoint-Mars-Flyby.jpg` - Mars Base
- `Waypoint-Ceres_Station.jpg` - Ceres Asteroid Station
- `Waypoint-Jupiter_Slingshot.jpg` - Jupiter Gas Mine
- `Waypoint-Saturn_Rings.jpg` - Saturn Outpost
- `Waypoint-Deep_Space_69.jpg` - Deep Space 69
- `Waypoint-Uranus_Arrival.jpg` - Uranus Arrival

**Fallback:** If waypoint image missing, falls back to zone-based image

**Current counts per aesthetic:** See Aesthetic Inventory table above

---

### Milestone Images
**Purpose:** Special celebration images at 25%, 50%, 75% progress
**Naming Pattern:** `milestone-{percent}.jpg`

**Milestones:**
- `milestone-25.jpg` - 25% progress celebration
- `milestone-50.jpg` - 50% progress celebration
- `milestone-75.jpg` - 75% progress celebration

**Current counts per aesthetic:** All aesthetics have 3 milestone images

---

### Event Images
**Purpose:** Random event backgrounds
**Naming Pattern:** `Event-{NN}.jpg`
**Selection:** Random from available event images

**Event Images:**
- `Event-01.jpg`
- `Event-02.jpg`
- `Event-03.jpg` (optional, adds variety)
- `Event-04.jpg` (optional, adds variety)

**Current counts per aesthetic:** See Aesthetic Inventory table above

---

### Post-Event Outcome Images
**Purpose:** Outcome-specific visuals for post-event screen (success vs failure)
**Naming Pattern:** `post_event_{outcome}_{NN}.jpg`
**Selection:** Random from appropriate outcome set (success or failure)

**Success Variants:**
- `post_event_success_01.jpg` - Crisis resolved, relief
- `post_event_success_02.jpg` - Successful repair, competence
- `post_event_success_03.jpg` - Resource gain, satisfaction

**Failure Variants:**
- `post_event_failure_01.jpg` - Damage taken, concern
- `post_event_failure_02.jpg` - Resource loss, depletion
- `post_event_failure_03.jpg` - Recovery effort, determination

**Fallback:** If post-event images missing, no image shown (graceful degradation)

**Current Status:**
- ✅ **main**, **simpsons** - Have all 6 post-event images
- ❌ **rick**, **classic**, **pixel** - Missing all 6 post-event images

---

### End-Game Images
**Purpose:** Game over and victory screens
**Naming Pattern:** `gameover.jpg`, `victory.jpg`

**End-Game Images:**
- `gameover.jpg` - Death/failure screen
- `victory.jpg` - Victory screen (reaching Uranus)

**Current counts per aesthetic:** All aesthetics have 2 end-game images

---

### Utility Images
**Purpose:** Title, setup, launch, and fallback images
**Naming Pattern:** Static names

**Utility Images:**
- `Title-01.jpg` - Main title/menu screen
- `NewGame-01.jpg` - New game setup screen
- `Launch-01.jpg` - Launch sequence dramatic moment
- `Continue-01.jpg` - Shop fallback image

**Current counts per aesthetic:** Varies (main and simpsons have 4, others have 2)

---

## File Structure

```
media/
└── main/                        (aesthetic variant: main)
    ├── day_earth_01.jpg         ✅
    ├── day_earth_02.jpg         ✅
    ├── day_earth_03.jpg         ✅
    ├── day_inner_01.jpg         ✅
    ├── day_inner_02.jpg         ✅
    ├── day_inner_03.jpg         ✅
    ├── day_belt_01.jpg          ✅
    ├── day_belt_02.jpg          ✅
    ├── day_belt_03.jpg          ✅
    ├── day_outer_01.jpg         ✅
    ├── day_outer_02.jpg         ✅
    ├── day_outer_03.jpg         ✅
    ├── day_uranus_01.jpg        ✅
    ├── day_uranus_02.jpg        ✅
    ├── day_uranus_03.jpg        ✅
    ├── status_earth_01.jpg      ✅
    ├── status_earth_02.jpg      ✅
    ├── status_earth_03.jpg      ✅
    ├── status_inner_01.jpg      ✅
    ├── status_inner_02.jpg      ✅
    ├── status_inner_03.jpg      ✅
    ├── status_belt_01.jpg       ✅
    ├── status_belt_02.jpg       ✅
    ├── status_belt_03.jpg       ✅
    ├── status_outer_01.jpg      ✅
    ├── status_outer_02.jpg      ✅
    ├── status_outer_03.jpg      ✅
    ├── status_uranus_01.jpg     ✅
    ├── status_uranus_02.jpg     ✅
    ├── status_uranus_03.jpg     ✅
    ├── Waypoint-Earth_Launch.jpg    ✅
    ├── Waypoint-Moon.jpg             ✅
    ├── Waypoint-Mars-Flyby.jpg       ✅
    ├── Waypoint-Ceres_Station.jpg    ✅
    ├── Waypoint-Jupiter_Slingshot.jpg ✅
    ├── Waypoint-Saturn_Rings.jpg     ✅
    ├── milestone-25.jpg         ✅
    ├── milestone-50.jpg         ✅
    ├── milestone-75.jpg         ✅
    ├── Event-01.jpg             ✅
    ├── Event-02.jpg             ✅
    ├── post_event_success_01.jpg  ✅ (main, simpsons) / ❌ (rick, classic, pixel)
    ├── post_event_success_02.jpg  ✅ (main, simpsons) / ❌ (rick, classic, pixel)
    ├── post_event_success_03.jpg  ✅ (main, simpsons) / ❌ (rick, classic, pixel)
    ├── post_event_failure_01.jpg  ✅ (main, simpsons) / ❌ (rick, classic, pixel)
    ├── post_event_failure_02.jpg  ✅ (main, simpsons) / ❌ (rick, classic, pixel)
    ├── post_event_failure_03.jpg  ✅ (main, simpsons) / ❌ (rick, classic, pixel)
    ├── gameover.jpg             ✅
    ├── victory.jpg              ✅
    ├── Title-01.jpg             ✅
    └── Continue-01.jpg          ✅
```

---

## Missing Images Across Aesthetics

This section identifies what's missing compared to the prompts JSON (which defines 65 possible images). The system gracefully handles missing images with fallbacks.

### Post-Event Images (Missing in 3 aesthetics)

**Missing from: rick, classic, pixel**

- `post_event_success_01.jpg`
- `post_event_success_02.jpg`
- `post_event_success_03.jpg`
- `post_event_failure_01.jpg`
- `post_event_failure_02.jpg`
- `post_event_failure_03.jpg`

**Impact:** Post-event view shows no image (graceful fallback)

### Waypoint Images (Missing from some aesthetics)

- `Waypoint-Deep_Space_69.jpg` - Missing from classic, pixel, rick (main and simpsons have it)
- `Waypoint-Uranus_Arrival.jpg` - Missing from classic, pixel (main, simpsons, rick have it)

**Impact:** Falls back to zone-based images

### Event Images (Fewer variants in some aesthetics)

- `Event-03.jpg` and `Event-04.jpg` - Present in main and simpsons; missing from rick, classic, pixel

**Impact:** Less variety in event screens (still functional with 2 variants)

### Zone Variants (Variable counts)

Zone image counts vary by aesthetic. The system auto-discovers whatever exists - no "correct" number.

### Utility Images (Missing from all aesthetics)

**New images required for Launch Sequence feature:**
- `NewGame-01.jpg` - New game setup screen (replaces Title-01.jpg usage)
- `Launch-01.jpg` - Launch sequence dramatic moment

**Impact:** These images are required for the new Launch Sequence View feature. Without them, the new flow will not display images.

---

## Image Selection Logic

**Complete documentation:** See [`IMAGE_SYSTEM.md`](IMAGE_SYSTEM.md)

### Journey View (`'day'` context)
**Priority system:**
1. **Milestone images** (if milestone just reached)
2. **Waypoint images** (if at specific waypoint)
3. **Zone-based day images** (fallback, random variant from current zone)

### Status View (`'status'` context)
**Zone-based selection:**
- Random variant from current zone's status images

### Station Choice View (`'waypoint'` context)
**Waypoint-specific:**
- Shows waypoint image or zone fallback

### Waypoint View (`'station'` context)
**Location-specific:**
- Shows station image or zone fallback

### Shop View (`'shop'` context)
**Station-based:**
- Shows station waypoint image or `Continue-01.jpg` fallback

### Event View (`'event'` context)
**Random selection:**
- Random choice from available event images (Event-01.jpg, Event-02.jpg, etc.)

### Post-Event View (`'post_event'` context)
**Outcome-based selection:**
- If success: Random from `post_event_success_01-03.jpg` (3 variants)
- If failure: Random from `post_event_failure_01-03.jpg` (3 variants)
- Determined by `recent_event_outcome` in session state

---

## Technical Specifications

### File Format
- **Extension:** `.jpg` (all images)
- **Resolution:** Optimized for Telegram (typically 512x512 or 1024x1024)
- **Color Depth:** 24-bit RGB
- **File Size:** Optimized for web delivery (typically 400-650 KB per image)

### Naming Conventions
- **Zone images:** `{context}_{zone}_{variant}.jpg`
- **Waypoint images:** `Waypoint-{Location}.jpg`
- **Milestone images:** `milestone-{percent}.jpg`
- **Event images:** `Event-{number}.jpg`
- **Utility images:** `{Purpose}-{number}.jpg` or `{purpose}.jpg`

### Style Guidelines
- **Theme:** Space/sci-fi aesthetic
- **Color Palette:** Deep space blues, purples, cosmic colors
- **Style:** Photorealistic space imagery
- **Consistency:** Cohesive visual experience across all images

---

## Caching System

**File:** `data/image_cache.json`
**Handler:** `TelegramAPI.php` (automatic)

### How It Works
1. **First send:** Local file path → Upload to Telegram → Receive `file_id` → Cache `file_id`
2. **Subsequent sends:** Check cache → Use `file_id` (instant, no upload)

**Benefits:**
- ✅ Faster delivery after first send
- ✅ Reduced bandwidth (upload once, reference forever)
- ✅ Better user experience (instant message delivery)
- ✅ Automatic caching (no manual intervention needed)

---

## Zone System Details

**Handler:** `getDistanceZone()` in ViewManager.php:1310-1317

| Zone | Progress % | Distance Range | Region |
|------|-----------|----------------|--------|
| `earth` | 0-10% | 0-280M km | Earth departure / near-Earth space |
| `inner` | 10-35% | 280M-980M km | Inner solar system (Mars region) |
| `belt` | 35-55% | 980M-1,540M km | Asteroid belt / Jupiter region |
| `outer` | 55-80% | 1,540M-2,240M km | Saturn / mid-journey |
| `uranus` | 80-100% | 2,240M-2,800M km | Final approach to Uranus |

**Purpose:** Creates visual progression through the solar system as players travel toward Uranus.

---

## Implementation Status

### Code Integration
✅ **ViewManager.php** - Image selection logic (`getContextImageUrl()`, `selectRandomZoneImage()`)
✅ **TelegramAPI.php** - Automatic file_id caching system
✅ **All views** - Integrated with contextual image system

### Testing Status
✅ **Image loading** - All 45 images load correctly
✅ **Zone transitions** - Images change appropriately as player progresses
✅ **Variant rotation** - Random selection from 3 variants per zone works
✅ **Waypoint images** - Specific location images display correctly
✅ **Milestone images** - Celebration images trigger at correct progress points
✅ **Caching system** - file_id caching works, no re-uploads after first send
✅ **Fallback system** - Graceful degradation when specific images missing

---

## Status by Aesthetic

### Main (97 images)
- **Zone-based:** 70 images (35 day + 35 status)
- **Waypoint:** 10
- **Milestone:** 3
- **Event:** 4
- **Post-event:** 6
- **End-game:** 2
- **Utility:** 2

### Simpsons (66 images)
- **Zone-based:** 40 images (20 day + 20 status)
- **Waypoint:** 9
- **Milestone:** 3
- **Event:** 4
- **Post-event:** 6
- **End-game:** 2
- **Utility:** 2

### Rick (68 images)
- **Zone-based:** 52 images (30 day + 22 status, uneven distribution)
- **Waypoint:** 7
- **Milestone:** 3
- **Event:** 2
- **Post-event:** 0 (missing)
- **End-game:** 2
- **Utility:** 2

### Classic (45 images)
- **Zone-based:** 30 images (15 day + 15 status)
- **Waypoint:** 6
- **Milestone:** 3
- **Event:** 2
- **Post-event:** 0 (missing)
- **End-game:** 2
- **Utility:** 2

### Pixel (45 images)
- **Zone-based:** 30 images (15 day + 15 status)
- **Waypoint:** 6
- **Milestone:** 3
- **Event:** 2
- **Post-event:** 0 (missing)
- **End-game:** 2
- **Utility:** 2

**Note:** All aesthetics are fully functional. Missing images gracefully fall back.

---

## Adding More Images

The system uses **auto-discovery** - just add files following the naming patterns and they'll be automatically found and used:

### Adding Zone Variants
Add files matching the pattern: `day_{zone}_{NN}.jpg` or `status_{zone}_{NN}.jpg`

**Example:** To add a 4th earth zone variant, create `day_earth_04.jpg` - no code changes needed

### Adding Event Images
Add files matching the pattern: `Event-{NN}.jpg`

**Example:** Add `Event-05.jpg` and update event array in ViewManager.php

### Adding Waypoint Images
Add files matching the pattern: `Waypoint-{Location}.jpg` and update the waypoint mapping in ViewManager.php

### Adding New Aesthetics
Create a new directory under `media/{aesthetic_name}/` with the same file structure

---

**Last Verified Against Media Directory:** 2026-03-01
**Current state: 5 aesthetics with varying image counts. All functional with graceful fallbacks for missing images.**

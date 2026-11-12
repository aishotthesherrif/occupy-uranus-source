# Image Generation Instructions

**Last Updated:** 2026-03-01
**Purpose:** Guide for creating a complete aesthetic variant for Occupy Uranus — 65 image prompts delivered as an `img_{aesthetic}.json` file ready for image generation.

---

## Overview

Creating a new aesthetic is a creative reinterpretation of the game's 65 scenes through a specific visual and tonal lens. The process has two phases:

1. **Write the prompt file** — an `img_{aesthetic}.json` containing 65 image prompts, each creatively reimagined through your aesthetic. This document and `data/image_generation_prompts.json` (the scene bible) guide that work.
2. **Generate the images** — feed the prompt file to your image generation tool, post-process, and install into the game.

This is not a mechanical style-prefix job. The scene bible gives you the generic bones of each scene — what's in it, what mood it serves, where it sits in the journey. Your job is to completely reimagine every scene through the aesthetic's creative lens: its world-building, its humor, its visual language, its recurring motifs. The output should feel like a coherent creative vision, not a search-and-replace.

### Required References

| File | Role |
|------|------|
| **This document** | Creative workflow, output schema, prompt-writing guidance |
| **`data/image_generation_prompts.json`** | Scene bible — generic descriptions, moods, zone context, "out the window" vistas for all 65 images |
| **`docs/IMAGE_SYSTEM.md`** | Technical authority — how the game engine selects and displays images (contexts, zones, selection priority, auto-discovery). Read this to understand *why* each image exists. |

---

## The Output: `img_{aesthetic}.json`

Your deliverable is a single JSON file. Here is the schema:

```json
{
  "metadata": {
    "project_name": "aesthetic_id",
    "keywords": "Short keyword phrase for the style",
    "style": "Full creative brief — see below",
    "quality": "Target visual quality descriptor",
    "base_palette": [
      { "hex": "#HEXCODE", "description": "What this color represents in the aesthetic" }
    ]
  },
  "images": [
    {
      "filename": "Title-01.png",
      "prompt": "Complete, self-contained image generation prompt.",
      "text_overlay": "OCCUPY URANUS"
    },
    {
      "filename": "gameover.png",
      "prompt": "Complete prompt — no text_overlay field when not needed."
    }
  ]
}
```

### Schema Details

**`metadata.project_name`** — The aesthetic's ID (used as directory name under `media/`).

**`metadata.keywords`** — A short phrase identifying the style, used as a reference tag (e.g., "Simpsons-style", "Studio Ghibli watercolor").

**`metadata.style`** — The aesthetic's full creative brief. This is not a one-line prefix — it's a rich paragraph (or more) covering the visual language, world-building premise, humor style, recurring motifs, rendering approach, and any technical art direction notes. This field is the DNA of every prompt in the file. Someone reading only this field should understand the entire aesthetic vision. See "Define Your Aesthetic" below.

**`metadata.quality`** — Target rendering quality (e.g., "NTSC/PAL" for a cartoon, "4K cinematic" for photorealistic, "16-bit pixel art" for retro).

**`metadata.base_palette`** — The aesthetic's core colors with semantic descriptions. Not per-image — these are the colors that define the aesthetic's identity across all 65 images.

**`images[].filename`** — Must match the game's expected filenames exactly (see Complete Image Manifest below). Choose `.png` or `.jpg` and be consistent within your aesthetic.

**`images[].prompt`** — A single, rich, self-contained prompt. Everything an image generator needs is in this one field. No external references required. See "Writing Prompts" below.

**`images[].text_overlay`** — Only present on images that need baked-in text: `Title-01` ("OCCUPY URANUS") and milestones ("25%", "50%", "75%"). Omit the field entirely otherwise.

---

## Creative Process

### Step 1: Define Your Aesthetic

Before writing any prompts, develop the aesthetic's creative identity. This becomes your `metadata.style` field and guides every decision.

**World-building premise** — The game is an Oregon Trail parody: hauling cargo across the solar system to colonize Uranus. Your aesthetic reinterprets *who* is making this journey and *why it's funny*. The Simpsons aesthetic imagines Springfield's most incompetent family piloting a ship held together with duct tape and optimism, with Duff Beer sponsorships on everything. What's your version?

**Visual language** — Not just "cartoon" or "anime" but the specific rendering: line weight, shading model, color saturation, how you handle detail, what reads clearly at Telegram's mobile image sizes. If referencing a specific show or game, name it and describe what makes its visuals distinctive.

**The spacecraft** — It appears in most of the 65 images. Give it a name, a visual identity, and personality. Recurring exterior details (paint job, damage accumulation, decals, modifications) create continuity across the set.

**The cockpit** — 20 of the 65 images are ship interior/dashboard shots (the `status_` context). Design the cockpit as a recurring set: instruments, personal items, running gags, and details that evolve across zones. Zone-specific lighting comes through the viewport.

**Recurring motifs** — Objects, gags, or details that appear across multiple images and create continuity. These are the connective tissue of your aesthetic. The Simpsons has the donut (fuel gauge, food supply, emotional anchor), fuzzy dice, family photo, bumper stickers, the "Employee of the Month (By Default)" plaque. Your aesthetic needs its own set.

**Humor style** — How does your aesthetic handle comedy? Sight gags? Signage and readout jokes? Absurdist juxtaposition? Dry understatement? This determines how you'll reinterpret events, status screens, and waypoint locations.

### Step 2: Read the Scene Bible

Open `data/image_generation_prompts.json`. Each of the 65 entries provides:

- **`content_description`** — What's in the scene (generic, aesthetic-agnostic)
- **`mood`** — The emotional target
- **`out_the_window`** — What's visible outside the ship (zones, waypoints, events only — null for interiors and special images)
- **`style`** — The scene's general character
- **`notes`** — Context about when/why this image appears in the game
- **`color_palette`** — Suggested generic hex colors (reinterpret freely)
- **`zone`** / **`waypoint_name`** / **`category`** — Technical placement info
- **`interior_focus`** — For status and post-event images: what the interior scene should emphasize

These fields are creative ingredients, not copy-paste sources. Your job is to take "spacecraft navigating asteroid field" and turn it into something fully realized in your aesthetic's voice.

### Step 3: Understand Image Contexts

Read `docs/IMAGE_SYSTEM.md` for full technical details. The critical creative distinctions:

**Exterior scenes** — the view outside the ship:

- **Day images** (`day_{zone}_NN`) — Travel vistas. The scenic exterior view during the journey. The spacecraft in the scene against the zone's celestial backdrop. Each zone has multiple variants for visual variety (the game randomly picks one each time).
- **Waypoint images** (`Waypoint-{name}`) — Specific location establishing shots. The ship arriving at or docked at a known landmark.
- **Event images** (`Event-NN`) — "Something is happening" moments. Danger, mystery, or discovery visible outside. Must be generic enough to work for various event types.
- **Milestone images** (`milestone-NN`) — Celebration shots at 25%, 50%, 75% progress. Exterior with the relevant region visible and some achievement marker.

**Interior scenes** — inside the ship:

- **Status images** (`status_{zone}_NN`) — Cockpit/dashboard interiors. The zone's light and color comes through the viewport, but the focus is instruments, personal items, and the lived-in interior. This is the "chad checking the dashboard" view. Each zone has multiple variants.
- **Post-event images** (`post_event_{outcome}_NN`) — Cockpit interiors showing the aftermath of a resolved event. Success variants show different flavors of relief/victory. Failure variants show different kinds of setback/damage. No specific exterior vista — focus is the chad's world as reflected in the cockpit environment.

**Special/static scenes:**

- **Title** (`Title-01`) — Main menu. The aesthetic's first impression. Sets the entire tone.
- **Game Over** (`gameover`) — Mission failed. Emotional but dignified.
- **Victory** (`victory`) — Mission complete. The payoff for the whole journey.
- **Continue** (`Continue-01`) — Generic waypoint/shop fallback. Neutral, functional, but still in the aesthetic's voice.

### Step 4: Write the Prompts

Work through the 65 images. For each one:

1. Read its scene bible entry
2. Reimagine the scene through your aesthetic
3. Write a self-contained prompt with everything an image generator needs
4. Include aesthetic-specific details, recurring motifs, and world-building
5. Make each variant meaningfully distinct from its siblings

**Zone color shifts matter.** The Simpsons aesthetic uses zone-specific accent colors that tint the cockpit through the viewport: Earth blues/greens → inner reds/oranges → belt greys → outer ambers/golds → Uranus teals. Your aesthetic should have its own zone color progression that creates visual variety across the status and day images.

---

## Writing Prompts

Each prompt should contain these elements woven naturally together (not as labeled sections, but as a flowing creative brief):

1. **Framing hook** — A short phrase establishing the image's role and energy (e.g., "couch gag title card," "cockpit in the belt," "void interior")
2. **Art direction** — Rendering notes specific to this image
3. **Atmosphere** — Color/lighting environment, palette shifts from the base, zone-specific tinting
4. **Mood** — Emotional tone interpreted through the aesthetic's sensibility
5. **Scene description** — The main content, fully reimagined. Generic spacecraft becomes *your* spacecraft with *your* details
6. **Note** — A closing line explaining why specific creative choices matter for this image's game role

The prompt reads like a creative brief to an artist who has never seen the game. It should be completely self-contained.

---

## Complete Image Manifest

All 65 filenames the game expects (extension is your choice — be consistent):

### Special (4 images)
`Title-01`, `gameover`, `victory`, `Continue-01`

### Events (4 images)
`Event-01`, `Event-02`, `Event-03`, `Event-04`

### Milestones (3 images)
`milestone-25`, `milestone-50`, `milestone-75`

### Waypoints (8 images)
`Waypoint-Earth_Launch`, `Waypoint-Moon`, `Waypoint-Mars-Flyby`, `Waypoint-Ceres_Station`, `Waypoint-Jupiter_Slingshot`, `Waypoint-Saturn_Rings`, `Waypoint-Deep_Space_69`, `Waypoint-Uranus_Arrival`

### Day — Zone Exteriors (20 images)
`day_earth_01` through `day_earth_04`
`day_inner_01` through `day_inner_04`
`day_belt_01` through `day_belt_04`
`day_outer_01` through `day_outer_04`
`day_uranus_01` through `day_uranus_04`

### Status — Zone Interiors (20 images)
`status_earth_01` through `status_earth_04`
`status_inner_01` through `status_inner_04`
`status_belt_01` through `status_belt_04`
`status_outer_01` through `status_outer_04`
`status_uranus_01` through `status_uranus_04`

### Post-Event Outcomes (6 images)
`post_event_success_01`, `post_event_success_02`, `post_event_success_03`
`post_event_failure_01`, `post_event_failure_02`, `post_event_failure_03`

**Note on variant counts:** The game auto-discovers zone variants by glob pattern (`day_earth*.{jpg,png}`). The 65-image set with 4 variants per zone is the standard full set, but you could produce more. The system will find and randomly select from whatever's in the directory.

---

## Generation Order

Work in this sequence to build consistency outward from the core identity.

### Phase 1: Identity (4 images, ~20 min)
Establish the visual identity with the highest-impact images.
1. `Title-01` — First impression, sets the entire tone
2. `gameover` — Emotional low
3. `victory` — Emotional high
4. `Continue-01` — Neutral baseline

### Phase 2: Waypoints (8 images, ~1 hour)
Define the journey's landmark locations.
5–12. All waypoints in journey order (Earth Launch → Uranus Arrival)

### Phase 3: Events + Milestones (7 images, ~45 min)
Define danger, mystery, and celebration.
13–16. Events 01–04
17–19. Milestones 25, 50, 75

### Phase 4: Day Exteriors (20 images, ~1.5 hours)
The travel vistas, zone by zone. Work through all variants of one zone before moving to the next.
20–23. `day_earth` variants
24–27. `day_inner` variants
28–31. `day_belt` variants
32–35. `day_outer` variants
36–39. `day_uranus` variants

### Phase 5: Status Interiors (20 images, ~1.5 hours)
The cockpit/dashboard views, zone by zone. This is where the ship's interior character develops.
40–43. `status_earth` variants
44–47. `status_inner` variants
48–51. `status_belt` variants
52–55. `status_outer` variants
56–59. `status_uranus` variants

### Phase 6: Post-Event Outcomes (6 images, ~30 min)
Interior aftermath scenes.
60–62. `post_event_success` 01–03
63–65. `post_event_failure` 01–03

---

## Guidance by Image Type

### Day Images (Exterior Travel Vistas)

These are the scenic views players see most often. Each zone needs visual variety across its variants — vary composition, distance, featured celestial objects, lighting, and what the ship is doing.

Tell a mini-story of progression within each zone. For example in the Earth zone: variant 01 might show Earth filling the frame (just left), variant 02 shows Earth and Moon together (pulling away), variant 03 shows Earth small and distant (committed to the journey), variant 04 offers a unique perspective (nightside, aurora, etc.).

The ship should be in the scene as an exterior element. Zone-appropriate celestial objects should be present per the scene bible's `out_the_window` field.

### Status Images (Interior Cockpit/Dashboard)

These are the second most frequent images. They all share a common setting — the ship's interior — but the zone's light comes through the viewport and tints everything.

Design a cockpit that's a character in itself. Instruments, personal items, notes, running gags, and accumulated journey details. The interior should visually evolve across zones: early zones feel fresh and well-stocked, later zones show wear, depletion, and the accumulation of journey artifacts.

Each variant within a zone should be meaningfully distinct — different instrument readings in focus, different personal details highlighted, different emotional emphasis. Some aesthetics may choose to show different rooms of the ship for variety — that's a creative option, not a requirement.

### Waypoint Images (Location Establishing Shots)

Each waypoint is a specific, named location. These should feel like the most "designed" images — clear establishing shots of recognizable places, reinterpreted through your aesthetic. The ship should be present (docked, approaching, or in frame).

Each waypoint has a distinct character: Earth (home, familiar), Moon (first stop, still close), Mars (frontier outpost), Ceres (industrial), Jupiter (massive scale, awe), Saturn (beauty, rings), Deep Space 69 (void, isolation), Uranus Arrival (destination, triumph).

### Event Images (Danger/Mystery/Discovery)

These must be generic enough to work for various random event types, but vivid enough to convey "something is happening." The four variants cover different threat/discovery flavors:

- **Event-01** — Physical danger (asteroids, debris, collision threat)
- **Event-02** — Cosmic mystery (anomaly, unknown signal, strange phenomenon)
- **Event-03** — Natural force (solar storm, radiation, energy event)
- **Event-04** — Discovery (derelict, artifact, abandoned structure)

### Post-Event Images (Interior Aftermath)

All six are cockpit interior scenes — no exterior vista, just the chad's world after a crisis is resolved.

Each success variant should depict a different flavor of good outcome: crisis resolved through action (01), systems successfully repaired (02), resources secured or restocked (03).

Each failure variant should depict a different kind of setback: damage taken and systems compromised (01), resources lost and supplies depleted (02), regrouping after failure with determination to continue (03).

### Milestone Images (Progress Celebrations)

Exterior shots at specific journey points with a prominent progress marker (25%, 50%, 75%). The relevant region should be visible: Mars area for 25%, belt/Jupiter for 50%, Saturn region for 75%. These should feel celebratory — the one moment the game pauses to acknowledge progress.

### Special Images

**Title-01** — The main menu screen. This is the aesthetic's movie poster. Make it memorable, inviting, and tonally definitive.

**gameover** — Mission failed. Emotional but dignified — "end of mission," not horror. The ship disabled or lost, conveying finality with respect.

**victory** — Mission complete. The triumphant payoff. Colony deploying, ship landed, the full emotional climax of a successful journey.

**Continue-01** — Generic waypoint/shop fallback. A neutral space station or supply depot. Should work as background for any shop interaction. Functional but still in the aesthetic's voice.

---

## After Writing: Image Generation

Once your `img_{aesthetic}.json` is complete, generate the actual images.

### Recommended Tools
- **Midjourney** (v6+) — Best for artistic and stylized aesthetics
- **DALL-E 3** — Good for consistent style across a set
- **Stable Diffusion** (SDXL) — Best for custom/fine-tuned models
- **Leonardo.ai** — Good for game asset generation with consistency controls

### Technical Requirements
- **Resolution:** 1024×1024 preferred (512×512 minimum)
- **Format:** JPG or PNG (match your filename extensions)
- **File size:** 300–700 KB per image (optimize for Telegram delivery)
- **Total set:** ~25–40 MB for 65 images

### Consistency Tips
- Generate in zone batches (all earth variants together, etc.)
- Use style reference features (Midjourney `--sref`, etc.) to maintain coherence
- Generate the Title-01 first and use it as the style anchor
- If using Stable Diffusion, keep the same checkpoint for the entire set

### Post-Processing
```bash
# Resize if needed
mogrify -resize 1024x1024 -quality 85 *.jpg

# Convert PNG to JPG if needed
mogrify -format jpg -quality 85 *.png

# Optimize file size
jpegoptim --max=700 *.jpg

# Strip metadata
mogrify -strip *.jpg
```

### Font Recommendations (for Title-01 text overlay)
- **Orbitron** — Geometric sci-fi
- **Exo** — Modern tech
- **Nasalization** — Retro NASA-style
- **Audiowide** — Futuristic display

If your image generator struggles with text, generate without it and add "OCCUPY URANUS" in post-processing.

---

## Installation

### Add to Game

```bash
# Create aesthetic directory
mkdir -p media/{aesthetic_name}/

# Copy all images
cp *.jpg media/{aesthetic_name}/

# Verify count
ls media/{aesthetic_name}/*.jpg | wc -l
# Should output: 65
```

### Update aesthetics.json

Add an entry to `data/aesthetics.json`:

```json
{
  "id": "aesthetic_id",
  "name": "Display Name",
  "description": "Short description for selection UI",
  "icon": "🎨",
  "path": "aesthetic_id"
}
```

### Test

- Verify all 65 images load correctly
- Check images look good on mobile (Telegram is the primary platform)
- Test every context: title, journey, status, events, post-events, waypoints, milestones, game over, victory
- Confirm consistent style across the full set
- Check file sizes are reasonable for fast Telegram delivery

---

## Completion Checklist

- [ ] `img_{aesthetic}.json` contains exactly 65 image entries
- [ ] All filenames match the game's expected names (case-sensitive)
- [ ] Every prompt is self-contained (no external references needed)
- [ ] `metadata.style` is a full creative brief (not a one-line prefix)
- [ ] `metadata.base_palette` defines the aesthetic's core colors
- [ ] `text_overlay` present only on Title-01 and milestones
- [ ] Status images are clearly interior/cockpit scenes
- [ ] Day images are clearly exterior travel vistas
- [ ] Post-event images are interior aftermath scenes (no exterior vista)
- [ ] Each variant is meaningfully distinct from its siblings
- [ ] Zone color progression is visible across the set
- [ ] Recurring motifs appear consistently throughout
- [ ] All 65 actual images generated and optimized
- [ ] Images placed in `media/{aesthetic_name}/`
- [ ] `aesthetics.json` updated
- [ ] Tested in game across all contexts

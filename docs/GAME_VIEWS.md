# Game Views Reference

**Last Updated:** 2026-03-02 (Added Who's Playing View)
**Last Verified Against Code:** 2026-03-02

Complete documentation of all views/screens and image system in Occupy Uranus bot.

**Recent Changes:**
- Added Who's Playing View - shows all active players and their progress (2026-03-02)
- Added `/frens` command to access Who's Playing view (2026-03-02)
- Added Launch Sequence View - dramatic launch moment between setup and gameplay (2026-03-01)
- Renamed "Launch View" to "New Game View" for clarity (2026-03-01)
- Added Return to Waypoint Confirmation View (2026-03-01)
- Resource displays standardized to 6 resources: Fuel, Food, Hull, Chad, Morale, Credits
- Post-Event View now shows RESOURCE CHANGES section with before/after comparison
- Base speed increases from 20M to 40M km/day after passing Jupiter waypoint (2026-03-01)
- Start View and Main Menu View now fully separated with distinct purposes
- Start View: Entry point for new/returning players (simplified, focused on getting started)
- Main Menu View: In-game navigation hub (comprehensive meta-game actions)
- Main Menu simplified: removed redundant "Journey" and "Map" buttons
- Main Menu now includes context-aware "Play in DMs / Play in Group" button
- "Help" renamed to "How To Play" for consistency
- Removed "Journey View (at waypoint)" - status now properly set to `'in_space'` when skipping waypoints

---

## 1a. START VIEW (Entry Point)
**Trigger:** `/game` or `/start` command
**Handler:** `handleStart()` in uranus_game_bot.php
**View Renderer:** `renderStartView()` in ViewManager

### Description:
Entry point for new and returning players. Shows different content based on session state.

### Conditionals:
- **Has active session:** Shows resume or abandon options only
- **No session:** Shows full start menu

### Keyboard (Active Session):
```
▶️ Resume Journey           [menu|continue]
🗑️ Abandon & Start New      [menu|abandon]
```

### Keyboard (No Session):
```
🚀 New Journey              [menu|startnewgame]
🏆 Leaderboard              [menu|leaderboard]
👥 Who's Playing            [menu|whosplaying]
💬 Play in DMs              [menu|privatedm]    (if in group)
👥 Play in Group            [menu|backtogroup]  (if in DM)
❓ How To Play              [menu|help]
```

**Note:** Aesthetic selection available in New Game View (pre-launch setup screen).

### Images:
- **No session:** Shows title image
- **Active session:** No image

---

## 1b. MAIN MENU VIEW (In-Game Navigation Hub)
**Trigger:** `menu|main` callback (e.g., from New Game View "Cancel" button or Journey View "Main Menu")
**Handler:** `handleMainMenu()` in uranus_game_bot.php
**View Renderer:** `renderMainMenuView()` in ViewManager

### Description:
Utility hub accessed during active gameplay. Provides meta-game actions (leaderboard, aesthetic, chat switching, help) and navigation back to gameplay. Requires active session.

### Conditionals:
- **Requires active session:** If no session, redirects to Start View
- **Chat context:** Shows "Play in DMs" if in group, "Play in Group" if in DM

### Keyboard:
```
▶️ Resume Journey                      [menu|continue]
🎨 Aesthetic                           [menu|aesthetic]
💬 Play in DMs (or 👥 Play in Group)   [menu|privatedm] [menu|backtogroup]
👥 Who's Playing                       [menu|whosplaying]
❓ How To Play                         [menu|help]
🗑️ Abandon & Start New                [menu|abandon]
```

**Note:** Resume Journey is navigation-only (shows Journey View without advancing time).

### Message Format:
```
🏠 MAIN MENU

📍 Current Status: <status>
📅 Day X | 🛸 Y million km

What would you like to do?
```

### Images:
- Shows title image with current aesthetic

---

## 2. NEW GAME VIEW (Pre-Launch Setup)
**Trigger:** New game start (after clicking "🚀 New Journey")
**Handler:** `handleNewGameView()` in uranus_game_bot.php
**Renderer:** `formatNewGameMessage()` and `getNewGameViewKeyboard()` in ViewManager
**Status:** `pre_launch`

### Description:
Pre-launch setup screen shown after creating a new game. Player can select their aesthetic, read help, and commit to starting. Click "Launch Ship" to proceed to launch sequence. Created with default (normal) difficulty.

**Note:** Difficulty selection was removed 2026-02-27. All games use normal difficulty.

### Message:
```
🚀 JOURNEY TO URANUS BEGINS!

Your Chad crew is assembled, ship is fueled, and supplies are loaded.
The 2.8 billion kilometer journey awaits!

Press Launch Ship to start traveling. Good luck, Captain! 🫡
```

### Keyboard:
```
🚀 Launch Ship          [action|launch]
🎨 Select Aesthetic     [menu|aesthetic]
❓ How to Play          [menu|help]
❌ Cancel               [menu|main]
```

### Images:
- `NewGame-01.jpg` (context: `'newgame'`)

**Note:** This is where players select their visual aesthetic before launching their journey.

---

## 2b. LAUNCH SEQUENCE VIEW (Dramatic Launch)
**Trigger:** Clicking "🚀 Launch Ship" from New Game View
**Handler:** `handleLaunch()` in uranus_game_bot.php
**Renderer:** `renderLaunchSequenceView()` in ViewManager
**Status:** `pre_launch`

### Description:
Dramatic launch moment view. Pure visual experience - shows launch image with single GO button. Point of commitment - clicking GO launches the ship and begins Day 1.

### Message:
```
(no text - image only)
```

### Keyboard:
```
🚀 GO                   [action|go]
```

### Images:
- `Launch-01.jpg` (context: `'launch'`)

**Note:** After clicking GO, status changes to `'in_space'` and the Journey View is shown (Day 1) with the launch message as its flash text. There is no platform choice: the same game can be opened in the Mini-App at any time (via the bot's menu button, which BotFather points at `app.html`).

---

## 3. SHOP VIEW (At Station)
**Trigger:** Arriving at station or "🛒 Shop" button
**Formatter:** `formatShop()` in ViewManager
**Keyboard:** `getShopKeyboard()` in ViewManager
**Status:** `'at_station'` (only accessible at stations)

### Conditionals:
- **status === 'at_station':** Shows "↩️ Back to Station" button
- **Otherwise:** Shows "⬅️ Back" button

### Keyboard:
```
⛽ Buy Fuel (+100)      [shop|fuel|100]
🍖 Buy Food (+50)       [shop|food|50]
🔧 Buy Repair (+20%)    [shop|repair|20]
↩️ Back to Station      [action|station]   (if at_station)
⬅️ Back                 [action|continue]   (otherwise)
```

---

## 4. WAYPOINT VIEW
**Trigger:** Arriving at a waypoint
**Formatter:** `formatWaypointView()` in GameEngine
**Keyboard:** `getWaypointKeyboard()` in ViewManager
**Status:** `'at_waypoint'` (decision phase)

### Description:
Stop/skip decision screen shown when crossing a waypoint. Player chooses whether to stop at the station or continue traveling with gravitational assist.

### Conditionals:
- **speed_multiplier > 1.0:** Shows "has boost" messaging
- **speed_multiplier === 1.0:** Shows "gain boost" messaging

### Keyboard:
```
🛒 Stop for Supplies           [station|stop|{stationId}]
➡️ Skip Waypoint               [station|skip|{stationId}]
```

---

## 4b. WAYPOINT SKIP CONFIRMATION VIEW
**Trigger:** After clicking "Skip Waypoint" button (`skip_waypoint` action → `waypoint_skipped` outcome)
**Renderer:** `renderWaypointSkippedView()` in ViewManager (view name `waypoint_skipped`)
**Formatter:** `formatWaypointSkipMessage()` in ViewManager
**Keyboard:** `getContinueKeyboard()` in ViewManager

### Description:
Brief confirmation message shown after skipping a waypoint, announcing gravitational assist gained/maintained. Single Continue button to proceed.

### Keyboard:
```
▶️ Continue                    [action|continue]
```

---

## 4c. STATION VIEW
**Trigger:** After stopping at a station (`status = 'at_station'`)
**Renderer:** `renderStationView()` in ViewManager
**Formatter:** `formatStationView()` in `bot/GameText.php`
**Keyboard:** `getStationKeyboard()` in ViewManager
**Status:** `'at_station'` (service phase)

### Description:
Main station interface shown when stopped at a waypoint. Displays local character story and provides access to shop and rest actions.

The story and description are chosen **once, when the ship docks** (`GameEngine::selectStationContent()`), stored in `session['at_waypoint']`, and read back here, so redrawing the screen shows the same story and the Mini-App shows the same one. When docking cost the player a gravitational assist, the view is prefixed with the "assist lost" notice (`speed_boost_lost` context).

### Display Format:
```
📍 [Station Name]

[Local Character Story]
"[Character Quote]"
— [Character Name], [Character Role]

📦 Resources:
⛽ Fuel: X units  |  🍖 Food: X units
🛡️ Hull: X%  |  😊 Morale: X%  |  💰 Credits: X
```

### Keyboard:
```
🛒 Shop | 😴 Rest
➡️ Continue Journey
🗺️ Map
🏠 Main Menu
```

---

## 5. JOURNEY VIEW (Day Result)
**Trigger:** "Continue" button or `/continue` command, or navigating back from other views
**Renderer:** `renderJourneyView()` in ViewManager
**Formatter:** `formatJourney()` in GameEngine

### Description:
Concise, action-focused view showing current day status with progress bar, velocity, resources with days remaining, and context-aware prompt. Replaces old Status-style format.

### Display Format:
```
🚀 DAY 42 — Journey to Uranus

💬 "Quote" — Chad Name (Role)

[Flash message if present]

[Waypoint banner if at waypoint]

Progress to Uranus: [████████████░░░░░░░░] 30%

🚀 Velocity: 20M km/day (1×) | 📏 Traveled: 840M km
🎯 Destination: 1,960M km (~98 days)
🛰️ Next Stop: Jupiter Gas Mine - 40M km (~2 days)

**Note:** After passing Jupiter (800M km), base velocity increases to 40M km/day automatically.

[Warning line if resources critical]
⛽ Fuel: 245 units (12 days)  |  🍖 Food: 180 units (12 days)
🛡️ Hull: 78%  |  👥 Chad: 5/5  |  😊 Morale: 65%  |  💰 Credits: 1500

[Context-aware prompt]
```

### Display Conditionals:
- **Daily chad quote:** Rotates through active chad: `(day - 1) % active_chad`
  - Uses contextual quote system with 8 contexts (priority order):
    1. post_event_success (after positive events, shows once)
    2. post_event_failure (after negative events, shows once)
    3. at_station (used by Station View, not Journey View)
    4. low_morale (< 30%)
    5. critical_resources (< 5 days fuel/food)
    6. near_victory (<= 20 days to Uranus)
    7. near_waypoint (<= 5 days to next station)
    8. general (default)
  - Format: `💬 "Quote" — Name (Role)`
- **Flash message:** Shows if `context['flash']` exists (event outcomes, action results)
- **Velocity:** Shows `(1×)` or `(⚡2×)` based on speed multiplier
- **Next Stop:** Shows next waypoint name and distance
- **Warning line:** Shows if any resource triggers warning threshold:
  - Fuel < 10 days remaining
  - Food < 10 days remaining
  - Hull < 30%
  - Morale < 30%

### Keyboard:
**Note:** Journey View is only shown when `status = 'in_space'`. When stopped at a station (`status = 'at_station'`), the Station View is shown instead. Shop is NEVER available in Journey View - only at stations.

```
▶️ Continue                   [action|continue]
📊 Status | 🗺️ Map            [action|status] [action|map]
🦌 Hunt Resources             [action|hunt]
↩️ Return to Last Waypoint | 🏠 Main Menu
  [action|return] [menu|main]
👥 Who's Playing              [menu|whosplaying]
```

**Note:** Approximately 12-17 lines vs previous 25-30 lines (~50% reduction)

---

## 6. STATUS VIEW
**Trigger:** "Status" button
**Renderer:** `renderStatusView()` in ViewManager
**Formatter:** `formatDetailedStatus()` in GameEngine

### Description:
Detailed "Chad Dashboard" view showing full mission overview, individual chad with contextual personality quotes, and ship/supplies status. Quotes respond to game state using 8 contexts: post_event_success, post_event_failure, at_station, low_morale, critical_resources, near_victory, near_waypoint, general.

### Display Format:
```
📊 DETAILED STATUS — Day 42 of the Journey

═══════════════════════════
MISSION OVERVIEW
═══════════════════════════
Progress %, speed with multiplier, ETA

[Gravitational assist indicator if active]

═══════════════════════════
CREW STATUS (X/5 active)
═══════════════════════════
Morale % with description

Individual chads:
👨‍✈️ Name (Role) — Trait
   💬 "Contextual quote"
   📍 Current activity

═══════════════════════════
SHIP & SUPPLIES
═══════════════════════════
Hull, fuel, food, credits with status descriptions
Difficulty level
```

### Display Conditionals:
- **Gravitational Assist:** Shows if `speed_multiplier > 1.0`
- **Morale Description:** Changes based on morale level (5 tiers)
- **Hull Description:** Changes based on hull level (5 tiers)
- **Chad Quotes:** Context-aware based on game state:
  - `low_morale` - Morale < 30%
  - `critical_resources` - Fuel or food < 5 days
  - `near_victory` - Within 20 days of Uranus
  - `near_waypoint` - Within 5 days of next station
  - `general` - Default
- **Quote Rotation:** Hash-based rotation per day/chad/trait
- **Lost Chad:** Shows "❌ Lost" instead of quote/activity

### Keyboard:
```
🗺️ Map | 🏠 Back to Journey  [action|map] [action|journey]
```

**Note:** Approximately 25-35 lines depending on chad count and active chad

---

## 7. MAP VIEW
**Trigger:** "Map" button
**Formatter:** `generateMap()` in GameEngine (lines 1577+)
**Keyboard:** `getMapKeyboard()` in ViewManager

### Description:
Cosmic void-themed navigation map showing solar system traverse as a tree structure. Uses space aesthetic with stars, dots, and tree branches. Width constrained to 28 characters for safe Telegram display.

### Display Format:
```
  ･  ✹  ･    ･  ✹  ･
     ･   ✹  ･    ･
 ✹      ･       ✹

 🛰️ DEEP SPACE NAV 🛰️

  ･  ✹  ･    ･  ✹  ･

⟬ MISSION STATUS ⟭

Day 50 ┃ 1000M ┃ 36%
Status: Deep Space / At [Station]

     *  ･  *  ･  *

⟬ SOLAR TRAVERSE ⟭

☉ Sol
 ┃
 ├─✓ 🌍 Earth ····· 0M
 ┃    Start
 ┃
 [... waypoints tree ...]
 ┃
 ├─→ 🚀 YOU ···· 1000M
 ┃   ≫ Jupiter → Saturn
 ┃
 [... upcoming waypoints ...]

     *  ･  *  ･  *

  ･  ✹  ･    ･  ✹  ･
```

### Display Conditionals:
- **Status:** Shows "Deep Space" if in transit, "At [Station Name]" if docked
- **Passed waypoints:** Marked with `✓` symbol and contextual labels
- **Current position:** Marked with `→ 🚀 YOU`
  - If at waypoint: Shows "Docked at [Name]"
  - If in transit: Shows "[From] → [To]"
- **Next waypoint:** Shows ETA in days and resource requirements (fuel/food)
- **Upcoming waypoints:** Marked with `○` symbol
- **Waypoint labels:** Contextual (Hub, Outpost, Refuel, Station, Void, End)

### Design Notes:
- Width: 28 characters (reduced from 30 for safety)
- Uses tree structure (`┃ ├─ └─`) to show progression
- Waypoint names shortened for compact display
- Distance shown in transit with `⋮` connectors
- Star decorations (`･ ✹ *`) frame the display

### Keyboard:
```
📊 Status | 🏠 Back to Journey  [action|status] [action|journey]
```

---

## 8. EVENT VIEW
**Trigger:** Random event during day advance
**Formatter:** `formatEnhancedEvent()` in ViewManager
**Keyboard:** `createEnhancedEventKeyboard()` in ViewManager

### Conditionals:
- **event has chad_reactions field:** Shows chad reaction section at top
- Dynamic choice buttons based on event data

### Keyboard (Dynamic):
```
[Choice 1 text]  [event|{eventId}|{choiceId1}]
[Choice 2 text]  [event|{eventId}|{choiceId2}]
...
```

---

## 9. POST-EVENT VIEW
**Trigger:** After event choice is processed
**Renderer:** `renderPostEventView()` in ViewManager
**Formatter:** `processEventChoice()` in GameEngine

### Description:
Shows the event outcome message and requires user to explicitly return to journey.
This prevents accidental day advancement and gives player time to read the outcome.

### Display Format:
```
🎲 Result:

🎯 YOUR CHOICE: [Choice text]

[Outcome message]

💬 Chad Name: "Contextual quote"  (from chad_reaction field, or 25% chance for random quote)

[Chad death messages if any]

📊 RESOURCE CHANGES:
⛽ Fuel: 500 → 450 (-50)
🛡️ Hull: 100% → 80% (-20%)
[Only resources that changed are shown]

📦 Resources:
⛽ Fuel: 450 | 🍖 Food: 1200 | 🛡️ Hull: 80%
👥 Chad: 5/5 | 😊 Morale: 85% | 💰 Credits: 1500
```

### Conditionals:
- **Events with chad_reaction field:** Shows specific chad_reaction from event outcome (100%)
  - Template placeholders like `{captain_name}` resolved to actual chad
- **Events without chad_reaction field:** 25% chance to show contextual chad quote
  - Uses post_event_success or post_event_failure context
  - Random active chad selected
  - Quote responds to event outcome (success vs failure)
- **Resource Changes:** Only shown if resources actually changed
  - Displays before → after (delta) format
  - All resources tracked: fuel, food, chad, hull, morale, credits
  - Shows positive gains with + sign, losses with - sign

### Keyboard:
```
🏠 Back to Journey  [action|journey]
```

**Note:** The "Back to Journey" button shows the current day status WITHOUT advancing time.

---

## 10. GAME OVER VIEW
**Trigger:** Death condition (no chad, no fuel, hull <= 0)
**Formatter:** `formatGameOverWithImage()` in ViewManager
**Keyboard:** `getGameOverKeyboard()` in ViewManager
**Reason text:** the engine returns a code (`out_of_fuel`, `starved`, `crew_lost`, `hull_breached`); `GameText::gameOverText()` turns it into the sentence shown.

### Conditionals:
None (static keyboard)

### Keyboard:
```
🚀 New Journey            [menu|startnewgame]
🏆 Leaderboard | 📊 My Stats  [menu|leaderboard] [action|mystats]
```

---

## 11. VICTORY VIEW
**Trigger:** Reaching Uranus (distance >= TOTAL_DISTANCE)
**Formatter:** `formatVictoryWithImage()` in ViewManager
**Keyboard:** `getVictoryKeyboard()` in ViewManager

### Conditionals:
None (static keyboard)

### Keyboard:
```
🚀 Play Again                      [menu|startnewgame]
🏆 Leaderboard | 📊 My Stats       [menu|leaderboard] [action|mystats]
📤 Share Victory                   [action|share]
```

---

## 12. HELP VIEW
**Trigger:** "Help" button or `/help` command
**Handler:** `handleHelp()` in uranus_game_bot.php

### Conditionals:
None (informational text only, no keyboard)

### Keyboard:
```
(none)
```

---

## 13. LEADERBOARD VIEW
**Trigger:** "Leaderboard" button
**Handler:** `handleLeaderboard()` in uranus_game_bot.php
**Formatter:** `formatLeaderboard()` in ViewManager

### Conditionals:
- **FEATURE_LEADERBOARDS = false:** Shows "coming soon" message

### Keyboard:
```
(none)
```

### Images:
- **Leaderboard.jpg** - Static image showing leaderboard trophy/rankings theme
- Respects the player's aesthetic preference from `BotPreferences` (defaults to 'main')

---

## 14. WHO'S PLAYING VIEW
**Trigger:** "Who's Playing" button in main menu or journey view, or `/frens` command
**Handler:** `handleWhosPlaying()` in uranus_game_bot.php
**Formatter:** `formatWhosPlaying()` in ViewManager

### Description:
Shows all players with active sessions (activity within last 7 days). Players are sorted by distance traveled (farthest first). Each entry shows:
- Display name (preferred) or username
- Distance traveled or "Victory!" status for finished games
- Percentage of journey complete
- Days elapsed
- Current chad count
- Last activity date

### Conditionals:
- **No active players:** Shows "No active players right now!" message
- **Current user in list:** Their entry is highlighted with 👉 marker

### Keyboard:
```
(none)
```

### Images:
- **WhosPlaying.jpg/png** - Static image showing multiplayer/community theme
- Respects the player's aesthetic preference from `BotPreferences` (defaults to 'main')

---

## 15. ABANDON CONFIRMATION VIEW
**Trigger:** "Abandon & Start New" button
**Handler:** `handleAbandon()` in uranus_game_bot.php

### Conditionals:
None (static keyboard)

### Keyboard:
```
⚠️ Yes, Abandon Journey  [menu|abandonconfirm]
↩️ No, Keep Playing      [menu|continue]
```

---

## 16. PRIVATE DM PROMPT VIEW
**Trigger:** "Play in DMs" button from group
**Handler:** `handlePrivateDM()` in uranus_game_bot.php

### Conditionals:
- **Already in private mode:** Shows different message

### Keyboard:
```
✅ Start Private Game  [menu|confirmprivatedm]
↩️ Stay in Group       [menu|continue]
```

---

## 17. RETURN TO WAYPOINT CONFIRMATION VIEW
**Trigger:** Click "↩️ Return to Last Waypoint" button from Journey View
**Renderer:** `renderReturnConfirmView()` in ViewManager
**Handler:** `handleReturnConfirmation()` in uranus_game_bot.php

### Description:
Confirmation screen shown before executing a return to waypoint action. Shows
destination waypoint, costs, and requires explicit confirmation.

### Display Format:
```
↩️ Return to Last Waypoint?

You're considering turning back to:
🪐 Saturn Outpost

📊 Cost to Return:
⏱️ Time: 2 days
⛽ Fuel: 10 units
🍖 Food: 30 units
📏 Distance: 200M km (backtrack)

⚠️ Note: Returning will reset your gravitational assist (speed → 1×)

Are you sure you want to return?
```

### Conditionals:
- Shows last waypoint passed (based on current distance)
- Defaults to Earth Orbit if no waypoints passed
- Fuel cost: 5 units/day × 2 days = 10 units
- Food cost: chad × 3 units/day × 2 days (e.g., 30 for 5 chad)
- Backtrack distance: current distance - waypoint distance

### Keyboard:
```
✅ Confirm Return | ❌ Cancel
  [action|return_confirm] [action|journey]
```

### Notes:
- Prevents accidental returns (significant cost)
- Shows exact costs for informed decision
- Warning about losing gravitational assist
- Cancel returns to Journey View without cost

---

## 18. GROUP GAME CONFIRMATION VIEW
**Trigger:** "Play in Group" button from DMs
**Handler:** `handleBackToGroup()` in uranus_game_bot.php

### Conditionals:
- **Already in group mode:** Shows "already in group" message
- **No session:** Shows "no active journey" message

### Keyboard:
```
(varies based on state)
```

---

## Image System

**Complete Documentation:** See [`IMAGE_SYSTEM.md`](IMAGE_SYSTEM.md) for detailed image selection criteria

All views support contextual images that enhance the visual experience. Images are selected based on view type, game state (distance, waypoints, milestones), and random factors.

### Quick Reference by View

| View | Image Context | Selection Logic |
|------|---------------|-----------------|
| **Journey** | `'day'` | 4-tier priority: milestone → waypoint → space view (20% random) → zone |
| **Status** | `'status'` | Zone-based (5 zones: earth → uranus) |
| **Event** | `'event'` | Random from 2 event images |
| **Station Choice** | `'waypoint'` | Specific waypoint image or zone fallback |
| **Waypoint** | `'station'` | Specific station image or zone fallback |
| **Shop** | `'shop'` | Current station image or `Continue-01.png` fallback |
| **Game Over** | `'gameover'` | Static `gameover.jpg` |
| **Victory** | `'victory'` | Static `victory.jpg` |
| **Map** | none | Text-only (no image) |

### Key Features

- **24 images total** (6 waypoint, 3 milestone, 3 space view, 2 event, 2 status, 8+ utility/zone)
- **file_id caching** for optimal performance (no re-uploads)
- **Distance zones** provide visual progression (5 zones based on % progress)
- **Random variety** in Journey view (20% chance for space views)
- **Milestone celebration** images at 25%, 50%, 75% progress

**Handler:** `getContextImageUrl()` in ViewManager.php (line 1159)
**Cache:** `data/image_cache.json` (Telegram file_id storage)
**Media:** `media/` directory

**For complete selection criteria, zone calculation, waypoint mappings, caching system details, and troubleshooting:** See [`IMAGE_SYSTEM.md`](IMAGE_SYSTEM.md)

---

## Summary of Conditional Logic

### Status-Based Conditionals:
- `status === 'at_waypoint'` → Shows Waypoint View (stop/skip decision)
- `status === 'at_station'` → Shows Station View (shop, rest, continue)
- `status === 'in_space'` → Shows Journey View with Hunt + Return buttons

### Speed Multiplier Conditionals:
- `speed_multiplier > 1.0` → Status view shows gravitational assist banners
- Waypoint view changes messaging based on current boost

### Session State Conditionals:
- Has session → Main menu shows resume options
- No session → Main menu shows new game option

### Chat Type Conditionals:
- `chatId < 0` (group) → Shows "Play in DMs" button
- `chatId > 0` (private) → Shows "Play in Group" button

### Feature Flags:
- `FEATURE_SHOP` → Enables/disables shop functionality
- `FEATURE_LEADERBOARDS` → Enables/disables leaderboard view

### Location-Based Conditionals:
- At station → Shows Station View with shop/rest options (not Journey View)
- In space → Shows Journey View with hunt/return options

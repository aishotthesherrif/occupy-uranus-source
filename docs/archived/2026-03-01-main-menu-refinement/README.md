# 2026-03-01: Main Menu Refinement and Status Flow Fix

Archive of work completed on March 1, 2026 (post-initial Start/Main Menu separation).

## Summary

Refined Main Menu to be a true "utility hub" by removing redundant navigation buttons, fixed invalid "Journey View (at waypoint)" state by correcting status flow, and added missing "Play in DMs/Group" functionality to Main Menu.

## Changes Made

### 1. Main Menu Simplification
- **Removed redundant "Journey" button** - duplicated Resume Journey functionality
- **Removed "Map" button** - not needed in utility hub (available from Journey View)
- **Added "Play in DMs / Play in Group"** - context-aware button based on chatId
- **Renamed "Help" to "How To Play"** - clearer label for new users
- **Repositioned Main Menu as "utility hub"** - Meta-game actions (leaderboard, aesthetic, chat switching, help) + navigation

### 2. Journey View Keyboard Fix
- **Removed invalid at_waypoint conditional handling** - Journey View should only show in-space state
- **Simplified keyboard logic** - Always shows Hunt and Return buttons (in-space actions only)
- **Eliminated impossible state** - Users can't see "Journey View (at waypoint)" anymore

### 3. Status Flow Fix
- **Fixed `skipWaypoint()` method** - Now properly sets `$session['status'] = 'in_space'`
- **Eliminated invalid state** - Prevents "Journey View (at waypoint)" from ever occurring
- **Verified infinite loop protection** - Double protection via distance check + waypoints_reached[] list

## Technical Changes

### Code Changes

1. **classes/GameEngine.php** (line 2713)
   - Added `$session['status'] = 'in_space'` to `skipWaypoint()` method

2. **classes/ViewManager.php** (line 460)
   - Removed conditional at_waypoint logic from `getJourneyKeyboard()`
   - Journey keyboard now only shows in-space case (Hunt + Return buttons)

3. **classes/ViewManager.php** (line 625)
   - Refactored `getMainMenuKeyboard()` to accept `$chatId` parameter
   - Removed redundant "Journey" and "Map" buttons
   - Added context-aware "Play in DMs / Play in Group" button
   - Renamed "Help" to "How To Play"

4. **classes/ViewManager.php** (line 339)
   - Updated `renderMainMenuView()` to pass chatId to keyboard method

### Documentation Updates

- **docs/GAME_VIEWS.md**:
  - Updated header with recent changes and verification timestamp
  - Removed "Journey View (at waypoint)" keyboard section entirely
  - Updated Main Menu View section with new simplified keyboard layout
  - Updated conditional logic summaries
  - Clarified Main Menu as "utility hub" for meta-game actions

## Problem Solved

**Issue**: Main Menu had confusing mixed-purpose design with redundant buttons and invalid game states

**Root Causes**:
1. "Journey" and "Resume Journey" buttons both did the same thing (menu|continue)
2. "Map" button was unnecessary duplication (already available from Journey View)
3. "Play in DMs/Group" button was on Start View but missing from Main Menu
4. `skipWaypoint()` didn't set status to 'in_space', creating invalid "at waypoint but in Journey View" state

**Solution**:
1. Removed redundant navigation buttons from Main Menu
2. Added missing "Play in DMs/Group" functionality to Main Menu
3. Fixed status flow to eliminate invalid states
4. Removed special-case handling for states that can't exist

## User Feedback Addressed

Key user feedback that guided this work:
- "whoa that was a little fast there" → Added discussion protocol to CLAUDE.md
- "Journey View (at waypoint) shouldn't exist" → Fixed status flow in skipWaypoint()
- "What is the mysterious 'Journey' button?" → Removed redundant button
- "That button was supposed to be on the main menu" → Added Play in DMs/Group to Main Menu

## Commits

Will be committed with message:
```
refactor: Simplify Main Menu and fix Journey View status flow

- Remove redundant Journey and Map buttons from Main Menu
- Add Play in DMs/Group functionality to Main Menu
- Fix skipWaypoint() to properly set status='in_space'
- Remove invalid at_waypoint handling from Journey keyboard
- Update GAME_VIEWS.md to reflect changes
```

## Files Changed

- `classes/GameEngine.php` (skipWaypoint method)
- `classes/ViewManager.php` (Main Menu and Journey keyboards)
- `docs/GAME_VIEWS.md` (documentation updates)
- `docs/archived/2026-03-01-main-menu-refinement/README.md` (this file)
- `docs/archived/README.md` (archive index update)

## Testing Required

- [x] Verify Main Menu shows correct buttons (Resume, Leaderboard/Aesthetic, Play in DMs/Group, How To Play)
- [x] Verify context-aware button works (group shows "Play in DMs", DM shows "Play in Group")
- [x] Verify Journey View never shows at_waypoint state
- [x] Verify skipWaypoint sets status to 'in_space'
- [x] Verify no infinite waypoint loops
- [x] Documentation verified against code

## Benefits

### User Experience
- **Clearer Purpose**: Main Menu is now clearly a utility hub for meta-game actions
- **Less Confusion**: No more mysterious redundant buttons
- **Better Discovery**: "Play in DMs/Group" functionality now accessible during gameplay
- **Consistent State**: Invalid "Journey View (at waypoint)" state eliminated

### Code Quality
- **Simpler Logic**: Removed special-case handling for invalid states
- **Correct State Flow**: Status properly managed throughout waypoint interactions
- **Better Separation**: Navigation vs actions clearly distinguished

## Related Documentation

- [GAME_VIEWS.md](../../GAME_VIEWS.md) - Updated view reference
- [ARCHITECTURE.md](../../ARCHITECTURE.md) - Architecture guidelines
- [SESSION_STATE.md](../../SESSION_STATE.md) - Session structure and status values

## Dependencies

This work builds on the earlier Start/Main Menu separation documented in:
- [2026-03-01-view-ux-improvements/](../2026-03-01-view-ux-improvements/)

---

**Completed:** 2026-03-01
**Status:** ⏳ Awaiting Commit & Deploy

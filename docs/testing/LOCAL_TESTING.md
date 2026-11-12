# Local Game Testing

This guide explains how to test the game locally without needing Telegram.

---

## Local Game Tester

**Purpose:** Test game UI and flow by directly calling game classes (bypasses Telegram API).

### Usage

```bash
php local_game_tester.php
```

### Features

1. **Start New Game** - Create a new game session
   - Enter chad name
   - Select aesthetic (main/simpsons/rick/anime)
   - View the New Game screen

2. **View Current Session** - Inspect raw session data (JSON)

3. **Send Command** - *(Not fully implemented)* Use button press instead

4. **Press Button** - Simulate clicking a button
   - Common buttons:
     - `continue` - Advance day / Continue journey
     - `launch_sequence` - Go to Launch Sequence View
     - `journey` - View journey screen
     - `status` - View status screen
     - `map` - View map
     - `shop` - Open shop
     - `rest` - Rest at station

5. **View Last Message** - Re-display the last game message

6. **Delete Session** - Remove test session

### How It Works

The tester:
- Uses a test user ID (999999 by default)
- Creates a real session file in `logs/sessions/999999.json`
- Calls BotController.route() directly with button actions
- Displays messages, images, and keyboards in terminal format
- Shows callback_data for each button

### Example Flow

```
1. Start new game
   → Enter chad name: "Test Chad"
   → Enter aesthetic: "main"
   → See New Game View with "Launch Ship" button

2. Press button
   → Enter: launch_sequence
   → See Launch Sequence View with "GO" button

3. Press button
   → Enter: continue
   → See Journey View with distance progress

4. Press button
   → Enter: status
   → See Status View with chad/resources

5. View current session
   → See raw session JSON
```

### Tips

- The tester uses the **real game classes** (GameEngine, ViewManager, etc.)
- Session state persists between runs (stored in `logs/sessions/999999.json`)
- Use "Delete session" to reset and start fresh
- Keyboard callback_data is shown so you know what to type in "Press button"
- HTML formatting is stripped and converted to terminal colors

### Limitations

- Doesn't test actual Telegram webhook handling
- Doesn't test message editing/deletion
- Doesn't test image file_id caching
- Commands (like /game) not fully implemented - use buttons instead
- Callback queries with parameters need manual entry (e.g., `event|asteroid|navigate`)

### Advanced Usage

**Test specific button actions:**
```bash
# In "Press button" menu, enter:
continue           # Advance day
journey            # Journey view
status             # Status view
map                # Map view
shop               # Shop view
rest               # Rest (when at station)
launch_sequence    # Launch Sequence View
```

**Test event choices:**
```bash
# First, advance until an event triggers
# Then use callback_data format:
event|<event_id>|<choice_id>

# Example:
event|asteroid|navigate
```

**Test waypoint actions:**
```bash
station|stop|mars    # Stop at waypoint
station|skip|mars    # Skip waypoint
```

---

## Testing Checklist

Before deploying changes, test these flows:

### 1. New Game Flow
- [ ] Start new game
- [ ] Launch Ship → Launch Sequence View
- [ ] GO → Journey View
- [ ] Continue button advances day and distance

### 2. Waypoint Flow
- [ ] Advance to waypoint (Mars at 240M km)
- [ ] See Waypoint View with Stop/Skip buttons
- [ ] Stop → Station View with Shop/Rest/Continue
- [ ] Rest → Returns to Station View
- [ ] Shop → Returns to Station View with Back button
- [ ] Continue → Leaves station, advances day

### 3. Event Flow
- [ ] Trigger event (random, may take several "continue" presses)
- [ ] See event with choices
- [ ] Choose option → See post-event screen
- [ ] Continue → Returns to Journey

### 4. Skip Waypoint Flow
- [ ] Advance to waypoint
- [ ] Skip → See confirmation message
- [ ] Continue → Speed multiplier active (2.0x)
- [ ] Next waypoint → Speed returns to 1.0x

### 5. Status/Map Views
- [ ] Status button shows correct chad/resources
- [ ] Map button shows progress bar and waypoints

---

## Troubleshooting

**"No active session" error:**
- Start a new game first (option 1)

**"Invalid button" error:**
- Check the keyboard output for valid callback_data
- Button names must match exactly (case-sensitive)

**Session gets stuck:**
- View session (option 2) to check status
- Delete session (option 6) to reset

**Want to test different user:**
- Edit `local_game_tester.php` line 113: `new LocalGameTester(123456)`
- Each user ID gets its own session file

---

## Development Notes

The local tester directly instantiates and calls:
- `BotController->route()` - Main routing
- `GameEngine` - Game logic
- `ViewManager` - View rendering
- `SessionManager` - Session persistence

This tests **everything except**:
- Telegram API communication
- Webhook request parsing
- Message tracking (per-user files in `data/message_ids/`)
- Owner stamping in keyboards

For those, you need to test on production or with a real Telegram bot in a test environment.

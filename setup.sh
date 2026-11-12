#!/bin/bash

##############################################
# Occupy Uranus - Automatic Setup Script
# Creates all directories and files needed
##############################################

echo "🚀 Setting up Occupy Uranus Telegram Bot..."
echo ""

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Function to create directory
create_dir() {
    if [ ! -d "$1" ]; then
        mkdir -p "$1"
        echo -e "${GREEN}✓${NC} Created directory: $1"
    else
        echo -e "${YELLOW}⚠${NC} Directory exists: $1"
    fi
}

# Function to create file
create_file() {
    if [ ! -f "$1" ]; then
        touch "$1"
        echo -e "${GREEN}✓${NC} Created file: $1"
    else
        echo -e "${YELLOW}⚠${NC} File exists: $1"
    fi
}

# Create main directories
echo -e "${BLUE}📁 Creating directories...${NC}"
create_dir "classes"
create_dir "data"
create_dir "logs"
echo ""

# Create PHP class files (empty placeholders)
echo -e "${BLUE}📄 Creating PHP class files...${NC}"
create_file "classes/TelegramAPI.php"
create_file "classes/GameEngine.php"
create_file "classes/GameSessionManager.php"
echo ""

# Create main bot file
echo -e "${BLUE}📄 Creating main bot file...${NC}"
create_file "uranus_game_bot.php"
echo ""

# Create config file with template
echo -e "${BLUE}⚙️ Creating config file...${NC}"
if [ ! -f "config.php" ]; then
    cat > config.php << 'EOF'
<?php
/**
 * Occupy Uranus - Telegram Bot Configuration
 */

// ============================================
// TELEGRAM BOT SETTINGS
// ============================================

// IMPORTANT: Replace with your actual bot token from @BotFather
define('BOT_TOKEN', 'YOUR_BOT_TOKEN_HERE');

// Replace with your Telegram user ID (get from @userinfobot)
define('ADMIN_USER_IDS', [123456789]);

// ============================================
// FILE PATHS
// ============================================

define('BASE_DIR', __DIR__);
define('DATA_DIR', BASE_DIR . '/data');
define('LOG_DIR', BASE_DIR . '/logs');

define('GAME_STATE_FILE', DATA_DIR . '/game_state.json');
define('GAME_STATE_BACKUP', DATA_DIR . '/game_state.backup.json');
define('EVENTS_FILE', DATA_DIR . '/events.json');
define('WAYPOINTS_FILE', DATA_DIR . '/waypoints.json');
define('ITEMS_FILE', DATA_DIR . '/items.json');
define('LEADERBOARD_FILE', DATA_DIR . '/leaderboards.json');

define('LOCK_FILE', BASE_DIR . '/bot.lock');
define('LOG_FILE', LOG_DIR . '/bot.log');

// ============================================
// GAME SETTINGS
// ============================================

define('DIFFICULTY_EASY', [
    'name' => 'Colonist',
    'emoji' => '🟢',
    'credits' => 2000,
    'price_multiplier' => 0.7,
    'event_frequency' => 0.2,
    'description' => 'For beginners - More credits, lower prices, fewer hazards'
]);

define('DIFFICULTY_NORMAL', [
    'name' => 'Entrepreneur',
    'emoji' => '🟡',
    'credits' => 1500,
    'price_multiplier' => 1.0,
    'event_frequency' => 0.3,
    'description' => 'Balanced challenge for most players'
]);

define('DIFFICULTY_HARD', [
    'name' => 'Corporate Intern',
    'emoji' => '🔴',
    'credits' => 1000,
    'price_multiplier' => 1.3,
    'event_frequency' => 0.4,
    'description' => 'For veterans - Minimal credits, harsh universe'
]);

define('TOTAL_DISTANCE', 2800000000);
define('TRAVEL_SPEED', 50000000);
define('MAX_CREW', 5);
define('MAX_HULL', 100);
define('MAX_MORALE', 100);
define('FUEL_PER_CREW', 2);
define('FOOD_PER_CREW', 3);
define('SESSION_MAX_AGE', 604800);

define('RATE_LIMIT_DEFAULT', 2);
define('RATE_LIMIT_CONTINUE', 1);
define('RATE_LIMIT_NEWGAME', 5);

define('FEATURE_LEADERBOARDS', true);
define('FEATURE_SHOP', true);
define('FEATURE_ASCII_MAP', true);
define('FEATURE_DEBUG_COMMANDS', true);
define('FEATURE_GROUP_MODE', true);

define('TELEGRAM_API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN);
define('TELEGRAM_TIMEOUT', 10);

define('LOG_LEVEL', 'INFO');
define('LOG_BUFFER_SIZE', 10);

function getDifficulty($key) {
    switch($key) {
        case 'easy': return DIFFICULTY_EASY;
        case 'normal': return DIFFICULTY_NORMAL;
        case 'hard': return DIFFICULTY_HARD;
        default: return DIFFICULTY_NORMAL;
    }
}

function ensureDirectories() {
    $dirs = [DATA_DIR, LOG_DIR];
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

function initializeDataFiles() {
    $files = [
        GAME_STATE_FILE => ['game_sessions' => []],
        LEADERBOARD_FILE => [
            'fastest_journeys' => [],
            'most_chad_saved' => [],
            'player_stats' => []
        ]
    ];
    
    foreach ($files as $file => $defaultData) {
        if (!file_exists($file)) {
            file_put_contents($file, json_encode($defaultData, JSON_PRETTY_PRINT));
        }
    }
}

ensureDirectories();
initializeDataFiles();

if (BOT_TOKEN === 'YOUR_BOT_TOKEN_HERE') {
    die("ERROR: Please set your BOT_TOKEN in config.php\n");
}
?>
EOF
    echo -e "${GREEN}✓${NC} Created config.php (REMEMBER TO ADD YOUR BOT TOKEN!)"
else
    echo -e "${YELLOW}⚠${NC} config.php already exists"
fi
echo ""

# Create data files with empty JSON structures
echo -e "${BLUE}📊 Creating data files...${NC}"

# events.json
if [ ! -f "data/events.json" ]; then
    cat > data/events.json << 'EOF'
{
  "events": []
}
EOF
    echo -e "${GREEN}✓${NC} Created data/events.json (empty - add events later)"
else
    echo -e "${YELLOW}⚠${NC} data/events.json already exists"
fi

# waypoints.json
if [ ! -f "data/waypoints.json" ]; then
    cat > data/waypoints.json << 'EOF'
{
  "waypoints": []
}
EOF
    echo -e "${GREEN}✓${NC} Created data/waypoints.json (empty - add waypoints later)"
else
    echo -e "${YELLOW}⚠${NC} data/waypoints.json already exists"
fi

# game_state.json
if [ ! -f "data/game_state.json" ]; then
    cat > data/game_state.json << 'EOF'
{
  "game_sessions": {}
}
EOF
    echo -e "${GREEN}✓${NC} Created data/game_state.json"
else
    echo -e "${YELLOW}⚠${NC} data/game_state.json already exists"
fi

# leaderboards.json
if [ ! -f "data/leaderboards.json" ]; then
    cat > data/leaderboards.json << 'EOF'
{
  "fastest_journeys": [],
  "most_chad_saved": [],
  "player_stats": {}
}
EOF
    echo -e "${GREEN}✓${NC} Created data/leaderboards.json"
else
    echo -e "${YELLOW}⚠${NC} data/leaderboards.json already exists"
fi

# items.json (future use)
if [ ! -f "data/items.json" ]; then
    cat > data/items.json << 'EOF'
{
  "items": []
}
EOF
    echo -e "${GREEN}✓${NC} Created data/items.json"
else
    echo -e "${YELLOW}⚠${NC} data/items.json already exists"
fi
echo ""

# Create .htaccess for security
echo -e "${BLUE}🔒 Creating .htaccess for security...${NC}"
if [ ! -f ".htaccess" ]; then
    cat > .htaccess << 'EOF'
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
EOF
    echo -e "${GREEN}✓${NC} Created .htaccess"
else
    echo -e "${YELLOW}⚠${NC} .htaccess already exists"
fi
echo ""

# Create .gitignore
echo -e "${BLUE}📝 Creating .gitignore...${NC}"
if [ ! -f ".gitignore" ]; then
    cat > .gitignore << 'EOF'
# Configuration
config.php

# Data files
data/game_state.json
data/game_state.backup.json
data/leaderboards.json

# Logs
logs/*.log

# Lock files
*.lock

# System files
.DS_Store
Thumbs.db

# IDE
.vscode/
.idea/
*.swp
*.swo
*~
EOF
    echo -e "${GREEN}✓${NC} Created .gitignore"
else
    echo -e "${YELLOW}⚠${NC} .gitignore already exists"
fi
echo ""

# Set permissions
echo -e "${BLUE}🔐 Setting permissions...${NC}"
chmod 755 . 2>/dev/null
chmod 755 classes 2>/dev/null
chmod 755 data 2>/dev/null
chmod 755 logs 2>/dev/null
chmod 644 *.php 2>/dev/null
chmod 644 classes/*.php 2>/dev/null
chmod 666 data/*.json 2>/dev/null
chmod 666 logs/*.log 2>/dev/null
echo -e "${GREEN}✓${NC} Permissions set"
echo ""

# Create README
echo -e "${BLUE}📖 Creating README.md...${NC}"
if [ ! -f "README.md" ]; then
    cat > README.md << 'EOF'
# 🚀 Occupy Uranus - Telegram Bot Game

A text-based space survival game inspired by The Oregon Trail.

## Quick Start

1. Get bot token from @BotFather on Telegram
2. Edit `config.php` and add your bot token
3. Upload all files to your web server (HTTPS required)
4. Set webhook: `https://api.telegram.org/bot<TOKEN>/setWebhook?url=https://yourdomain.com/uranus_game_bot.php`
5. Message your bot on Telegram!

## Files to Edit

You still need to add the actual PHP code to these files:
- `classes/TelegramAPI.php` - Telegram API wrapper
- `classes/GameEngine.php` - Game logic
- `classes/GameSessionManager.php` - Session management  
- `uranus_game_bot.php` - Main bot handler

And populate these JSON files:
- `data/events.json` - Game events
- `data/waypoints.json` - Solar system waypoints

See the artifacts in Claude for the complete code!

## Directory Structure

```
/uranus-game-bot/
├── uranus_game_bot.php          # Main bot file
├── config.php                    # Configuration (EDIT THIS!)
├── .htaccess                     # Security rules
├── .gitignore                    # Git ignore rules
├── classes/
│   ├── TelegramAPI.php          # Telegram interface
│   ├── GameEngine.php           # Game logic
│   └── GameSessionManager.php   # State management
├── data/
│   ├── events.json              # Game events
│   ├── waypoints.json           # Waypoints
│   ├── game_state.json          # Player sessions
│   └── leaderboards.json        # High scores
└── logs/
    └── bot.log                   # Application logs
```

## Next Steps

1. Copy the PHP code from Claude artifacts into the respective files
2. Copy the JSON data from Claude artifacts into the data files
3. Edit config.php with your bot token
4. Test locally or deploy to your server
5. Set the webhook URL
6. Start playing!

## Commands

- `/start` - Start the bot
- `/newgame` - Begin new journey
- `/continue` - Advance one day
- `/status` - View mission status
- `/map` - View solar system map
- `/shop` - Visit space station
- `/help` - Show help

## Support

Check the logs at `logs/bot.log` for errors.

Good luck, Captain! 🚀🪐
EOF
    echo -e "${GREEN}✓${NC} Created README.md"
else
    echo -e "${YELLOW}⚠${NC} README.md already exists"
fi
echo ""

# Create TODO file
echo -e "${BLUE}✅ Creating TODO.md...${NC}"
if [ ! -f "TODO.md" ]; then
    cat > TODO.md << 'EOF'
# Setup Checklist

## Required Steps

- [ ] Copy PHP code from Claude artifacts to respective files:
  - [ ] `classes/TelegramAPI.php`
  - [ ] `classes/GameEngine.php`
  - [ ] `classes/GameSessionManager.php`
  - [ ] `uranus_game_bot.php`

- [ ] Copy JSON data from Claude artifacts:
  - [ ] `data/events.json`
  - [ ] `data/waypoints.json`

- [ ] Edit `config.php`:
  - [ ] Add your bot token from @BotFather
  - [ ] Add your Telegram user ID

- [ ] Upload to web server (HTTPS required)

- [ ] Set webhook:
  ```
  https://api.telegram.org/bot<YOUR_TOKEN>/setWebhook?url=https://yourdomain.com/uranus_game_bot.php
  ```

- [ ] Set bot commands:
  ```bash
  curl -X POST "https://api.telegram.org/bot<TOKEN>/setMyCommands" \
    -H "Content-Type: application/json" \
    -d '{"commands":[
      {"command":"start","description":"🎮 Start or return to main menu"},
      {"command":"newgame","description":"🚀 Begin a new journey"},
      {"command":"continue","description":"▶️ Advance one day"},
      {"command":"status","description":"📊 View mission status"},
      {"command":"map","description":"🗺️ Display solar system map"},
      {"command":"shop","description":"🛒 Visit space station"},
      {"command":"help","description":"❓ Show help"}
    ]}'
  ```

- [ ] Test the bot:
  - [ ] Send `/start` command
  - [ ] Start a new game
  - [ ] Buy supplies
  - [ ] Launch and continue
  - [ ] Encounter an event
  - [ ] Check status and map

## Optional

- [ ] Set up monitoring
- [ ] Configure backups
- [ ] Add more events
- [ ] Customize difficulty
- [ ] Add analytics
EOF
    echo -e "${GREEN}✓${NC} Created TODO.md"
else
    echo -e "${YELLOW}⚠${NC} TODO.md already exists"
fi
echo ""

# Summary
echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}✅ Setup Complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo -e "Directory structure created:"
echo -e "  📁 classes/"
echo -e "  📁 data/"
echo -e "  📁 logs/"
echo ""
echo -e "Files created:"
echo -e "  📄 config.php ${RED}(⚠️ EDIT THIS - Add your bot token!)${NC}"
echo -e "  📄 uranus_game_bot.php"
echo -e "  📄 classes/TelegramAPI.php"
echo -e "  📄 classes/GameEngine.php"
echo -e "  📄 classes/GameSessionManager.php"
echo -e "  📄 data/*.json"
echo -e "  📄 .htaccess"
echo -e "  📄 .gitignore"
echo -e "  📄 README.md"
echo -e "  📄 TODO.md"
echo ""
echo -e "${YELLOW}⚠️  IMPORTANT NEXT STEPS:${NC}"
echo -e "1. Copy the PHP code from Claude artifacts into the PHP files"
echo -e "2. Copy the JSON data from Claude artifacts into the data/*.json files"
echo -e "3. Edit ${YELLOW}config.php${NC} and add your bot token"
echo -e "4. Upload to your web server"
echo -e "5. Set the webhook URL"
echo ""
echo -e "Check ${BLUE}TODO.md${NC} for a complete checklist!"
echo ""
echo -e "${GREEN}Good luck with your deployment! 🚀🪐${NC}"
echo ""
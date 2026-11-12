<?php
/**
 * Occupy Uranus - Configuration
 *
 * All application constants and the getDifficulty() helper function live here.
 * This file is require_once'd at the top of every entry point.
 *
 * DEPLOYMENT CHECKLIST
 * --------------------
 * [x] Set BOT_TOKEN to your token from @BotFather
 * [ ] Set WEBHOOK_SECRET to a random string (register it via TelegramAPI::setWebhook)
 * [x] Add your Telegram user ID to ADMIN_USER_IDS
 * [ ] Verify DATA_DIR exists and is writable by your web server user
 * [x] Set IMAGE_BASE_URL if you want zone-based images (optional)
 * [ ] Install php-apcu for rate limiting: apt install php-apcu
 * [ ] Set apc.enable_cli=1 in php.ini if you want rate limiting in CLI/cron
 */

// =============================================================================
// BOT CREDENTIALS
// =============================================================================

/**
 * Your Telegram Bot API token from @BotFather.
 *
 * The code reads TELEGRAM_BOT_TOKEN from the environment and uses a placeholder
 * if it is not configured.
 *
 * Set the token in your local environment or ignored .env file. Never commit it.
 */
define('BOT_TOKEN', getenv('TELEGRAM_BOT_TOKEN') ?: 'YOUR_BOT_TOKEN_HERE');

/**
 * Webhook secret token for request authentication.
 *
 * Set this to any random string of 1–256 characters (A-Z a-z 0-9 _ - allowed).
 * Register it when you set the webhook:
 *   $telegram->setWebhook('https://yourdomain.com/uranus_game_bot.php', WEBHOOK_SECRET);
 *
 * Telegram will include this value in the X-Telegram-Bot-Api-Secret-Token header
 * on every incoming update. The bot validates it before processing anything.
 * Leave empty ('') to disable validation in local development.
 *
 * Generate a good value: php -r "echo bin2hex(random_bytes(32));"
 */
define('WEBHOOK_SECRET', getenv('WEBHOOK_SECRET') ?: '');

/**
 * Telegram Bot API base URL — includes the token so API methods can be called
 * by appending '/methodName' to this string.
 * Do not add a trailing slash.
 */
define('TELEGRAM_API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN);

/**
 * cURL timeout in seconds for each Telegram API call.
 * Telegram's own timeout for webhook responses is 10 seconds, so keeping this
 * below 8 leaves headroom. Increase for very slow hosting environments.
 */
define('TELEGRAM_TIMEOUT', 8);

// =============================================================================
// PATHS & DIRECTORIES
// =============================================================================

/**
 * Base directory for static game data (events, waypoints, personalities, leaderboards, backups).
 * Must exist and be writable by the web server user.
 * Using __DIR__ makes the path relative to this config file regardless of where
 * the script is called from.
 */
define('DATA_DIR', __DIR__ . '/data');

/**
 * Optional single directory for everything the app writes at runtime.
 *
 * Unset (shared hosting): runtime files stay where they always were —
 * logs/, data/leaderboards.json, media/file_ids.json.
 * Set (Docker sets URANUS_RUNTIME_DIR=/var/lib/uranus): logs, sessions, bot prefs,
 * message IDs, the leaderboard and the Telegram file_id cache all live in this one
 * directory, so a single volume holds all player state and the image can be rebuilt freely.
 */
define('RUNTIME_DIR', rtrim((string) getenv('URANUS_RUNTIME_DIR'), '/'));

/**
 * Base directory for runtime/transient data (sessions, message IDs, logs).
 * Must exist and be writable by the web server user.
 */
define('LOGS_DIR', RUNTIME_DIR !== '' ? RUNTIME_DIR : __DIR__ . '/logs');

/**
 * Active game sessions — one JSON object containing all players' sessions.
 * This file is the only source of truth for whether a user has a game in progress
 * (active journey). All session load/save and "has active session?" checks go
 * through GameSessionManager and read from or write to this file.
 */
define('GAME_STATE_FILE',   DATA_DIR . '/game_state.json');

/**
 * Directory for per-user session files (one JSON file per user).
 * Used by the patched GameSessionManager to replace the monolithic GAME_STATE_FILE.
 */
define('SESSIONS_DIR',      LOGS_DIR . '/sessions');

/** Leaderboard data */
define('LEADERBOARD_FILE',  (RUNTIME_DIR !== '' ? RUNTIME_DIR : DATA_DIR) . '/leaderboards.json');

/** Read-only game data: random events (includes enhanced events with crew_reaction) */
define('EVENTS_FILE',       DATA_DIR . '/events.json');

/** Crew personalities (traits, quotes, names) for personality system */
define('CREW_PERSONALITIES_FILE', DATA_DIR . '/crew_personalities.json');

/** Read-only game data: solar system waypoints */
define('WAYPOINTS_FILE',    DATA_DIR . '/waypoints.json');

/** Per-user message ID files — one file per user (no race conditions) */
define('MESSAGE_IDS_DIR',   LOGS_DIR . '/message_ids');

/**
 * Local directory for game images (and the file_id cache).
 * Images are read from here and sent to Telegram; file_ids are cached in media/file_ids.json.
 */
define('MEDIA_DIR', __DIR__ . '/media');

/** Telegram file_id cache for all media — stored in media folder; avoids re-uploading by URL */
define('IMAGE_CACHE_FILE',  (RUNTIME_DIR !== '' ? RUNTIME_DIR : MEDIA_DIR) . '/file_ids.json');

/** Application log — appended to by the log buffer */
define('LOG_FILE',          LOGS_DIR . '/bot.log');

/** Diagnostic log — immediate error logging for webhook debugging */
define('DIAGNOSTIC_LOG',    LOGS_DIR . '/diagnostic.log');

/** Structured telemetry log — one JSON object per line (JSON Lines format) */
define('TELEMETRY_FILE',    LOGS_DIR . '/telemetry.jsonl');

// =============================================================================
// LOGGING
// =============================================================================

/**
 * Debug mode — enables verbose diagnostic logging to diagnostic.log
 *
 * When TRUE:  Detailed step-by-step logs for webhook processing, API calls, etc.
 *             Use this when troubleshooting issues. Log files grow quickly.
 *
 * When FALSE: Only critical errors are logged to diagnostic.log.
 *             Normal operation logging still goes to bot.log.
 *             Recommended for production to minimize disk usage.
 *
 * TIP: Set to TRUE temporarily when debugging, then back to FALSE.
 */
define('DEBUG_MODE', false);

/**
 * Number of log lines to buffer in memory before flushing to disk.
 * Higher values reduce I/O but mean more log entries are lost if the process
 * terminates unexpectedly. 10 is a safe default.
 */
define('LOG_BUFFER_SIZE', 10);

// =============================================================================
// TELEMETRY
// =============================================================================

/**
 * Set to true to write structured telemetry events to TELEMETRY_FILE.
 * Used by telemetry_analyzer.php to generate player behaviour reports.
 * Disabled to reduce I/O overhead.
 */
define('TELEMETRY_ENABLED', false);

// =============================================================================
// SESSION MANAGEMENT
// =============================================================================

/**
 * Maximum session age in seconds before cleanupOldSessions() removes it.
 * Default: 30 days. A session that has not been touched in this time is
 * considered abandoned and will be pruned on the next cleanup run.
 */
define('SESSION_MAX_AGE', 30 * 24 * 60 * 60); // 30 days

// =============================================================================
// RATE LIMITING & MESSAGE PRUNING
// =============================================================================

/**
 * Maximum webhook actions per user per second.
 * Set to 0 to disable rate limiting entirely.
 * Requires the php-apcu extension; if unavailable, rate limiting is skipped.
 */
define('RATE_LIMIT_PER_SECOND', 3);

/**
 * Maximum age in seconds for per-user message ID entries before they
 * become eligible for pruning. Default: 24 hours.
 */
define('MESSAGE_IDS_MAX_AGE', 86400);

// =============================================================================
// TELEGRAM CALLBACK DATA
// =============================================================================

/**
 * Telegram's maximum byte length for callback_data in inline keyboards.
 * @see https://core.telegram.org/bots/api#inlinekeyboardbutton
 */
define('TELEGRAM_CALLBACK_DATA_MAX_BYTES', 64);

/**
 * Bytes to reserve for the owner stamp "|u{userId}" when validating callback_data
 * before it is appended. User IDs can be up to ~10 digits, so reserve 15 for safety.
 */
define('CALLBACK_DATA_OWNER_STAMP_RESERVE', 15);

// =============================================================================
// ADMIN ACCESS
// =============================================================================

/**
 * Array of Telegram user IDs who have access to admin commands:
 *   /resetall, /debug_userid, /debug_sessions
 *
 * Find your user ID: message @userinfobot on Telegram, or use /debug_userid
 * after temporarily adding yourself here.
 *
 * Values are cast to int on comparison so string IDs are also safe.
 */
define('ADMIN_USER_IDS', [
    6970271124,
    1002860538306,
    1087968824,
]);

// =============================================================================
// FEATURE FLAGS
// =============================================================================
// Set to false to display "coming soon" messages for unfinished features.
// Useful for staged rollouts and A/B testing.

/** Space port and in-journey shop (/shop command) */
define('FEATURE_SHOP',            true);

/** Leaderboard (/leaderboard command) */
define('FEATURE_LEADERBOARDS',    true);

/** Admin debug commands (/debug_*) — should always be false in production */
define('FEATURE_DEBUG_COMMANDS',  true);

// =============================================================================
// IMAGES
// =============================================================================
// Images are read from MEDIA_DIR (see above). Zone filenames: status_earth.jpg,
// status_inner.jpg, status_belt.jpg, status_outer.jpg, status_uranus.jpg;
// day_*.jpg; gameover.jpg; victory.jpg. File_ids are cached in MEDIA_DIR/file_ids.json.

// =============================================================================
// GAME BALANCE — TRAVEL & RESOURCES
// =============================================================================

/**
 * Total journey distance from Earth to Uranus in kilometres.
 * Approximate real-world average distance: 2,724,000,000 km.
 * Rounded to 2.8B for clean milestone numbers.
 */
define('TOTAL_DISTANCE', 2_800_000_000);

/**
 * Distance the ship travels per day in kilometres.
 * This gives a journey of approximately 140 days on normal travel.
 * Real chemical rockets: ~1–2M km/day. Ion drives: slower but more efficient.
 * Balance against starting fuel if you change this.
 *
 * Days to Uranus at base speed: ~97 days (40 before Jupiter + 57 after speed boost)
 */
define('TRAVEL_SPEED', 20_000_000); // 20 million km/day

/**
 * Fuel consumed per day of travel (constant, regardless of crew count).
 * Ship mass doesn't meaningfully change with crew size.
 * On a rest day: FUEL_PER_DAY * 0.5 (life support only, no thrust).
 */
define('FUEL_PER_DAY', 5);

/**
 * Food consumed per crew member per day.
 * Total daily food consumption = crew_count * FOOD_PER_CREW.
 * On a rest day: crew_count * FOOD_PER_CREW * 2 (larger meals, leisure time).
 */
define('FOOD_PER_CREW', 3);

// =============================================================================
// GAME BALANCE — CREW
// =============================================================================

/** Maximum crew size. Also the starting crew count. */
define('MAX_CREW', 5);

// =============================================================================
// GAME BALANCE — RESOURCE CAPS
// =============================================================================
// These prevent players from stockpiling unlimited resources in the shop.
// Set generously — the caps are a sanity bound, not a tight design constraint.

/** Maximum vibes percentage (displayed as 0–100%) */
define('MAX_VIBES', 100);

/** Maximum hull integrity percentage (displayed as 0–100%) */
define('MAX_HULL', 100);

/**
 * Maximum fuel tank capacity.
 * Set to 2× starting fuel (600 × 2 = 1200).
 * At FUEL_PER_DAY=5, max capacity lasts 240 days of travel.
 */
define('MAX_FUEL', 1200);

/**
 * Maximum food storage capacity.
 * Set to 2× starting food (1750 × 2 = 3500).
 * At FOOD_PER_CREW=3 and MAX_CREW=5, max capacity lasts 233 days (3500/15).
 */
define('MAX_FOOD', 3500);

// =============================================================================
// GAME BALANCE — STARTING RESOURCES
// =============================================================================

/**
 * Starting resources (balanced for standard gameplay).
 * - Fuel: 400 units (80 days of travel)
 * - Food: 800 units (53 days for 5 crew)
 * - ETH: 15.000 (strategic shopping at waypoints)
 * - Hull: 100% (full integrity)
 * - Vibes: 100% (maximum morale)
 *
 * These values are set in GameEngine::createNewGame()
 * Shop prices have 1.0 multiplier (base prices).
 */

// =============================================================================
// RUNTIME SETUP
// =============================================================================

/**
 * Ensure required directories exist.
 * This runs on every request but is cheap — file_exists() on a warm filesystem
 * is a single syscall and mkdir() is a no-op when the directory is already there.
 */
(function () {
    $dirs = [
        DATA_DIR,
        DATA_DIR . '/backups',
        LOGS_DIR,
        SESSIONS_DIR,
        MESSAGE_IDS_DIR,
    ];
    foreach ($dirs as $dir) {
        if (!file_exists($dir)) {
            if (!mkdir($dir, 0755, true) && !is_dir($dir)) {
                // Log to PHP error log if we can't write — don't die silently
                error_log("config.php: failed to create directory: $dir");
            }
        }
    }
})();

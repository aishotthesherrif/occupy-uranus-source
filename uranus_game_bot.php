<?php
/**
 * Occupy Uranus - Telegram Game Bot
 * Main webhook handler and command router
 *
 * PATCH NOTES — changes from previous version:
 *
 * RATE LIMITING (was Issue #6 — no rate limiting in code)
 * - processWebhook() now enforces per-user rate limiting via APCu (if available).
 *   Default: 3 actions per second per user. If APCu is not installed, rate limiting
 *   is skipped with a one-time log warning per request. This prevents button-mashing
 *   and command flooding from mutating game state faster than intended.
 *
 * CALLBACK_DATA POST-STAMP VALIDATION (was Issue #3 — silent 64-byte overflow)
 * - sendMessage() now checks the byte length of each callback_data string AFTER
 *   appending the owner stamp "|u{userId}". Any callback that would exceed Telegram's
 *   64-byte limit is logged as an error and the owner stamp is stripped for that button
 *   (the button still works, it just won't be owner-locked). Previously, the 64-byte
 *   check in renderEventMessage() happened BEFORE the stamp was appended, so long
 *   event IDs + long user IDs silently produced invalid callback_data.
 *
 * CHAT ROUTING (bot-only state)
 * - Where the bot replies (private DM vs group) is kept in BotPreferences, not in the saved
 *   game, so a game started in the mini-app can be continued in the bot. sendMessage()
 *   looks the preference up itself.
 *
 * MESSAGE ID PRUNING (was Issue #5 — unbounded growth)
 * - pruneMessageIds() removes entries older than MESSAGE_IDS_MAX_AGE (default 24h)
 *   based on a per-entry timestamp. Called probabilistically (1 in 50 requests) to
 *   amortise the cost. Entries now store {messageId, timestamp} instead of bare messageId.
 *
 * HEALTH PAGE INFO LEAK FIXED (was Issue #11)
 * - The GET info page no longer reveals whether WEBHOOK_SECRET is configured.
 *   This was minor information leakage visible to any browser visitor.
 *
 * USERID CASTING (was Issue #10 — inconsistent int/string)
 * - All user IDs from Telegram are now cast to (int) at the point of extraction
 *   from the update payload. Internal methods receive int consistently.
 *
 * DIFFICULTY SELECTION REMOVED (2026-02-27)
 * - Removed difficulty selection UI. All games now default to 'normal' difficulty.
 *   Players go directly from "New Journey" to the shop. Simplified onboarding flow.
 *
 * RESET ALL - COMPLETE RUNTIME DATA WIPE
 * - executeResetAll() deletes all runtime data without backups:
 *   sessions, leaderboards, image cache, message IDs, logs (app, diagnostic, telemetry).
 *
 * DRY REFACTOR
 * - sendNoSessionMessage() + getNewJourneyKeyboard() consolidate 6+ duplicated
 *   "no active journey" response blocks.
 * - stampOwnerIntoKeyboard() extracts owner stamping logic for reuse in sendMessage
 *   and handleMap (plain-text map).
 * - handleGameOverResult() consolidates game-over handling in handleContinue and handleRest.
 * - getGameIntroText() and getSessionSnapshotForTelemetry() reduce duplication.
 * - Callback_data 64-byte constants moved to config.php.
 */

require_once 'config.php';
require_once 'game/GameSessionManager.php';
require_once 'game/GameEngine.php';
require_once 'game/Telemetry.php';
require_once 'game/GameService.php';
require_once 'bot/TelegramAPI.php';
require_once 'bot/FormatHelpers.php';
require_once 'bot/GameText.php';
require_once 'bot/ViewManager.php';
require_once 'bot/BotPreferences.php';
require_once 'bot/BotController.php';

class OccupyUranusBot {

    private TelegramAPI $telegram;
    private GameService $game;
    private GameEngine $gameEngine;
    private ViewManager $viewManager;
    private BotPreferences $prefs;
    private BotController $botController;

    /** Within-request log buffer — flushed on __destruct and after processWebhook() */
    private static array $logBuffer = [];

    /** @var bool Whether the APCu-missing warning has been logged this request */
    private static bool $apcuWarningLogged = false;

    /**
     * Callback reply context.
     *
     * When handling a callback_query (button press), responses must be sent to the
     * chat where the button was pressed — not wherever getPreferredChatId() would
     * route them. These two properties capture that context for the duration of the
     * callback dispatch and are cleared immediately after in the finally block.
     *
     * This is safe in a webhook context because each PHP process handles exactly
     * one request synchronously — there is no concurrency within one process.
     */
    private ?int $callbackReplyChatId   = null;
    private ?int $callbackReplyThreadId = null;

    public function __construct() {
        self::logDiagnostic('Constructor: Creating TelegramAPI...');
        $this->telegram       = new TelegramAPI();
        self::logDiagnostic('Constructor: TelegramAPI created OK');

        // The bot talks to the game only through GameService. GameEngine is built here because
        // ViewManager and GameText read game data (waypoints, crew, bands) from it to draw screens.
        self::logDiagnostic('Constructor: Creating game, views and controller...');
        $this->gameEngine    = new GameEngine();
        $this->game          = new GameService($this->gameEngine, new GameSessionManager());
        $this->prefs         = new BotPreferences();
        $this->viewManager   = new ViewManager($this->gameEngine);
        $this->botController = new BotController($this->game, $this->viewManager, new GameText($this->gameEngine), $this->prefs);
        self::logDiagnostic('Constructor: New architecture components created OK');
    }

    public function __destruct() {
        $this->flushLogBuffer();
    }

    // =========================================================================
    // LOGGING & TELEMETRY
    // =========================================================================

    public function log(string $message, string $level = 'INFO'): void {
        // Skip DEBUG messages if not in debug mode
        if ($level === 'DEBUG' && !DEBUG_MODE) {
            return;
        }

        $timestamp          = date('Y-m-d H:i:s');
        self::$logBuffer[]  = "[$timestamp] [$level] $message" . PHP_EOL;

        if (count(self::$logBuffer) >= LOG_BUFFER_SIZE) {
            $this->flushLogBuffer();
        }
    }

    public function flushLogBuffer(): void {
        if (empty(self::$logBuffer)) {
            return;
        }
        $dir = dirname(LOG_FILE);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $written = @file_put_contents(LOG_FILE, implode('', self::$logBuffer), FILE_APPEND | LOCK_EX);
        if ($written === false) {
            error_log('OccupyUranus: failed to write log to ' . LOG_FILE . ' — check path and that the web server user can write to the data directory.');
        }
        self::$logBuffer = [];
    }

    /**
     * Immediate diagnostic logging for webhook debugging.
     * Writes directly to diagnostic.log without buffering.
     * Static so it can be called from anywhere, including entry point.
     *
     * @param string $message Message to log
     * @param string $level   Log level: 'DEBUG', 'ERROR', 'CRITICAL'
     *                        DEBUG messages only logged when DEBUG_MODE=true
     *                        ERROR/CRITICAL always logged
     */
    public static function logDiagnostic(string $message, string $level = 'DEBUG'): void {
        // Skip DEBUG messages if not in debug mode
        if ($level === 'DEBUG' && !DEBUG_MODE) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $entry = "[$timestamp] [$level] $message" . PHP_EOL;

        $dir = dirname(DIAGNOSTIC_LOG);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        $written = @file_put_contents(DIAGNOSTIC_LOG, $entry, FILE_APPEND | LOCK_EX);

        // Fallback to PHP error log if file write fails (only for ERROR/CRITICAL)
        if ($written === false && $level !== 'DEBUG') {
            error_log("[OCCUPY_URANUS_DIAGNOSTIC] [$level] $message (WARNING: Could not write to diagnostic.log)");
        }
    }

    /** Bot-level telemetry (who pressed what). Game events are recorded by GameService. */
    public function logTelemetry(string $action, array $data = [], $userId = null, ?string $sessionId = null): void {
        Telemetry::record($action, $data, $userId, $sessionId, 'bot');
    }

    public function logUserInteraction(string $action, int $userId, $chatId, ?string $username, ?string $command = null, ?int $threadId = null): void {
        $this->logTelemetry($action, [
            'chat_id'   => $chatId,
            'username'  => $username,
            'command'   => $command,
            'thread_id' => $threadId,
            'chat_type' => $chatId < 0 ? 'group' : 'private',
        ], $userId);
    }

    // =========================================================================
    // RATE LIMITING (APCu-based, per-user)
    // =========================================================================

    /**
     * Check whether this user has exceeded the per-second action rate limit.
     * Uses APCu for shared-memory counters across requests.
     * Returns true if the request should be rejected, false if allowed.
     *
     * @param  int  $userId
     * @return bool  True = rate limited (reject), False = allowed
     */
    private function isRateLimited(int $userId): bool {
        if (!function_exists('apcu_fetch')) {
            if (!self::$apcuWarningLogged) {
                $this->log('APCu not available — rate limiting disabled. Install php-apcu for production.', 'WARNING');
                self::$apcuWarningLogged = true;
            }
            return false;
        }

        $maxPerSecond = defined('RATE_LIMIT_PER_SECOND') ? RATE_LIMIT_PER_SECOND : 3;
        $key          = 'ou_rate_' . $userId . '_' . time();

        $current = apcu_fetch($key, $success);
        if (!$success) {
            // First action this second — initialise counter with 2-second TTL
            apcu_store($key, 1, 2);
            return false;
        }

        if ($current >= $maxPerSecond) {
            $this->log("Rate limited user $userId ($current actions this second)", 'WARNING');
            return true;
        }

        apcu_inc($key);
        return false;
    }

    // =========================================================================
    // MESSAGE ID TRACKING (file-based, cross-request)
    // =========================================================================

    /**
     * Get the message ID file path for a specific user.
     * Uses per-user files to eliminate race conditions.
     */
    private function getUserMessageIdsFile(int $userId): string {
        $dir = defined('MESSAGE_IDS_DIR') ? MESSAGE_IDS_DIR : (DATA_DIR . '/message_ids');
        return $dir . '/' . $userId . '.json';
    }

    /**
     * Load message IDs for a specific user. Per-user files eliminate race conditions.
     */
    private function loadUserMessageIds(int $userId): array {
        $file = $this->getUserMessageIdsFile($userId);
        if (!file_exists($file)) {
            return [];
        }
        $content = @file_get_contents($file);
        $data = $content ? json_decode($content, true) : [];
        return is_array($data) ? $data : [];
    }

    /**
     * Save message IDs for a specific user.
     */
    private function saveUserMessageIds(int $userId, array $ids): void {
        $file = $this->getUserMessageIdsFile($userId);
        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        @file_put_contents($file, json_encode($ids, JSON_PRETTY_PRINT), LOCK_EX);
    }

    private function getMessageKey(int $userId, $chatId, ?int $threadId = null): string {
        // Key format: chatId or chatId_threadId (userId is now the filename)
        return $threadId ? "{$chatId}_{$threadId}" : (string)$chatId;
    }

    private function getLastMessageId(int $userId, $chatId, ?int $threadId = null): ?int {
        $ids = $this->loadUserMessageIds($userId);
        $key = $this->getMessageKey($userId, $chatId, $threadId);
        if (!isset($ids[$key])) {
            return null;
        }
        // Handle both old (bare int) and new ({id,ts}) format
        $entry = $ids[$key];
        return is_array($entry) ? (int) ($entry['id'] ?? 0) : (int) $entry;
    }

    private function storeLastMessageId(int $userId, $chatId, int $messageId, ?int $threadId = null): void {
        $ids = $this->loadUserMessageIds($userId);
        $key = $this->getMessageKey($userId, $chatId, $threadId);
        $ids[$key] = ['id' => $messageId, 'ts' => time()];
        $this->saveUserMessageIds($userId, $ids);
    }

    private function deleteLastMessage(int $userId, $chatId, ?int $threadId = null): void {
        $messageId = $this->getLastMessageId($userId, $chatId, $threadId);
        if ($messageId === null) {
            return;
        }

        $this->telegram->deleteMessage($chatId, $messageId);

        // Clear the stored ID regardless of delete success
        $ids = $this->loadUserMessageIds($userId);
        unset($ids[$this->getMessageKey($userId, $chatId, $threadId)]);
        $this->saveUserMessageIds($userId, $ids);
    }

    /**
     * Remove stale message ID files (not run every request - probabilistic).
     * With per-user files, we delete entire files older than MAX_AGE.
     */
    private function pruneMessageIds(): void {
        // Only run ~2% of requests
        if (rand(1, 50) !== 1) {
            return;
        }

        $dir = defined('MESSAGE_IDS_DIR') ? MESSAGE_IDS_DIR : (DATA_DIR . '/message_ids');
        if (!is_dir($dir)) {
            return;
        }

        $maxAge = defined('MESSAGE_IDS_MAX_AGE') ? MESSAGE_IDS_MAX_AGE : 86400; // 24h
        $now = time();
        $pruned = 0;

        $files = @scandir($dir);
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || substr($file, -5) !== '.json') {
                continue;
            }

            $path = $dir . '/' . $file;
            $mtime = @filemtime($path);

            if ($mtime !== false && ($now - $mtime) > $maxAge) {
                if (@unlink($path)) {
                    $pruned++;
                }
            }
        }

        if ($pruned > 0) {
            $this->log("Pruned $pruned stale message ID files", 'DEBUG');
        }
    }

    // =========================================================================
    // WEBHOOK PROCESSING
    // =========================================================================

    /**
     * Process webhook update from array (for testing)
     * @param array|null $updateData If provided, use this instead of reading php://input
     * @return bool Success status
     */
    public function processWebhook(?array $updateData = null): bool {
        try {
            if ($updateData !== null) {
                // Testing mode: use provided update data
                self::logDiagnostic('processWebhook: Using provided update data (test mode)');
                $content = json_encode($updateData);
            } else {
                // Production mode: read from php://input
                self::logDiagnostic('processWebhook: Reading php://input...');
                $content = file_get_contents('php://input');
            }
            self::logDiagnostic('processWebhook: Received ' . strlen($content) . ' bytes');

            if (empty($content)) {
                $this->log("Empty webhook content", 'WARNING');
                self::logDiagnostic('processWebhook: Content was empty');
                return false;
            }

            self::logDiagnostic('processWebhook: Decoding JSON...');
            $update = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->log("JSON decode error: " . json_last_error_msg(), 'ERROR');
                self::logDiagnostic('processWebhook: JSON decode FAILED: ' . json_last_error_msg());
                return false;
            }
            self::logDiagnostic('processWebhook: JSON decoded OK');

            // Probabilistic maintenance
            $this->pruneMessageIds();

            // Button presses
            if (isset($update['callback_query'])) {
                self::logDiagnostic('processWebhook: Handling callback_query');
                $result = $this->handleCallbackQuery($update['callback_query']);
                self::logDiagnostic('processWebhook: callback_query handled, result=' . ($result ? 'true' : 'false'));
                return $result;
            }

            if (!isset($update['message'])) {
                self::logDiagnostic('processWebhook: No callback_query or message in update, ignoring');
                return false;
            }

            self::logDiagnostic('processWebhook: Processing message...');

            $message     = $update['message'];
            $chatId      = $message['chat']['id'];
            $userId      = (int) $message['from']['id'];
            $username    = $message['from']['username'] ?? 'Unknown';
            $displayName = $this->getDisplayNameFromFrom($message['from'], $username);
            $messageText = $message['text'] ?? '';
            $threadId    = $message['message_thread_id'] ?? null;

            // Only process commands
            if (empty($messageText) || $messageText[0] !== '/') {
                return false;
            }

            // Rate limit check
            if ($this->isRateLimited($userId)) {
                return false;
            }

            $gameCommands = [
                '/game', '/help', '/howtoplay', '/frens', '/leaderboard',
                '/resetall', '/resetcache',
            ];

            $lowerText = strtolower(explode('@', explode(' ', $messageText)[0])[0]);
            $isGameCommand = in_array($lowerText, $gameCommands);

            if (!$isGameCommand && in_array($userId, ADMIN_USER_IDS) && strpos($lowerText, '/debug') === 0) {
                $isGameCommand = true;
            }

            if (!$isGameCommand) {
                return false;
            }

            $threadInfo = $threadId ? " (thread: $threadId)" : '';
            $this->log("User $username ($userId) in chat $chatId$threadInfo: $messageText", 'DEBUG');
            $this->logUserInteraction('command_received', $userId, $chatId, $username, $messageText, $threadId);

            $parsed  = $this->parseCommand($messageText);
            $command = $parsed['command'];
            $params  = $parsed['parameters'];

            self::logDiagnostic("processWebhook: Routing command '$command' with params '$params'");
            $result = $this->routeCommand($command, $params, $userId, $chatId, $username, $threadId, $displayName);
            self::logDiagnostic("processWebhook: routeCommand returned " . ($result ? 'true' : 'false'));
            return $result;

        } catch (Exception $e) {
            $this->log("Exception in processWebhook: " . $e->getMessage(), 'ERROR');
            self::logDiagnostic('processWebhook: EXCEPTION: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return false;
        }
    }

    // =========================================================================
    // COMMAND PARSING & ROUTING
    // =========================================================================

    private function parseCommand(string $text): array {
        $parts           = explode('@', $text, 2);
        $commandWithArgs = $parts[0];
        $parts           = explode(' ', $commandWithArgs, 2);

        return [
            'command'    => strtolower($parts[0]),
            'parameters' => isset($parts[1]) ? trim($parts[1]) : '',
        ];
    }

    private function routeCommand(string $command, string $parameters, int $userId, $chatId, ?string $username, ?int $threadId = null, ?string $displayName = null) {
        switch ($command) {
            case '/game':
                return $this->handleStart($userId, $chatId, $threadId, $username, $displayName);
            case '/help':
            case '/howtoplay':
                return $this->handleHelp($userId, $chatId, $threadId, $username, $displayName);
            case '/frens':
                return $this->handleWhosPlaying($userId, $chatId, $threadId, $username, $displayName);
            case '/leaderboard':
                return $this->handleLeaderboard($userId, $chatId, $threadId, $username, $displayName);
            case '/resetall':
                return $this->handleResetAll($userId, $chatId, $threadId, $username, $displayName);
            case '/resetcache':
                return $this->handleResetCache($userId, $chatId, $threadId, $username, $displayName);
            default:
                if (strpos($command, '/debug') === 0 && in_array($userId, ADMIN_USER_IDS)) {
                    return $this->handleDebugCommand($command, $parameters, $userId, $chatId, $threadId, $username, $displayName);
                }
                return false;
        }
    }

    // =========================================================================
    // CALLBACK QUERY HANDLER
    // =========================================================================

    private function handleCallbackQuery(array $callbackQuery) {
        $data        = $callbackQuery['data'];
        $userId      = (int) $callbackQuery['from']['id'];
        $username    = $callbackQuery['from']['username'] ?? 'Unknown';
        $displayName = $this->getDisplayNameFromFrom($callbackQuery['from'], $username);
        $chatId      = $callbackQuery['message']['chat']['id'];
        $messageId   = $callbackQuery['message']['message_id'];
        $threadId    = $callbackQuery['message']['message_thread_id'] ?? null;
        $callbackId  = $callbackQuery['id'];

        // Rate limit check
        if ($this->isRateLimited($userId)) {
            $this->telegram->answerCallbackQuery($callbackId);
            return false;
        }

        $this->log("Callback from user $userId: $data", 'DEBUG');
        $this->logUserInteraction('callback_received', $userId, $chatId, $username, $data, $threadId);

        // Parse "category|action|param[|owner]" — owner segment (if present) is "u<userId>"
        $parts = explode('|', $data);

        $ownerId = null;
        $lastIdx = count($parts) - 1;
        if ($lastIdx >= 0) {
            $last = $parts[$lastIdx];
            if (is_string($last) && strlen($last) > 1 && $last[0] === 'u' && ctype_digit(substr($last, 1))) {
                $ownerId = (int) substr($last, 1);
                array_pop($parts);
            }
        }

        $category = $parts[0] ?? '';
        $action   = $parts[1] ?? '';
        $param    = $parts[2] ?? '';

        // Determine which user ID owns the underlying session for this callback:
        // - If callback_data has an owner segment (u<id>), that ID owns the session
        // - Otherwise, the clicking user owns the session
        $sessionUserId = $ownerId !== null ? $ownerId : $userId;

        // Enforce per-message ownership:
        // - For normal players, only the owner may press their buttons
        // - For group-anonymous owner (1087968824), allow any user to play that shared game
        if ($ownerId !== null && $ownerId !== 1087968824 && $userId !== $ownerId) {
            $this->log("Unauthorized callback press by user $userId for owner $ownerId: $data", 'WARNING');

            // Get owner's display name for clearer error message
            $ownerLabel = "another player";
            if ($ownerId) {
                $ownerSession = $this->loadSession($ownerId);
                if ($ownerSession) {
                    // Prefer display_name, fallback to username, then user ID
                    if (isset($ownerSession['display_name']) && $ownerSession['display_name']) {
                        $ownerLabel = $ownerSession['display_name'];
                    } elseif (isset($ownerSession['username']) && $ownerSession['username']) {
                        $ownerLabel = $ownerSession['username'];
                    } else {
                        $ownerLabel = "Player ID: $ownerId";
                    }
                }
            }

            $this->telegram->answerCallbackQuery(
                $callbackId,
                "❌ These buttons belong to $ownerLabel! Start your own game with /game",
                true
            );
            return false;
        }

        // Authorized callback (or shared group-anonymous game) — acknowledge to clear spinner.
        $this->telegram->answerCallbackQuery($callbackId);

        // Delete the message whose button was pressed (only for authorized callbacks)
        $this->telegram->deleteMessage($chatId, $messageId);

        // Set callback reply context so sendMessage() targets this chat regardless
        // of the player's chat preference. Cleared in the finally block.
        $this->callbackReplyChatId   = $chatId;
        $this->callbackReplyThreadId = $threadId;

        try {
            switch ($category) {
                case 'menu':
                    return $this->handleMenuCallback($action, $sessionUserId, $chatId, $messageId, $threadId, $username, $displayName);
                case 'event':
                    return $this->handleEventCallback($action, $param, $sessionUserId, $chatId, $messageId, $threadId, $username, $displayName);
                case 'shop':
                    return $this->handleShopCallback($action, $param, $sessionUserId, $chatId, $messageId, $threadId, $username, $displayName);
                case 'station':
                    return $this->handleStationCallback($action, $param, $sessionUserId, $chatId, $messageId, $threadId, $username, $displayName);
                case 'action':
                    return $this->handleActionCallback($action, $sessionUserId, $chatId, $messageId, $threadId, $username, $displayName);
                case 'admin':
                    return $this->handleAdminCallback($action, $param, $sessionUserId, $chatId, $messageId, $threadId, $username, $displayName);
                case 'aesthetic':
                    // Aesthetic selection: action is the aesthetic ID
                    if ($this->game->getState($sessionUserId) !== null) {
                        $this->prefs->set($sessionUserId, ['aesthetic' => $action]);

                        // Refresh aesthetic view to show new selection
                        $session = $this->loadSession($sessionUserId);
                        $view = $this->viewManager->render('aesthetic', $session);
                        return $this->sendMessage($chatId, $view['message'], $view['image_url'], $view['keyboard'], $sessionUserId, $threadId, $username, $displayName);
                    }
                    return false;
                default:
                    $this->log("Unknown callback category: $category", 'WARNING');
                    return false;
            }
        } finally {
            $this->callbackReplyChatId   = null;
            $this->callbackReplyThreadId = null;
        }
    }

    // =========================================================================
    // COMMAND HANDLERS
    // =========================================================================

    /** The player's game as the bot draws it (saved game plus the bot's aesthetic); display only. */
    private function loadSession(int $userId): ?array {
        return $this->botController->loadSession($userId);
    }

    private function handleStart(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        $session = $this->loadSession($userId);
        $view = $this->viewManager->render('start', $session, ['chatId' => $chatId]);
        return $this->sendMessage($chatId, $view['message'], $view['image_url'], $view['keyboard'], $userId, $threadId, $username, $displayName);
    }

    private function handleMainMenu(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        // Special case: if no session, redirect to start view
        $session = $this->loadSession($userId);
        if (!$session) {
            return $this->handleStart($userId, $chatId, $threadId, $username, $displayName);
        }

        return $this->routeViaArchitecture('main_menu', $userId, $chatId, ['chatId' => $chatId], $threadId, $username, $displayName);
    }

    private function handleHelp(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        $message = $this->viewManager->formatHelpMessage();

        // Context-aware back button
        $session = $this->loadSession($userId);
        $isPreLaunch = $session !== null && ($session['status'] ?? '') === 'pre_launch';

        $keyboard = $this->viewManager->getHelpKeyboard($isPreLaunch);

        return $this->sendMessage($chatId, $message, null, $keyboard, $userId, $threadId, $username, $displayName);
    }

    /**
     * Handle aesthetic selection view.
     */
    private function handleAesthetic(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        return $this->routeViaArchitecture('aesthetic', $userId, $chatId, [], $threadId, $username, $displayName);
    }

    /**
     * Called from menu callback (New Journey button). Routes to difficulty selection.
     * If the player has an active session they will see a confirmation before it is abandoned.
     */
    private function handleNewGame(int $userId, $chatId, string $parameters, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        return $this->handleStartNewGame($userId, $chatId, $threadId, $username, $displayName);
    }

    /**
     * Route button press through unified architecture.
     *
     * @param string $button Button identifier (continue, journey, status, map, etc.)
     * @param int $userId User ID
     * @param mixed $chatId Chat ID
     * @param array $params Additional parameters for actions
     * @param int|null $threadId Thread ID (for groups)
     * @param string|null $username Username
     * @param string|null $displayName Display name
     * @return mixed Result from sendMessage
     */
    private function routeViaArchitecture(
        string $button,
        int $userId,
        $chatId,
        array $params = [],
        ?int $threadId = null,
        ?string $username = null,
        ?string $displayName = null
    ) {
        try {
            $result = $this->botController->route($button, $userId, $params);
            $session = $result['session'];
            $viewData = $result['view_data'];

            return $this->sendMessage(
                $chatId,
                $viewData['message'],
                $viewData['image_url'] ?? null,
                $viewData['keyboard'],
                $userId,
                $threadId,
                $username,
                $displayName
            );
        } catch (RuntimeException $e) {
            // No active session
            return $this->sendNoSessionMessage($chatId, null, $userId, $threadId, $username, $displayName);
        }
    }

    private function handleNewGameView(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        $session = $this->loadSession($userId);

        if ($session === null) {
            return $this->sendNoSessionMessage($chatId, "❌ No active journey. Start a new one with /game!", $userId, $threadId, $username, $displayName, false);
        }

        if ($session['status'] !== 'pre_launch') {
            return $this->sendMessage($chatId, "❌ Your journey has already launched!", null, $this->viewManager->getJourneyKeyboard($session), $userId, $threadId, $username, $displayName);
        }

        // Show new game view
        $newGameMessage = $this->viewManager->formatNewGameMessage();
        $keyboard = $this->viewManager->getNewGameViewKeyboard();
        $newGameImage = $this->viewManager->getContextImageUrl('newgame');

        return $this->sendMessage($chatId, $newGameMessage, $newGameImage, $keyboard, $userId, $threadId, $username, $displayName);
    }

    private function handleLaunch(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        // Validation: must be pre_launch status
        $session = $this->loadSession($userId);
        if ($session === null) {
            return $this->sendNoSessionMessage($chatId, "❌ No active journey. Start a new one with /game!", $userId, $threadId, $username, $displayName, false);
        }

        if ($session['status'] !== 'pre_launch') {
            return $this->sendMessage($chatId, "❌ Your journey has already launched!", null, $this->viewManager->getJourneyKeyboard($session), $userId, $threadId, $username, $displayName);
        }

        return $this->routeViaArchitecture('launch_sequence', $userId, $chatId, [], $threadId, $username, $displayName);
    }

    private function handleGo(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        // Launch the ship via ActionManager (standardized flash message handling)
        return $this->routeViaArchitecture('launch', $userId, $chatId, [], $threadId, $username, $displayName);
    }

    private function handleContinue(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        return $this->routeViaArchitecture('continue', $userId, $chatId, [], $threadId, $username, $displayName);
    }

    private function handleJourney(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        return $this->routeViaArchitecture('journey', $userId, $chatId, [], $threadId, $username, $displayName);
    }

    private function handleStatus(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        return $this->routeViaArchitecture('status', $userId, $chatId, [], $threadId, $username, $displayName);
    }

    /**
     * Show the solar system map. The Back button always returns to the Journey page.
     */
    private function handleMap(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        return $this->routeViaArchitecture('map', $userId, $chatId, [], $threadId, $username, $displayName);
    }

    private function handleShop(int $userId, $chatId, string $parameters, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        if (!FEATURE_SHOP) {
            return $this->sendMessage($chatId, "🛒 Shop feature coming soon!", null, null, $userId, $threadId, $username, $displayName);
        }

        return $this->routeViaArchitecture('shop', $userId, $chatId, [], $threadId, $username, $displayName);
    }

    private function handleRest(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        return $this->routeViaArchitecture('rest', $userId, $chatId, [], $threadId, $username, $displayName);
    }

    private function handleReturnConfirmation(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        // Validation: must be in_space and not at waypoint
        $session = $this->loadSession($userId);
        if ($session === null) {
            return $this->sendNoSessionMessage($chatId, null, $userId, $threadId, $username, $displayName);
        }

        if ($session['status'] !== 'in_space') {
            return $this->sendMessage($chatId, "❌ You can only return during your journey!", null, $this->viewManager->getJourneyKeyboard($session), $userId, $threadId, $username, $displayName);
        }

        if ($this->gameEngine->isAtWaypoint($session)) {
            return $this->sendMessage($chatId, "❌ You're already at a waypoint. Use Rest or Continue.", null, $this->viewManager->getJourneyKeyboard($session), $userId, $threadId, $username, $displayName);
        }

        return $this->routeViaArchitecture('return_confirm', $userId, $chatId, [], $threadId, $username, $displayName);
    }

    private function handleReturnToLastWaypoint(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        // Validation: must be in_space
        $session = $this->loadSession($userId);
        if ($session === null) {
            return $this->sendNoSessionMessage($chatId, null, $userId, $threadId, $username, $displayName);
        }

        if ($session['status'] !== 'in_space') {
            return $this->sendMessage($chatId, "❌ You can only return during your journey!", null, null, $userId, $threadId, $username, $displayName);
        }

        return $this->routeViaArchitecture('return_to_waypoint', $userId, $chatId, [], $threadId, $username, $displayName);
    }

    private function handleStation(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        // Validation: must be at station status
        $session = $this->loadSession($userId);
        if ($session === null) {
            return $this->sendNoSessionMessage($chatId, null, $userId, $threadId, $username, $displayName);
        }

        if ($session['status'] !== 'at_station') {
            return $this->sendMessage($chatId, "❌ You're not at a station!", null, $this->viewManager->getJourneyKeyboard($session), $userId, $threadId, $username, $displayName);
        }

        if (empty($session['at_waypoint'])) {
            return $this->sendMessage($chatId, "❌ No station data found!", null, $this->viewManager->getJourneyKeyboard($session), $userId, $threadId, $username, $displayName);
        }

        // Route to station view (ViewManager modifies session to track seen content, GameController saves it)
        return $this->routeViaArchitecture('station', $userId, $chatId, [], $threadId, $username, $displayName);
    }

    private function handleHunt(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        // Validation: must be in_space
        $session = $this->loadSession($userId);
        if ($session === null) {
            return $this->sendNoSessionMessage($chatId, null, $userId, $threadId, $username, $displayName);
        }

        if ($session['status'] !== 'in_space') {
            return $this->sendMessage($chatId, "❌ You can only hunt during your journey!", null, null, $userId, $threadId, $username, $displayName);
        }

        return $this->routeViaArchitecture('hunt', $userId, $chatId, [], $threadId, $username, $displayName);
    }

    /**
     * Show abandon confirmation rather than immediately deleting.
     * handleAbandonConfirm() handles the actual deletion.
     */
    private function handleAbandon(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        return $this->routeViaArchitecture('abandon', $userId, $chatId, [], $threadId, $username, $displayName);
    }

    /**
     * Actually perform the session deletion after the player has confirmed.
     */
    private function handleAbandonConfirm(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        $result = $this->game->act($userId, 'abandon', [], 'bot');

        if ($result['outcome'] === 'rejected') {
            // Already gone — just show main menu
            return $this->handleStart($userId, $chatId, $threadId, $username, $displayName);
        }

        $this->prefs->forgetChat($userId);

        $message = $this->viewManager->formatJourneyAbandonedMessage();
        $message .= $this->getGameIntroText() . "\n\n";

        return $this->sendMessage($chatId, $message, null, $this->viewManager->getStartViewKeyboard(false, $chatId), $userId, $threadId, $username, $displayName);
    }

    private function handleLeaderboard(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        if (!FEATURE_LEADERBOARDS) {
            return $this->sendMessage($chatId, "🏆 Leaderboards coming soon!", null, null, $userId, $threadId, $username, $displayName);
        }

        $leaderboard = $this->game->getLeaderboard();
        $message     = $this->viewManager->formatLeaderboard($leaderboard, $userId, $username);

        // Get image using ViewManager pattern (respects aesthetic, checks file existence)
        // Session may be null if user hasn't started a game - that's fine, image will use 'main' aesthetic
        $session = $this->loadSession($userId);
        $imageUrl = $this->viewManager->getContextImageUrl('leaderboard', $session);

        // Commands: message stays (no auto-delete), userId=null prevents message tracking
        // Buttons: message auto-deletes (replaces previous), userId passed for tracking
        $isCallback = ($this->callbackReplyChatId !== null);
        $trackingUserId = $isCallback ? $userId : null;

        return $this->sendMessage($chatId, $message, $imageUrl, null, $trackingUserId, $threadId, $username, $displayName);
    }

    private function handleWhosPlaying(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        // Get active players from SessionManager
        $players = $this->game->getActivePlayers(7);

        // Format message using ViewManager
        $message = $this->viewManager->formatWhosPlaying($players, $userId);

        // Get image using ViewManager pattern (similar to leaderboard)
        // Session may be null if user hasn't started a game - that's fine, image will use 'main' aesthetic
        $session = $this->loadSession($userId);
        $imageUrl = $this->viewManager->getContextImageUrl('whosplaying', $session);

        // Commands: message stays (no auto-delete), userId=null prevents message tracking
        // Buttons: message auto-deletes (replaces previous), userId passed for tracking
        $isCallback = ($this->callbackReplyChatId !== null);
        $trackingUserId = $isCallback ? $userId : null;

        return $this->sendMessage($chatId, $message, $imageUrl, null, $trackingUserId, $threadId, $username, $displayName);
    }

    private function handleResetAll(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        if (!in_array($userId, ADMIN_USER_IDS)) {
            $this->log("Non-admin user $userId attempted /resetall", 'WARNING');
            return $this->sendMessage($chatId, "❌ This command is only available to administrators.", null, null, $userId, $threadId, $username, $displayName);
        }

        $this->log("Admin $userId requested /resetall", 'WARNING');
        $sessionCount = $this->game->countGames();

        $message  = "⚠️ <b>ADMIN: RESET ALL DATA</b> ⚠️\n\n";
        $message .= "This will DELETE:\n";
        $message .= "• All active game sessions ($sessionCount active)\n";
        $message .= "• All leaderboard data\n";
        $message .= "• Image cache (file_id cache)\n";
        $message .= "• Message ID tracking files\n";
        $message .= "• Application logs\n";
        $message .= "• Diagnostic logs\n";
        $message .= "• Telemetry logs\n\n";
        $message .= "<b>This action CANNOT be undone!</b>\n";
        $message .= "<b>NO BACKUPS will be created!</b>\n\n";
        $message .= "Are you absolutely sure?";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🗑️ YES, RESET EVERYTHING', 'callback_data' => 'admin|resetall|confirm']],
                [['text' => '❌ Cancel',                  'callback_data' => 'admin|resetall|cancel']],
            ],
        ];

        // Pass null for $userId so admin callbacks are not owner-locked; any admin in ADMIN_USER_IDS
        // can confirm or cancel, and non-admins are still blocked inside handleAdminCallback().
        return $this->sendMessage($chatId, $message, null, $keyboard, null, $threadId, $username, $displayName);
    }

    private function handleResetCache(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        if (!in_array($userId, ADMIN_USER_IDS)) {
            $this->log("Non-admin user $userId attempted /resetcache", 'WARNING');
            return $this->sendMessage($chatId, "❌ This command is only available to administrators.", null, null, $userId, $threadId, $username, $displayName);
        }

        $this->log("Admin $userId requested /resetcache", 'INFO');

        // Get cache stats
        $cacheFile = defined('IMAGE_CACHE_FILE') ? IMAGE_CACHE_FILE : (DATA_DIR . '/image_cache.json');
        $cacheExists = file_exists($cacheFile);
        $cacheSize = 0;
        $entryCount = 0;

        if ($cacheExists) {
            $cacheSize = filesize($cacheFile);
            $cacheData = json_decode(file_get_contents($cacheFile), true);
            $entryCount = is_array($cacheData) ? count($cacheData) : 0;
        }

        $message  = "🗑️ <b>ADMIN: RESET IMAGE CACHE</b>\n\n";
        $message .= "<b>Current Cache:</b>\n";
        $message .= "• Entries: $entryCount cached images\n";
        $message .= "• File size: " . round($cacheSize / 1024, 2) . " KB\n";
        $message .= "• Location: <code>data/image_cache.json</code>\n\n";
        $message .= "<b>This will:</b>\n";
        $message .= "• Clear all cached Telegram file_ids\n";
        $message .= "• Force fresh image uploads on next use\n";
        $message .= "• Not affect game data or sessions\n\n";
        $message .= "Are you sure?";

        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🗑️ YES, CLEAR CACHE', 'callback_data' => 'admin|resetcache|confirm']],
                [['text' => '❌ Cancel',           'callback_data' => 'admin|resetcache|cancel']],
            ],
        ];

        return $this->sendMessage($chatId, $message, null, $keyboard, null, $threadId, $username, $displayName);
    }

    private function handlePrivateDM(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        $session = $this->loadSession($userId);

        // Check if game is already in private mode
        if ($session !== null && $this->prefs->preferredChat($userId) === 'private') {
            return $this->sendMessage($chatId, "✅ Your game is already in private mode! Use /game in your DMs to continue.", null, null, $userId, $threadId, $username, $displayName);
        }

        if ($session === null) {
            $message = $this->viewManager->formatPrivateDMPrompt();

            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '✅ Yes, Start Private Game', 'callback_data' => 'menu|confirmprivatedm']],
                    [['text' => '❌ Cancel',                   'callback_data' => 'menu|main']],
                ],
            ];

            return $this->sendMessage($chatId, $message, null, $keyboard, $userId, $threadId, $username, $displayName);
        }

        $this->prefs->setPrivate($userId, $chatId);

        $message = $this->viewManager->formatGameMovedToPrivateMessage();

        $this->sendMessage($chatId, $message, null, null, $userId, $threadId, $username, $displayName);

        $privateMessage = $this->viewManager->formatWelcomeToPrivateModeMessage();

        return $this->sendMessageToChat($userId, $privateMessage, $this->viewManager->getJourneyKeyboard($session), $userId, null, $username, $displayName);
    }

    private function handleConfirmPrivateDM(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        $message = $this->viewManager->formatStartingPrivateGameMessage();

        $this->sendMessage($chatId, $message, null, null, $userId, $threadId, $username, $displayName);

        // Create game in private mode
        return $this->createNewGameSession($userId, $userId, null, $username, $displayName);
    }

    private function handleConfirmGroupGame(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        // Create game in group mode
        return $this->createNewGameSession($userId, $chatId, $threadId, $username, $displayName);
    }

    private function handleStartNewGame(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        // If the user has an active session, show confirmation before abandoning it
        $session = $this->loadSession($userId);
        if ($session !== null) {
            $day      = $session['progress']['day'];
            $distance = round($session['progress']['distance'] / 1000000);

            $message = $this->viewManager->formatActiveJourneyNewGameWarning($day, $distance);
            $message .= "Are you sure?";

            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '🗑️ Yes, Start New Game', 'callback_data' => 'menu|abandonnewgame']],
                    [['text' => '↩️ Keep Current Game',    'callback_data' => 'menu|continue']],
                ],
            ];

            return $this->sendMessage($chatId, $message, null, $keyboard, $userId, $threadId, $username, $displayName);
        }

        // Create new game with default normal difficulty
        return $this->createNewGameSession($userId, $chatId, $threadId, $username, $displayName);
    }

    private function handleBackToGroup(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        $session = $this->loadSession($userId);

        if ($session === null) {
            return $this->sendNoSessionMessage($chatId, "❌ No active journey. Start a new one with /game!", $userId, $threadId, $username, $displayName, false);
        }

        if ($this->prefs->preferredChat($userId) !== 'private') {
            return $this->sendMessage($chatId, "✅ Your game is already in group mode!", null, null, $userId, $threadId, $username, $displayName);
        }

        $originalChatId = $this->prefs->originalChatId($userId) ?? $chatId;

        $this->prefs->setGroup($userId);

        $message = $this->viewManager->formatGameMovedBackToGroupMessage();

        $this->sendMessage($chatId, $message, null, null, $userId, $threadId, $username, $displayName);

        $groupMessage = $this->viewManager->formatGameReturnedToGroupMessage($username);

        return $this->sendMessageToChat($originalChatId, $groupMessage, $this->viewManager->getJourneyKeyboard($session), $userId, null, $username, $displayName);
    }

    // =========================================================================
    // CALLBACK HANDLERS
    // =========================================================================

    private function handleMenuCallback(string $action, int $userId, $chatId, int $messageId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        switch ($action) {
            case 'newgame':
            case 'startnewgame':
                return $this->handleStartNewGame($userId, $chatId, $threadId, $username, $displayName);
            case 'abandonnewgame':
                // User confirmed they want to abandon and start new (createNewGameSession abandons the old one)
                return $this->createNewGameSession($userId, $chatId, $threadId, $username, $displayName);
            case 'continue':
                // Context-aware: show launch screen if pre_launch, otherwise show journey
                $session = $this->loadSession($userId);
                if ($session && ($session['status'] ?? '') === 'pre_launch') {
                    return $this->handleNewGameView($userId, $chatId, $threadId, $username, $displayName);
                }
                // From menus/prompts this should show the current day without advancing time
                return $this->handleJourney($userId, $chatId, $threadId, $username, $displayName);
            case 'status':
                return $this->handleStatus($userId, $chatId, $threadId, $username, $displayName);
            case 'journey':
                // Context-aware: show launch screen if pre_launch, otherwise show journey
                $session = $this->loadSession($userId);
                if ($session && ($session['status'] ?? '') === 'pre_launch') {
                    return $this->handleNewGameView($userId, $chatId, $threadId, $username, $displayName);
                }
                return $this->handleJourney($userId, $chatId, $threadId, $username, $displayName);
            case 'map':
                return $this->handleMap($userId, $chatId, $threadId, $username, $displayName);
            case 'mapback_day':
                return $this->handleJourney($userId, $chatId, $threadId, $username, $displayName);
            case 'aesthetic':
                return $this->handleAesthetic($userId, $chatId, $threadId, $username, $displayName);
            case 'help':
                return $this->handleHelp($userId, $chatId, $threadId, $username, $displayName);
            case 'leaderboard':
                return $this->handleLeaderboard($userId, $chatId, $threadId, $username, $displayName);
            case 'whosplaying':
                return $this->handleWhosPlaying($userId, $chatId, $threadId, $username, $displayName);
            case 'privatedm':
                return $this->handlePrivateDM($userId, $chatId, $threadId, $username, $displayName);
            case 'backtogroup':
                return $this->handleBackToGroup($userId, $chatId, $threadId, $username, $displayName);
            case 'abandon':
                return $this->handleAbandon($userId, $chatId, $threadId, $username, $displayName);
            case 'abandonconfirm':
                return $this->handleAbandonConfirm($userId, $chatId, $threadId, $username, $displayName);
            case 'confirmprivatedm':
                return $this->handleConfirmPrivateDM($userId, $chatId, $threadId, $username, $displayName);
            case 'confirmgroupgame':
                return $this->handleConfirmGroupGame($userId, $chatId, $threadId, $username, $displayName);
            case 'main':
                return $this->handleMainMenu($userId, $chatId, $threadId, $username, $displayName);
            case 'launch':
                return $this->handleNewGameView($userId, $chatId, $threadId, $username, $displayName);
            default:
                return false;
        }
    }

    private function handleActionCallback(string $action, int $userId, $chatId, int $messageId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        switch ($action) {
            case 'launch':
                return $this->handleLaunch($userId, $chatId, $threadId, $username, $displayName);
            case 'go':
                return $this->handleGo($userId, $chatId, $threadId, $username, $displayName);
            case 'continue':
                return $this->handleContinue($userId, $chatId, $threadId, $username, $displayName);
            case 'status':
                return $this->handleStatus($userId, $chatId, $threadId, $username, $displayName);
            case 'journey':
                return $this->handleJourney($userId, $chatId, $threadId, $username, $displayName);
            case 'map':
                return $this->handleMap($userId, $chatId, $threadId, $username, $displayName);
            case 'shop':
                return $this->handleShop($userId, $chatId, '', $threadId, $username, $displayName);
            case 'rest':
                return $this->handleRest($userId, $chatId, $threadId, $username, $displayName);
            case 'return':
                return $this->handleReturnConfirmation($userId, $chatId, $threadId, $username, $displayName);
            case 'return_confirm':
                return $this->handleReturnToLastWaypoint($userId, $chatId, $threadId, $username, $displayName);
            case 'hunt':
                return $this->handleHunt($userId, $chatId, $threadId, $username, $displayName);
            case 'station':
                return $this->handleStation($userId, $chatId, $threadId, $username, $displayName);
            case 'mystats':
                return $this->sendMessage($chatId, "📊 Player stats coming soon!", null, null, $userId, $threadId, $username, $displayName);
            case 'share':
                return $this->sendMessage($chatId, "📤 Share feature coming soon!", null, null, $userId, $threadId, $username, $displayName);
            default:
                return false;
        }
    }

    /**
     * Create a new game session with default normal difficulty.
     * Extracted from handleDifficultyCallback to simplify game creation flow.
     */
    private function createNewGameSession(int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        // Starting over replaces any game the player already has
        if ($this->game->getState($userId) !== null) {
            $this->game->act($userId, 'abandon', [], 'bot');
        }

        $result = $this->game->act($userId, 'new_game', ['username' => $username, 'display_name' => $displayName], 'bot');
        $this->prefs->setChatFromStart($userId, $chatId);
        $this->log("Created session {$result['session']['session_id']} for user $userId, chat: " . $this->prefs->preferredChat($userId), 'DEBUG');

        // Show new game view with Launch Ship button
        $newGameMessage = $this->viewManager->formatNewGameMessage();
        $keyboard = $this->viewManager->getNewGameViewKeyboard();
        $newGameImage = $this->viewManager->getContextImageUrl('newgame');

        return $this->sendMessage($chatId, $newGameMessage, $newGameImage, $keyboard, $userId, $threadId, $username, $displayName);
    }

    private function handleEventCallback(string $eventId, string $choiceId, int $userId, $chatId, int $messageId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        return $this->routeViaArchitecture('event_choice', $userId, $chatId, ['event_id' => $eventId, 'choice_id' => $choiceId], $threadId, $username, $displayName);
    }

    private function handleShopCallback(string $item, $quantity, int $userId, $chatId, int $messageId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        if ($this->game->getState($userId) === null) {
            return $this->sendNoSessionMessage($chatId, "❌ Session expired. Start a new game with /game", $userId, $threadId, $username, $displayName, false);
        }

        if ($item === 'launch') {
            return $this->routeViaArchitecture('launch', $userId, $chatId, [], $threadId, $username, $displayName);
        }

        // Other shop purchases (fuel, food, repair)
        $result = $this->game->act($userId, 'purchase', ['item' => $item], 'bot');
        $session = $this->loadSession($userId);

        $message = $this->viewManager->formatPurchaseResult(['success' => $result['outcome'] === 'purchased'] + $result['data'], $session);
        $keyboard = $this->viewManager->getShopKeyboard($session);

        return $this->sendMessage($chatId, $message, null, $keyboard, $userId, $threadId, $username, $displayName);
    }

    private function handleStationCallback(string $action, string $param, int $userId, $chatId, int $messageId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        $session = $this->loadSession($userId);

        if ($session === null) {
            return $this->sendNoSessionMessage($chatId, "❌ Session expired. Start a new game with /game", $userId, $threadId, $username, $displayName, false);
        }

        if (empty($session['pending_station_arrival'])) {
            return $this->sendMessage($chatId, "❌ No waypoint arrival pending.", null, $this->viewManager->getJourneyKeyboard($session), $userId, $threadId, $username, $displayName);
        }

        if ($action === 'stop') {
            return $this->routeViaArchitecture('stop_at_waypoint', $userId, $chatId, [], $threadId, $username, $displayName);
        }
        if ($action === 'skip') {
            return $this->routeViaArchitecture('skip_waypoint', $userId, $chatId, [], $threadId, $username, $displayName);
        }

        $this->log("Unknown station action: $action", 'WARNING');
        return false;
    }

    private function handleAdminCallback(string $action, string $param, int $userId, $chatId, int $messageId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        if (!in_array($userId, ADMIN_USER_IDS)) {
            $this->log("Non-admin user $userId attempted admin callback: $action", 'WARNING');
            return $this->sendMessage($chatId, "❌ Unauthorized.", null, null, $userId, $threadId, $username, $displayName);
        }

        if ($action === 'resetall') {
            if ($param === 'confirm') {
                return $this->executeResetAll($userId, $chatId, $threadId, $username, $displayName);
            }
            if ($param === 'cancel') {
                return $this->sendMessage($chatId, "✅ Reset cancelled. No data was modified.", null, null, $userId, $threadId, $username, $displayName);
            }
        }

        if ($action === 'resetuser') {
            if (strpos($param, 'confirm_') === 0) {
                $targetUserId = (int) str_replace('confirm_', '', $param);
                return $this->executeResetUser($userId, $targetUserId, $chatId, $threadId, $username, $displayName);
            }
            if ($param === 'cancel') {
                return $this->sendMessage($chatId, "✅ User reset cancelled.", null, null, $userId, $threadId, $username, $displayName);
            }
        }

        if ($action === 'resetcache') {
            if ($param === 'confirm') {
                return $this->executeResetCache($userId, $chatId, $threadId, $username, $displayName);
            }
            if ($param === 'cancel') {
                return $this->sendMessage($chatId, "✅ Cache reset cancelled.", null, null, $userId, $threadId, $username, $displayName);
            }
        }

        return false;
    }

    // =========================================================================
    // ADMIN OPERATIONS
    // =========================================================================

    private function executeResetAll(int $adminUserId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        $this->log("Admin $adminUserId executing full data reset", 'WARNING');

        $results = [];
        $errors  = [];

        $sessionCount = $this->game->countGames();

        // Delete all saved games (and the bot's own per-player preferences)
        $deleted = $this->game->deleteAllGames();
        $this->prefs->clearAll();
        $results[] = "✅ Deleted $deleted session file(s)";

        // Reset leaderboards
        $emptyLeaderboards = ['fastest_journeys' => [], 'most_chad_saved' => [], 'player_stats' => []];
        if (file_put_contents(LEADERBOARD_FILE, json_encode($emptyLeaderboards, JSON_PRETTY_PRINT))) {
            $results[] = "✅ Reset leaderboards";
        } else {
            $errors[] = "❌ Failed to reset leaderboards.json";
        }

        // Clear application log
        if (file_exists(LOG_FILE) && file_put_contents(LOG_FILE, '') !== false) {
            $results[] = "✅ Cleared application log";
        }

        // Clear diagnostic log
        if (file_exists(DIAGNOSTIC_LOG) && file_put_contents(DIAGNOSTIC_LOG, '') !== false) {
            $results[] = "✅ Cleared diagnostic log";
        }

        // Clear telemetry log
        if (file_exists(TELEMETRY_FILE) && @unlink(TELEMETRY_FILE)) {
            $results[] = "✅ Deleted telemetry log";
        }

        // Delete image cache
        $cacheFile = defined('IMAGE_CACHE_FILE') ? IMAGE_CACHE_FILE : (DATA_DIR . '/image_cache.json');
        if (file_exists($cacheFile) && @unlink($cacheFile)) {
            $results[] = "✅ Deleted image cache";
        }

        // Delete all per-user message ID files
        if (is_dir(MESSAGE_IDS_DIR)) {
            $msgFiles = glob(MESSAGE_IDS_DIR . '/*.json');
            $msgDeleted = 0;
            foreach ($msgFiles as $file) {
                if (@unlink($file)) {
                    $msgDeleted++;
                }
            }
            if ($msgDeleted > 0) {
                $results[] = "✅ Deleted $msgDeleted per-user message ID file(s)";
            }
        }

        $message  = "🗑️ <b>RESET COMPLETE</b>\n\n";
        $message .= "👤 <b>Admin ID:</b> $adminUserId\n\n";

        if (!empty($results)) {
            $message .= "<b>Success:</b>\n" . implode("\n", $results) . "\n";
        }
        if (!empty($errors)) {
            $message .= "\n<b>Warnings:</b>\n" . implode("\n", $errors) . "\n";
        }

        $message .= "\n<i>All runtime data has been deleted. Players will need to start new games.</i>";

        $this->log("FULL RESET executed by admin $adminUserId — $sessionCount sessions cleared");

        return $this->sendMessage($chatId, $message, null, null, $adminUserId, $threadId, $username, $displayName);
    }

    /**
     * @param int         $adminUserId  The admin performing the reset
     * @param int         $targetUserId The user whose session is being reset
     * @param int|string  $chatId
     * @param int|null    $threadId
     * @param string|null $username     Admin's username for message addressing
     */
    private function executeResetUser(int $adminUserId, int $targetUserId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        $this->log("Admin $adminUserId executing reset for user $targetUserId", 'WARNING');

        $session = $this->game->getState($targetUserId);

        if ($session === null) {
            $message  = "❌ <b>Reset Failed</b>\n\n";
            $message .= "User $targetUserId no longer has an active session.\n";
            $message .= "They may have already finished or abandoned their game.";
            return $this->sendMessage($chatId, $message, null, null, $adminUserId, $threadId, $username, $displayName);
        }

        $day      = $session['progress']['day'];
        $distance = round($session['progress']['distance'] / 1000000);

        if ($this->game->deleteGame($targetUserId)) {
            $this->prefs->forgetChat($targetUserId);
            $message  = "✅ <b>USER RESET COMPLETE</b>\n\n";
            $message .= "👤 <b>Admin ID:</b> $adminUserId\n";
            $message .= "🎯 <b>Target User ID:</b> $targetUserId\n\n";
            $message .= "<b>Deleted Session:</b>\n";
            $message .= "📅 Day: $day\n";
            $message .= "📍 Distance: {$distance}M km\n\n";
            $message .= "✅ User can now start a new game.";

            $this->log("User $targetUserId session reset by admin $adminUserId (Day $day, {$distance}M km)");
        } else {
            $message  = "❌ <b>Reset Failed</b>\n\n";
            $message .= "Failed to delete session for user $targetUserId.\n";
            $message .= "Check file permissions on " . SESSIONS_DIR;

            $this->log("ERROR: Failed to reset user $targetUserId session", 'ERROR');
        }

        return $this->sendMessage($chatId, $message, null, null, $adminUserId, $threadId, $username, $displayName);
    }

    /**
     * Clear the Telegram image cache (file_id cache).
     *
     * @param int         $adminUserId The admin performing the reset
     * @param int|string  $chatId
     * @param int|null    $threadId
     * @param string|null $username
     * @param string|null $displayName
     */
    private function executeResetCache(int $adminUserId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        $this->log("Admin $adminUserId executing image cache reset", 'INFO');

        $cacheFile = defined('IMAGE_CACHE_FILE') ? IMAGE_CACHE_FILE : (DATA_DIR . '/image_cache.json');
        $cacheExists = file_exists($cacheFile);
        $entriesCleared = 0;
        $sizeCleared = 0;

        // Get stats before clearing
        if ($cacheExists) {
            $sizeCleared = filesize($cacheFile);
            $cacheData = json_decode(file_get_contents($cacheFile), true);
            $entriesCleared = is_array($cacheData) ? count($cacheData) : 0;
        }

        // Clear the cache
        $success = false;
        if ($cacheExists) {
            $success = @unlink($cacheFile);
        } else {
            $success = true; // No file to delete = success
        }

        // Build response message
        if ($success) {
            $message  = "✅ <b>IMAGE CACHE CLEARED</b>\n\n";
            $message .= "👤 <b>Admin ID:</b> $adminUserId\n\n";
            $message .= "<b>Cache Statistics:</b>\n";
            $message .= "• Entries cleared: $entriesCleared images\n";
            $message .= "• Size freed: " . round($sizeCleared / 1024, 2) . " KB\n\n";
            $message .= "✅ All cached file_ids have been cleared.\n";
            $message .= "Images will be re-uploaded on next use.";

            $this->log("Image cache cleared by admin $adminUserId ($entriesCleared entries, " . round($sizeCleared / 1024, 2) . " KB)");
        } else {
            $message  = "❌ <b>Cache Reset Failed</b>\n\n";
            $message .= "Failed to delete cache file.\n";
            $message .= "Check file permissions on: <code>$cacheFile</code>";

            $this->log("ERROR: Failed to clear image cache", 'ERROR');
        }

        return $this->sendMessage($chatId, $message, null, null, $adminUserId, $threadId, $username, $displayName);
    }

    // =========================================================================
    // MESSAGE ROUTING & SENDING
    // =========================================================================

    /**
     * Resolve the target chat ID based on the player's chat preference.
     *
     * @param  int         $userId
     * @param  int|string  $currentChatId  The chat where the command was received
     * @param  string|null $preferredChat  'private', 'group', or null (use $currentChatId)
     * @return int|string
     */
    private function resolveTargetChat(int $userId, $currentChatId, ?string $preferredChat = null): mixed {
        if ($preferredChat === 'private') {
            $this->log("Routing to private chat for userId: $userId", 'DEBUG');
            return $userId;
        }
        return $currentChatId;
    }

    /**
     * Build display name from Telegram "from" object (first_name + last_name).
     * Returns fallback (e.g. username) when name is empty.
     *
     * @param  array  $from  Telegram message/callback "from" object
     * @param  string $fallbackUsername  Username to use when name is empty
     * @return string
     */
    private function getDisplayNameFromFrom(array $from, string $fallbackUsername): string {
        $name = trim(($from['first_name'] ?? '') . ' ' . ($from['last_name'] ?? ''));
        return $name !== '' ? $name : $fallbackUsername;
    }

    /**
     * Unified message sender.
     *
     * Handles: preferred_chat routing, GAMER header (group and DM), previous
     * message cleanup, image vs text dispatch, callback_data owner stamping with
     * 64-byte validation, and message ID storage for next cleanup.
     *
     * @param  int|string    $chatId         Originating chat (may be overridden by preferred_chat)
     * @param  string        $text           HTML message body
     * @param  string|null   $imageUrl       Telegram file_id or path to local image (null = text-only)
     * @param  array|null    $keyboard       Inline keyboard array (not pre-encoded)
     * @param  int|null      $userId         Used for cleanup, routing, and username header
     * @param  int|null      $threadId       Forum topic thread ID
     * @param  string|null   $username       @handle; fallback for GAMER header when displayName empty
     * @param  string|null   $displayName    Preferred label in group chats (first name / display name)
     * @return array|false
     */
    private function sendMessage($chatId, string $text, ?string $imageUrl = null, ?array $keyboard = null, $userId = null, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        // Callbacks always respond to the chat where the button was pressed
        if ($this->callbackReplyChatId !== null) {
            $targetChatId = $this->callbackReplyChatId;
            $threadId     = $this->callbackReplyThreadId ?? $threadId;
        } elseif ($userId !== null) {
            $targetChatId = $this->resolveTargetChat((int) $userId, $chatId, $this->prefs->preferredChat((int) $userId));
        } else {
            $targetChatId = $chatId;
        }

        // Stamp per-message owner into all callback_data values so only that user can press them.
        $keyboard = $this->stampOwnerIntoKeyboard($keyboard, $userId !== null ? (int) $userId : null);

        // GAMER header — show display name when available, else @username, else userId (both group and DM)
        // In group chats, make player identification PROMINENT to avoid confusion when multiple players are active
        $isGroupChat = ($targetChatId < 0);
        $label = null;
        if ($displayName !== null && $displayName !== '' && $displayName !== 'Unknown' && $displayName !== 'GroupAnonymousBot') {
            $label = $displayName;
        } elseif ($username && $username !== 'Unknown' && $username !== 'GroupAnonymousBot') {
            $label = $username;
        } elseif ($userId) {
            $label = $userId;
        }

        if ($label !== null) {
            if ($isGroupChat) {
                // In groups: PROMINENT header with visual separator
                $text = "━━━━━━━━━━━━━━━\n" .
                        "👤 <b>PLAYER:</b> " . $label . "\n" .
                        "━━━━━━━━━━━━━━━\n\n" .
                        $text .
                        "\n\n<i>⚠️ Only " . $label . " can use these buttons</i>";
            } else {
                // In DMs: Simple header (no confusion possible)
                $text = "👤 <b>GAMER:</b> " . $label . "\n\n" . $text;
            }
        }

        // Delete the previous bot message in this conversation slot
        if ($userId !== null) {
            $this->deleteLastMessage($userId, $targetChatId, $threadId);
        }

        // Send (photo with caption, or text-only)
        $result = false;
        if ($imageUrl !== null && $imageUrl !== '') {
            // Telegram caption limit is 1024 characters; truncate to avoid silent API failure
            $caption = mb_strlen($text) > 1024 ? mb_substr($text, 0, 1021) . '…' : $text;
            $this->log("Sending photo: $imageUrl", 'DEBUG');
            self::logDiagnostic("sendMessage: Calling sendPhoto to chat $targetChatId");
            $result = $this->telegram->sendPhoto($targetChatId, $imageUrl, $caption, $keyboard, $threadId);
            self::logDiagnostic("sendMessage: sendPhoto returned " . ($result ? 'success' : 'FALSE'));

            // TelegramAPI now handles file_id caching automatically

            // If photo send failed (bad URL, timeout, etc.), send text-only so user still sees the message
            if ($result === false) {
                $this->log("sendPhoto failed for chat $targetChatId, falling back to text-only", 'WARNING');
                self::logDiagnostic("sendMessage: sendPhoto FAILED, falling back to sendMessage");
                $result = $this->telegram->sendMessage($targetChatId, $text, $keyboard, $threadId);
                self::logDiagnostic("sendMessage: Fallback sendMessage returned " . ($result ? 'success' : 'FALSE'));
            }
        } else {
            self::logDiagnostic("sendMessage: Calling sendMessage (text-only) to chat $targetChatId");
            $result = $this->telegram->sendMessage($targetChatId, $text, $keyboard, $threadId);
            self::logDiagnostic("sendMessage: sendMessage returned " . ($result ? 'success' : 'FALSE'));
        }

        // Store new message ID for next cleanup cycle
        if ($result && isset($result['result']['message_id']) && $userId !== null) {
            $this->storeLastMessageId($userId, $targetChatId, (int) $result['result']['message_id'], $threadId);
            self::logDiagnostic("sendMessage: Stored message ID for cleanup");
        } elseif (!$result) {
            self::logDiagnostic("sendMessage: API call FAILED - returning false to caller");
        }

        return $result !== false;
    }

    /**
     * Send a message to a specific chat, bypassing normal routing logic.
     *
     * Use this method when you need to send to an explicit chat (e.g., switching
     * between DMs and group), but still want wrapper benefits:
     * - Owner stamping for callback_data
     * - Message tracking for cleanup
     * - GAMER header with user identification
     *
     * This method does NOT use callback reply context or preferred_chat routing.
     *
     * @param  int|string   $targetChatId  Explicit chat ID to send to
     * @param  string       $text          Message text (HTML formatted)
     * @param  array|null   $keyboard      Inline keyboard array
     * @param  int|null     $userId        User ID for owner stamping and tracking
     * @param  int|null     $threadId      Forum thread ID (if applicable)
     * @param  string|null  $username      User's @username for GAMER header
     * @param  string|null  $displayName   User's display name for GAMER header
     * @return array|false
     */
    private function sendMessageToChat($targetChatId, string $text, ?array $keyboard = null, ?int $userId = null, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        // Stamp per-message owner into all callback_data values
        $keyboard = $this->stampOwnerIntoKeyboard($keyboard, $userId);

        // GAMER header — show display name when available, else @username, else userId
        // In group chats, make player identification PROMINENT to avoid confusion when multiple players are active
        $isGroupChat = ($targetChatId < 0);
        $label = null;
        if ($displayName !== null && $displayName !== '' && $displayName !== 'Unknown' && $displayName !== 'GroupAnonymousBot') {
            $label = $displayName;
        } elseif ($username && $username !== 'Unknown' && $username !== 'GroupAnonymousBot') {
            $label = $username;
        } elseif ($userId) {
            $label = $userId;
        }

        if ($label !== null) {
            if ($isGroupChat) {
                // In groups: PROMINENT header with visual separator
                $text = "━━━━━━━━━━━━━━━\n" .
                        "👤 <b>PLAYER:</b> " . $label . "\n" .
                        "━━━━━━━━━━━━━━━\n\n" .
                        $text .
                        "\n\n<i>⚠️ Only " . $label . " can use these buttons</i>";
            } else {
                // In DMs: Simple header (no confusion possible)
                $text = "👤 <b>GAMER:</b> " . $label . "\n\n" . $text;
            }
        }

        // Delete the previous bot message in this conversation slot
        if ($userId !== null) {
            $this->deleteLastMessage($userId, $targetChatId, $threadId);
        }

        // Send the message
        $result = $this->telegram->sendMessage($targetChatId, $text, $keyboard, $threadId);

        // Store new message ID for next cleanup cycle
        if ($result && isset($result['result']['message_id']) && $userId !== null) {
            $this->storeLastMessageId($userId, $targetChatId, (int) $result['result']['message_id'], $threadId);
        }

        return $result !== false;
    }

    // =========================================================================
    // DISPLAY HELPERS
    // =========================================================================

    /**
     * Send a "no active session" message. Use when a handler requires a session but none exists.
     *
     * @param  int|string  $chatId
     * @param  string|null $message     Override message (default: "No active journey. Start a new one!")
     * @param  int|null    $userId
     * @param  int|null    $threadId
     * @param  string|null $username
     * @param  string|null $displayName
     * @param  bool        $withKeyboard  Include New Journey button (default true)
     * @return array|false
     */
    private function sendNoSessionMessage($chatId, ?string $message = null, $userId = null, ?int $threadId = null, ?string $username = null, ?string $displayName = null, bool $withKeyboard = true) {
        $message = $message ?? "❌ No active journey. Start a new one!";
        $keyboard = $withKeyboard ? $this->viewManager->getNewJourneyButtonKeyboard() : null;
        return $this->sendMessage($chatId, $message, null, $keyboard, $userId, $threadId, $username, $displayName);
    }

    /**
     * Shared intro text for the game (welcome and post-abandon screens).
     *
     * @return string
     */
    private function getGameIntroText(): string {
        return "Guide your chad 2.8 billion kilometers through space to establish " .
               "humanity's first colony on Uranus.";
    }

    /**
     * Stamp owner ID into all callback_data buttons and validate 64-byte limit.
     * Returns the modified keyboard (or null). Used by sendMessage and handleMap.
     *
     * @param  array|null  $keyboard
     * @param  int|null    $userId
     * @return array|null
     */
    private function stampOwnerIntoKeyboard(?array $keyboard, ?int $userId): ?array {
        if ($keyboard === null || $userId === null || !isset($keyboard['inline_keyboard']) || !is_array($keyboard['inline_keyboard'])) {
            return $keyboard;
        }

        $maxBytes = defined('TELEGRAM_CALLBACK_DATA_MAX_BYTES') ? TELEGRAM_CALLBACK_DATA_MAX_BYTES : 64;
        $ownerSuffix = '|u' . $userId;

        foreach ($keyboard['inline_keyboard'] as &$row) {
            if (!is_array($row)) {
                continue;
            }
            foreach ($row as &$button) {
                if (!is_array($button) || !isset($button['callback_data'])) {
                    continue;
                }
                $data = $button['callback_data'];
                if (strpos($data, $ownerSuffix) !== false) {
                    continue;
                }
                $stamped = $data . $ownerSuffix;
                if (strlen($stamped) > $maxBytes) {
                    $this->log(
                        "callback_data would exceed {$maxBytes} bytes after owner stamp: " .
                        strlen($stamped) . " bytes ($stamped) — owner stamp skipped",
                        'ERROR'
                    );
                } else {
                    $button['callback_data'] = $stamped;
                }
            }
            unset($button);
        }
        unset($row);

        return $keyboard;
    }

    /**
     * Build the main menu inline keyboard.
     *
     * @param  bool   $hasActiveSession  True iff user has a game in progress
     * @param  int    $chatId            Current chat ID (negative = group, positive = private/DMs)
     * @return array
     */
    /**
     * Render an event into a message string and keyboard array.
     * Previously this block was duplicated twice in showDayResult().
     * Returns ['message' => string, 'keyboard' => array].
     *
     * @param  array       $event
     * @param  array       $session  For chad template replacement in enhanced events
     * @param  int|null    $userId
     * @param  string|null $username
     * @return array  ['message' => string, 'keyboard' => array]
     */
    private function renderEventMessage(array $event, array $session, $userId, ?string $username): array {
        if (isset($event['chad_reactions'])) {
            $message  = $this->viewManager->formatEnhancedEvent($event, $userId, $username, $session);
            $keyboard = ['inline_keyboard' => $this->viewManager->createEnhancedEventKeyboard($event)];
        } else {
            $message  = "🚨 <b>EVENT!</b> 🚨\n\n";
            $message .= "<b>" . $event['title'] . "</b>\n\n";
            $message .= $event['description'];

            $rows = [];
            $maxBytes = defined('TELEGRAM_CALLBACK_DATA_MAX_BYTES') ? TELEGRAM_CALLBACK_DATA_MAX_BYTES : 64;
            $ownerReserve = defined('CALLBACK_DATA_OWNER_STAMP_RESERVE') ? CALLBACK_DATA_OWNER_STAMP_RESERVE : 15;
            foreach ($event['choices'] as $choice) {
                $callbackData = "event|{$event['id']}|{$choice['id']}";
                if (strlen($callbackData) > ($maxBytes - $ownerReserve)) {
                    $this->log("callback_data exceeds limit after owner reserve: $callbackData (" . strlen($callbackData) . " bytes)", 'ERROR');
                    continue;
                }
                $rows[] = [['text' => $choice['text'], 'callback_data' => $callbackData]];
            }
            $keyboard = ['inline_keyboard' => $rows];
        }

        return ['message' => $message, 'keyboard' => $keyboard];
    }

    // =========================================================================
    // DEBUG COMMANDS (Admin only)
    // =========================================================================

    private function handleDebugCommand(string $command, string $parameters, int $userId, $chatId, ?int $threadId = null, ?string $username = null, ?string $displayName = null) {
        if (!FEATURE_DEBUG_COMMANDS) {
            return false;
        }

        if ($command === '/debug_userid') {
            $message  = "🔧 <b>DEBUG: User Information</b>\n\n";
            $message .= "Your User ID: <code>$userId</code>\n";
            $message .= "As Integer: " . $userId . "\n\n";
            $message .= "Admin IDs in config: " . json_encode(ADMIN_USER_IDS) . "\n";
            $message .= "Is Admin: " . (in_array($userId, ADMIN_USER_IDS) ? 'YES' : 'NO') . "\n\n";
            $message .= "<i>Add your User ID to ADMIN_USER_IDS in config.php if needed.</i>";
            return $this->sendMessage($chatId, $message, null, null, $userId, $threadId, $username, $displayName);
        }

        if ($command === '/debug_sessions') {
            $allSessions = $this->game->getAllGames();
            $message     = "🔧 <b>DEBUG: Active Sessions</b>\n\n";

            if (empty($allSessions)) {
                $message .= "No active sessions found.";
            } else {
                $message .= "Found " . count($allSessions) . " active session(s):\n\n";
                foreach ($allSessions as $sessionUserId => $session) {
                    $day      = $session['progress']['day'] ?? '?';
                    $distance = round(($session['progress']['distance'] ?? 0) / 1000000);
                    $message .= "👤 <b>User:</b> <code>$sessionUserId</code>\n";
                    $message .= "🆔 <b>Session:</b> <code>{$session['session_id']}</code>\n";
                    $message .= "🎮 <b>Status:</b> {$session['status']} | 💬 <b>Chat:</b> " . ($this->prefs->preferredChat((int) $sessionUserId) ?? 'unset') . "\n";
                    $message .= "📊 Day $day, {$distance}M km\n";
                    $message .= "🔄 Updated: " . date('Y-m-d H:i:s', $session['last_updated']) . "\n\n";
                }
            }

            return $this->sendMessage($chatId, $message, null, null, $userId, $threadId, $username, $displayName);
        }

        return $this->sendMessage($chatId, "🔧 Unknown debug command: $command", null, null, $userId, $threadId, $username, $displayName);
    }
}

// =============================================================================
// ENTRY POINT
// =============================================================================

// Skip auto-execution when included in tests
if (defined('TESTING_MODE') && TESTING_MODE === true) {
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // DIAGNOSTIC: Log immediately before any processing
    OccupyUranusBot::logDiagnostic('=== WEBHOOK POST REQUEST RECEIVED ===');

    try {
        // 1. Validate webhook secret token BEFORE reading or processing any data.
        if (!empty(WEBHOOK_SECRET)) {
            OccupyUranusBot::logDiagnostic('Checking webhook secret...');
            $receivedToken = $_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '';
            if (!hash_equals(WEBHOOK_SECRET, $receivedToken)) {
                OccupyUranusBot::logDiagnostic('Webhook secret validation FAILED - returning 403', 'ERROR');
                http_response_code(403);
                exit;
            }
            OccupyUranusBot::logDiagnostic('Webhook secret validated OK');
        } else {
            OccupyUranusBot::logDiagnostic('No webhook secret configured, skipping validation');
        }

        // 2. Flush HTTP 200 to Telegram immediately so it stops waiting and will not retry.
        OccupyUranusBot::logDiagnostic('Flushing HTTP 200 response...');
        TelegramAPI::flushResponse();
        OccupyUranusBot::logDiagnostic('Response flushed');

        // 3. Process the update
        OccupyUranusBot::logDiagnostic('Instantiating OccupyUranusBot...');
        $bot = new OccupyUranusBot();
        OccupyUranusBot::logDiagnostic('Bot instantiated OK');

        $bot->log('Webhook request received', 'DEBUG');
        OccupyUranusBot::logDiagnostic('Calling processWebhook()...');
        $bot->processWebhook();
        OccupyUranusBot::logDiagnostic('processWebhook() completed');

        $bot->flushLogBuffer();
        OccupyUranusBot::logDiagnostic('Log buffer flushed, webhook processing complete');
        OccupyUranusBot::logDiagnostic('=== WEBHOOK PROCESSING SUCCESSFUL ===');

    } catch (Throwable $e) {
        // CRITICAL: Catch ANY error (including fatal errors in PHP 7+)
        OccupyUranusBot::logDiagnostic('FATAL ERROR in webhook: ' . $e->getMessage(), 'CRITICAL');
        OccupyUranusBot::logDiagnostic('Error file: ' . $e->getFile() . ':' . $e->getLine(), 'CRITICAL');
        OccupyUranusBot::logDiagnostic('Stack trace: ' . $e->getTraceAsString(), 'CRITICAL');

        // Try to write to bot log too
        $errorMsg = "[FATAL] " . date('Y-m-d H:i:s') . " - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine() . PHP_EOL;
        @file_put_contents(LOG_FILE, $errorMsg, FILE_APPEND | LOCK_EX);
    }

    exit;
}

// GET request — show info page for browser / health checks
// NOTE: Does NOT reveal whether WEBHOOK_SECRET is set (was Issue #11)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Occupy Uranus — Telegram Bot</title>
    <style>
        body  { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        h1    { color: #0088cc; }
        .ok   { padding: 15px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 3px; margin: 20px 0; }
        .warn { padding: 15px; background: #f8d7da; border-left: 4px solid #dc3545; border-radius: 3px; margin: 20px 0; }
        code  { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
    <h1>🚀 Occupy Uranus — Telegram Bot</h1>

    <div class="<?php echo (defined('BOT_TOKEN') && BOT_TOKEN !== 'YOUR_BOT_TOKEN_HERE') ? 'ok' : 'warn'; ?>">
        <strong>Bot Status:</strong>
        <?php echo (defined('BOT_TOKEN') && BOT_TOKEN !== 'YOUR_BOT_TOKEN_HERE') ? '✅ Configured' : '❌ Not Configured'; ?>
    </div>

    <h2>Setup Instructions</h2>
    <ol>
        <li>Create a bot via <a href="https://t.me/BotFather">@BotFather</a></li>
        <li>Set <code>BOT_TOKEN</code> and <code>WEBHOOK_SECRET</code> in <code>config.php</code></li>
        <li>Register webhook: call <code>setWebhook()</code> with your URL and secret token</li>
        <li>Start playing in Telegram!</li>
    </ol>

    <h2>Commands</h2>
    <ul>
        <li><code>/game</code> — Open game menu</li>
        <li><code>/help</code> — Show help</li>
    </ul>
</body>
</html>
<?php

<?php
/**
 * Telegram API Wrapper
 * Handles all communication with Telegram Bot API
 *
 * PATCH NOTES — changes from previous version:
 *
 * RETRY LOGIC (was Issue #8 — no retry for 429/transient errors)
 * - makeRequest() now retries on HTTP 429 (Too Many Requests), honouring the
 *   Telegram-provided retry_after value. Up to MAX_RETRIES attempts (default 2)
 *   with a safety-capped sleep. Also retries on 500/502/503/504 server errors
 *   with exponential backoff, since these are transient on Telegram's side.
 * - For a game bot where session state has already been mutated and saved before
 *   the Telegram API call, a lost message means the player's UI is out of sync
 *   with their game state. Retry prevents this for recoverable failures.
 *
 * MULTIPART HEADER RESTORATION (was Issue #7 — fragile header restore)
 * - makeRequestMultipart() now uses a try/finally block to guarantee JSON headers
 *   are restored on the shared cURL handle regardless of how the function exits.
 *   Previously, a Telegram 400 that caused an early return after the explicit
 *   restore line was fine, but adding code before that line could break the
 *   invariant silently.
 *
 * UNCHANGED: All public method signatures are identical. Drop-in replacement.
 */

class TelegramAPI {
    /** @var resource cURL handle reused across all requests in this PHP process */
    private $curlHandle;

    /** Maximum number of retry attempts for retryable errors (429, 5xx) */
    private const MAX_RETRIES = 2;

    /** Absolute ceiling on sleep duration (seconds) to prevent unbounded waits */
    private const MAX_RETRY_SLEEP = 10;

    /** @var array Standard JSON headers — defined once, used for restore after multipart */
    private const JSON_HEADERS = [
        'Content-Type: application/json',
        'Connection: Keep-Alive',
        'Keep-Alive: 300',
    ];

    /** @var array|null In-memory cache of image_name => file_id mappings for this request */
    private static $imageCacheData = null;

    public function __construct() {
        $this->curlHandle = curl_init();
        curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curlHandle, CURLOPT_TIMEOUT, TELEGRAM_TIMEOUT);
        curl_setopt($this->curlHandle, CURLOPT_HTTPHEADER, self::JSON_HEADERS);
    }

    public function __destruct() {
        if ($this->curlHandle) {
            curl_close($this->curlHandle);
        }
    }

    // -------------------------------------------------------------------------
    // WEBHOOK MANAGEMENT
    // -------------------------------------------------------------------------

    /**
     * Register the webhook URL with Telegram.
     *
     * Pass $secretToken to have Telegram include X-Telegram-Bot-Api-Secret-Token
     * on every incoming update so you can verify requests are genuine.
     * The bot entry point should validate this header before processing anything.
     *
     * @param string      $url         Full HTTPS URL to your webhook endpoint
     * @param string|null $secretToken 1–256 chars, A-Z a-z 0-9 _ -  (optional but strongly recommended)
     * @param int[]       $allowedUpdates  Which update types to receive (empty = all)
     * @return array|false
     */
    public function setWebhook(string $url, ?string $secretToken = null, array $allowedUpdates = []) {
        $data = ['url' => $url];

        if ($secretToken !== null) {
            $data['secret_token'] = $secretToken;
        }

        if (!empty($allowedUpdates)) {
            $data['allowed_updates'] = $allowedUpdates;
        }

        return $this->makeRequest(TELEGRAM_API_URL . '/setWebhook', $data);
    }

    /**
     * Fetch current webhook configuration — useful for health checks and debugging.
     *
     * @return array|false
     */
    public function getWebhookInfo() {
        $url     = TELEGRAM_API_URL . '/getWebhookInfo';
        $ch      = curl_copy_handle($this->curlHandle);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, null);

        $response = curl_exec($ch);
        curl_close($ch);

        if ($response === false) {
            return false;
        }

        $result = json_decode($response, true);
        return ($result && isset($result['ok']) && $result['ok']) ? $result : false;
    }

    // -------------------------------------------------------------------------
    // MESSAGE SENDING
    // -------------------------------------------------------------------------

    /**
     * Send a message with HTML formatting and an optional inline keyboard.
     * Supports forum topic threads via $threadId.
     *
     * @param int|string    $chatId
     * @param string        $text      HTML-formatted message text
     * @param array|null    $keyboard  Inline keyboard array (NOT pre-encoded)
     * @param int|null      $threadId  Forum topic / message thread ID
     * @return array|false  Full Telegram API response, or false on failure
     */
    public function sendMessage($chatId, string $text, ?array $keyboard = null, ?int $threadId = null) {
        $data = [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ];

        if ($threadId !== null) {
            $data['message_thread_id'] = $threadId;
        }

        if ($keyboard !== null) {
            $data['reply_markup'] = $keyboard;
        }

        return $this->makeRequest(TELEGRAM_API_URL . '/sendMessage', $data);
    }

    /**
     * Send a plain-text message (no HTML parsing).
     * Use for ASCII art, monospace content, and maps where HTML tags would break layout.
     *
     * @param int|string    $chatId
     * @param string        $text
     * @param array|null    $keyboard
     * @param int|null      $threadId
     * @return array|false
     */
    public function sendMessagePlain($chatId, string $text, ?array $keyboard = null, ?int $threadId = null) {
        $data = [
            'chat_id' => $chatId,
            'text'    => $text,
        ];

        if ($threadId !== null) {
            $data['message_thread_id'] = $threadId;
        }

        if ($keyboard !== null) {
            $data['reply_markup'] = $keyboard;
        }

        return $this->makeRequest(TELEGRAM_API_URL . '/sendMessage', $data);
    }

    /**
     * Send a photo with an HTML caption and optional inline keyboard.
     * Automatically uses cached file_id for previously sent images to avoid re-uploads.
     *
     * $photo may be:
     *  - A Telegram file_id (string starting with non-/)
     *  - A path to a local file (will check cache first, upload if needed, then cache file_id)
     *
     * Returns the full response including a 'file_id' convenience key for caching.
     *
     * @param int|string    $chatId
     * @param string        $photo    Telegram file_id or path to local image file
     * @param string|null   $caption  HTML-formatted caption
     * @param array|null    $keyboard
     * @param int|null      $threadId
     * @return array|false  Response with extra 'file_id' key if a photo was returned
     */
    public function sendPhoto($chatId, string $photo, ?string $caption = null, ?array $keyboard = null, ?int $threadId = null) {
        $isLocalFile = is_file($photo) && is_readable($photo);
        $url = TELEGRAM_API_URL . '/sendPhoto';
        $originalPhotoPath = $photo; // Save original path for cache key generation

        // Check cache for local files
        if ($isLocalFile) {
            $cachedFileId = $this->getImageFromCache($photo);

            if ($cachedFileId !== null) {
                // Use cached file_id instead of uploading
                $photo = $cachedFileId;
                $isLocalFile = false;
            }
        }

        if ($isLocalFile) {
            // Upload the file
            $postFields = [
                'chat_id' => $chatId,
                'photo'   => new \CURLFile(realpath($photo)),
            ];
            if ($caption !== null) {
                $postFields['caption']    = $caption;
                $postFields['parse_mode'] = 'HTML';
            }
            if ($threadId !== null) {
                $postFields['message_thread_id'] = $threadId;
            }
            if ($keyboard !== null) {
                $postFields['reply_markup'] = json_encode($keyboard);
            }
            $result = $this->makeRequestMultipart($url, $postFields);

            // Cache the file_id after successful upload
            if ($result && isset($result['result']['photo']) && !empty($result['result']['photo'])) {
                $highestRes        = end($result['result']['photo']);
                $fileId = $highestRes['file_id'];
                $this->saveImageToCache($originalPhotoPath, $fileId);
            }
        } else {
            // Send using file_id (either original param or from cache)
            $data = [
                'chat_id' => $chatId,
                'photo'   => $photo,
            ];
            if ($caption !== null) {
                $data['caption']    = $caption;
                $data['parse_mode'] = 'HTML';
            }
            if ($threadId !== null) {
                $data['message_thread_id'] = $threadId;
            }
            if ($keyboard !== null) {
                $data['reply_markup'] = $keyboard;
            }
            $result = $this->makeRequest($url, $data);
        }

        // Hoist the highest-resolution file_id so callers can cache it
        if ($result && isset($result['result']['photo']) && !empty($result['result']['photo'])) {
            $highestRes        = end($result['result']['photo']);
            $result['file_id'] = $highestRes['file_id'];
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // MESSAGE EDITING & DELETION
    // -------------------------------------------------------------------------

    /**
     * Edit the text of an existing message.
     * Note: message_thread_id is NOT sent to editMessageText — the Telegram API
     * identifies the message by chat_id + message_id alone; thread context is implicit.
     *
     * @param int|string    $chatId
     * @param int           $messageId
     * @param string        $text       HTML-formatted replacement text
     * @param array|null    $keyboard
     * @return array|false
     */
    public function editMessage($chatId, int $messageId, string $text, ?array $keyboard = null) {
        $data = [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ];

        if ($keyboard !== null) {
            $data['reply_markup'] = $keyboard;
        }

        return $this->makeRequest(TELEGRAM_API_URL . '/editMessageText', $data);
    }

    /**
     * Delete a message.
     * Note: message_thread_id is NOT sent to deleteMessage — that parameter is not
     * accepted by this endpoint.  The message is uniquely identified by chat_id + message_id.
     * Returns true on success, false on failure (e.g. message already deleted, too old,
     * or bot lacks permission).  Callers should treat false as non-fatal.
     *
     * @param int|string  $chatId
     * @param int         $messageId
     * @return bool
     */
    public function deleteMessage($chatId, int $messageId): bool {
        $data = [
            'chat_id'    => $chatId,
            'message_id' => $messageId,
        ];

        $result = $this->makeRequest(TELEGRAM_API_URL . '/deleteMessage', $data);
        return $result !== false;
    }

    // -------------------------------------------------------------------------
    // CALLBACK QUERIES
    // -------------------------------------------------------------------------

    /**
     * Answer a callback query to remove the loading spinner on the button.
     * Must be called within 10 seconds of receiving the callback.
     *
     * @param string      $callbackQueryId
     * @param string|null $text       Optional toast notification text (up to 200 chars)
     * @param bool        $showAlert  If true, show an alert dialog instead of a toast
     * @return array|false
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false) {
        $data = ['callback_query_id' => $callbackQueryId];

        if ($text !== null) {
            $data['text']       = $text;
            $data['show_alert'] = $showAlert;
        }

        return $this->makeRequest(TELEGRAM_API_URL . '/answerCallbackQuery', $data);
    }

    // -------------------------------------------------------------------------
    // ASYNC RESPONSE UTILITY
    // -------------------------------------------------------------------------

    /**
     * Flush HTTP 200 OK to Telegram immediately so it stops waiting and won't
     * retry the webhook, then continue processing in the background.
     *
     * USAGE: Call this ONCE, at the very start of your webhook entry point,
     * BEFORE constructing the bot or doing any work:
     *
     *     if ($_SERVER['REQUEST_METHOD'] === 'POST') {
     *         TelegramAPI::flushResponse();
     *         $bot = new OccupyUranusBot();
     *         $bot->processWebhook();
     *     }
     *
     * This is a static method because it operates on the HTTP response layer,
     * not on a Telegram API connection — it doesn't belong to an instance.
     * It intentionally does NOT send any Telegram message; the webhook handler
     * is responsible for all outbound messages after this call returns.
     */
    public static function flushResponse(): void {
        // Discard any buffered output so Content-Length is accurate
        while (ob_get_level()) {
            ob_end_clean();
        }

        http_response_code(200);
        header('Content-Type: text/plain');
        header('Content-Length: 2');
        header('Connection: close');
        echo 'OK';

        // Flush at every level
        if (ob_get_level()) {
            ob_end_flush();
        }
        flush();

        // PHP-FPM: detach from the client connection entirely
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        }
    }

    // -------------------------------------------------------------------------
    // INTERNAL HTTP TRANSPORT
    // -------------------------------------------------------------------------

    /**
     * Reset the cURL handle to a clean state and restore persistent options.
     * Called after transport-level errors (curl_exec returns false) so the handle
     * is safe to reuse for subsequent calls in this request lifecycle.
     */
    private function resetCurlHandle(): void {
        curl_reset($this->curlHandle);
        curl_setopt($this->curlHandle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curlHandle, CURLOPT_HTTPHEADER, self::JSON_HEADERS);
    }

    /**
     * Make a POST request to the Telegram Bot API with retry support.
     *
     * The entire $data array is JSON-encoded as the request body.
     * reply_markup and other nested structures must be passed as plain PHP
     * arrays — do NOT pre-encode them with json_encode() before passing here.
     *
     * Retries on:
     *   - HTTP 429 (Too Many Requests): sleeps for retry_after seconds
     *   - HTTP 500/502/503/504 (server errors): exponential backoff
     * Up to MAX_RETRIES additional attempts after the first failure.
     *
     * @param  string       $url   Full API endpoint URL
     * @param  array        $data  Request payload as a PHP array
     * @return array|false  Decoded response body on success, false on any error
     */
    private function makeRequest(string $url, array $data) {
        $body = json_encode($data);

        $attempt = 0;
        $maxAttempts = 1 + self::MAX_RETRIES;

        while ($attempt < $maxAttempts) {
            $attempt++;

            curl_setopt($this->curlHandle, CURLOPT_URL,        $url);
            curl_setopt($this->curlHandle, CURLOPT_POST,       true);
            curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $body);
            curl_setopt($this->curlHandle, CURLOPT_TIMEOUT,    TELEGRAM_TIMEOUT);

            $response = curl_exec($this->curlHandle);
            $httpCode = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);

            // Transport-level failure (DNS, timeout, connection refused)
            if ($response === false) {
                $error = curl_error($this->curlHandle);
                error_log("Telegram API cURL error on $url (attempt $attempt): $error");
                $this->logDiagnostic("TelegramAPI: cURL ERROR on $url (attempt $attempt): $error");
                $this->resetCurlHandle();
                // Transport errors are typically not retryable (timeout, DNS)
                return false;
            }

            $result = json_decode($response, true);

            // Success
            if ($result && isset($result['ok']) && $result['ok']) {
                return $result;
            }

            // HTTP 429 — Too Many Requests: honour retry_after
            if ($httpCode === 429 && $attempt < $maxAttempts) {
                $retryAfter = $result['parameters']['retry_after'] ?? 1;
                $sleepTime  = min((int) $retryAfter, self::MAX_RETRY_SLEEP);
                error_log("Telegram API 429 on $url — retrying in {$sleepTime}s (attempt $attempt)");
                sleep($sleepTime);
                continue;
            }

            // 5xx server errors — exponential backoff
            if ($httpCode >= 500 && $httpCode < 600 && $attempt < $maxAttempts) {
                $sleepTime = min(pow(2, $attempt - 1), self::MAX_RETRY_SLEEP);
                error_log("Telegram API $httpCode on $url — retrying in {$sleepTime}s (attempt $attempt)");
                sleep((int) $sleepTime);
                continue;
            }

            // Non-retryable error (400, 401, 403, etc.) — fail immediately
            $errorMsg = $result['description'] ?? 'Unknown error';
            error_log("Telegram API error (HTTP $httpCode) on $url: $errorMsg");
            $this->logDiagnostic("TelegramAPI: HTTP $httpCode ERROR on $url: $errorMsg");
            return false;
        }

        // Exhausted all attempts
        error_log("Telegram API: exhausted $maxAttempts attempts on $url");
        $this->logDiagnostic("TelegramAPI: EXHAUSTED $maxAttempts attempts on $url");
        return false;
    }

    /**
     * Write to diagnostic log file immediately for debugging webhook issues.
     * Must be standalone (no dependencies) since TelegramAPI has no access to OccupyUranusBot.
     *
     * @param string $message Message to log
     * @param string $level   Log level: 'DEBUG', 'ERROR', 'CRITICAL'
     */
    private function logDiagnostic(string $message, string $level = 'ERROR'): void {
        if (!defined('DIAGNOSTIC_LOG') || !defined('DEBUG_MODE')) {
            return; // config.php not loaded yet
        }

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

        @file_put_contents(DIAGNOSTIC_LOG, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Send a request with multipart/form-data (for file uploads).
     * Does not set Content-Type so cURL uses multipart boundary.
     *
     * Uses try/finally to guarantee JSON headers are restored on the shared
     * cURL handle regardless of how the function exits — including early returns,
     * exceptions, or code added between the header swap and the restore point.
     *
     * @param  string  $url        Full API endpoint URL
     * @param  array   $postFields Fields including CURLFile for the photo
     * @return array|false
     */
    private function makeRequestMultipart(string $url, array $postFields) {
        // Swap to multipart headers (omit Content-Type so cURL adds boundary)
        curl_setopt($this->curlHandle, CURLOPT_HTTPHEADER, ['Connection: Keep-Alive', 'Keep-Alive: 300']);

        try {
            curl_setopt($this->curlHandle, CURLOPT_URL, $url);
            curl_setopt($this->curlHandle, CURLOPT_POST, true);
            curl_setopt($this->curlHandle, CURLOPT_POSTFIELDS, $postFields);
            curl_setopt($this->curlHandle, CURLOPT_TIMEOUT, TELEGRAM_TIMEOUT);

            $response = curl_exec($this->curlHandle);
            $httpCode = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);

            if ($response === false) {
                $error = curl_error($this->curlHandle);
                error_log("Telegram API cURL error on $url: $error");
                $this->resetCurlHandle();
                return false;
            }

            $result = json_decode($response, true);
            if (!$result || !isset($result['ok']) || !$result['ok']) {
                $errorMsg = $result['description'] ?? 'Unknown error';
                error_log("Telegram API error (HTTP $httpCode) on $url: $errorMsg");
                return false;
            }

            return $result;

        } finally {
            // Guarantee JSON headers are restored even if an exception or early return occurs
            curl_setopt($this->curlHandle, CURLOPT_HTTPHEADER, self::JSON_HEADERS);
        }
    }

    // -------------------------------------------------------------------------
    // IMAGE CACHE (file_id optimization)
    // -------------------------------------------------------------------------

    /**
     * Generate a cache key from a file path that includes aesthetic information.
     *
     * For paths like "media/anime/status_inner.jpg", returns "anime:status_inner.jpg"
     * This ensures each aesthetic has separate cache entries for the same filename.
     *
     * @param  string  $filePath  Full or relative path to image file
     * @return string  Cache key in format "aesthetic:filename"
     */
    private function generateCacheKey(string $filePath): string {
        $filename = basename($filePath);
        $pathParts = explode('/', str_replace('\\', '/', $filePath));

        // Look for aesthetic directory (media/{aesthetic}/{filename})
        for ($i = count($pathParts) - 2; $i >= 0; $i--) {
            if ($pathParts[$i] === 'media' && isset($pathParts[$i + 1])) {
                $aesthetic = $pathParts[$i + 1];
                return "{$aesthetic}:{$filename}";
            }
        }

        // No aesthetic found - this shouldn't happen for game images
        error_log("TelegramAPI: No aesthetic found in path: $filePath");
        return $filename;
    }

    /**
     * Get a cached Telegram file_id for an image.
     *
     * @param  string  $imagePath  Full path to the image file
     * @return string|null  Cached file_id or null if not cached
     */
    private function getImageFromCache(string $imagePath): ?string {
        $cacheKey = $this->generateCacheKey($imagePath);
        $cache = $this->loadImageCache();
        return $cache[$cacheKey] ?? null;
    }

    /**
     * Save a Telegram file_id to the image cache.
     *
     * @param  string  $imagePath  Full path to the image file
     * @param  string  $fileId     Telegram file_id
     * @return bool  True on success, false on failure
     */
    private function saveImageToCache(string $imagePath, string $fileId): bool {
        $cacheKey = $this->generateCacheKey($imagePath);
        $cache = $this->loadImageCache();
        $cache[$cacheKey] = $fileId;
        self::$imageCacheData = $cache;

        $cacheFile = $this->getImageCacheFilePath();
        $dir = dirname($cacheFile);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return false;
        }
        return file_put_contents($cacheFile, json_encode($cache, JSON_PRETTY_PRINT)) !== false;
    }

    /**
     * Load the image cache from disk.
     *
     * @return array  Map of imageName => telegramFileId
     */
    private function loadImageCache(): array {
        if (self::$imageCacheData !== null) {
            return self::$imageCacheData;
        }

        $cacheFile = $this->getImageCacheFilePath();

        if (!file_exists($cacheFile)) {
            self::$imageCacheData = [];
            return self::$imageCacheData;
        }

        $data = json_decode(file_get_contents($cacheFile), true);
        self::$imageCacheData = is_array($data) ? $data : [];
        return self::$imageCacheData;
    }

    /**
     * Get the image cache file path.
     *
     * @return string
     */
    private function getImageCacheFilePath(): string {
        return defined('IMAGE_CACHE_FILE') ? IMAGE_CACHE_FILE : (DATA_DIR . '/image_cache.json');
    }
}
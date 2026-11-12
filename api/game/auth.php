<?php
/**
 * Who is playing: two ways in.
 *
 * - Telegram: the Mini-App's initData, verified against the bot token.
 *   @see https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
 * - Web (no Telegram): a captain name, on the honor system. No password: the same name
 *   always opens the same game, from any browser, for anyone who types it.
 */

require_once __DIR__ . '/../../config.php';

const WEB_PLAYER_NAME_MIN = 2;
const WEB_PLAYER_NAME_MAX = 20;

/**
 * Turn a web player's captain name into a player.
 *
 * The user id is derived from the name (case and extra spaces ignored), so no registry is
 * needed. It is always negative: Telegram user ids are positive, so a web player can never
 * open a Telegram player's game, and the bot never sees web games.
 *
 * @return array|false Same shape as validateTelegramWebAppData(), or false if the name is not allowed
 */
function webPlayerFromName(string $name) {
    $name = preg_replace('/\s+/u', ' ', trim($name));
    $length = mb_strlen((string) $name);
    if ($name === null || $length < WEB_PLAYER_NAME_MIN || $length > WEB_PLAYER_NAME_MAX
        || !preg_match('/^[\p{L}\p{N}][\p{L}\p{N} _.\'-]*$/u', $name)) {
        return false;
    }

    // 52 bits of the name's hash: a safe integer in PHP and JavaScript; collisions are negligible
    $id = hexdec(substr(hash('sha256', mb_strtolower($name)), 0, 13)) + 1;

    return [
        'user_id'    => -$id,
        'username'   => $name,     // shown on the leaderboard
        'first_name' => $name,
        'last_name'  => null,
        'auth_date'  => time(),
    ];
}

/**
 * Validate Telegram Web App initData
 *
 * @param string $initData Raw initData string from Telegram.WebApp.initData
 * @return array|false Returns parsed user data if valid, false otherwise
 */
function validateTelegramWebAppData(string $initData) {
    // Parse initData into key-value pairs
    parse_str($initData, $data);

    // Extract hash (must be present)
    if (!isset($data['hash'])) {
        error_log('[Mini-App Auth] Missing hash in initData');
        return false;
    }

    $receivedHash = $data['hash'];
    unset($data['hash']);

    // Sort parameters alphabetically
    ksort($data);

    // Build data-check-string
    $dataCheckArr = [];
    foreach ($data as $key => $value) {
        $dataCheckArr[] = $key . '=' . $value;
    }
    $dataCheckString = implode("\n", $dataCheckArr);

    // Compute secret key (HMAC-SHA256 of bot token with "WebAppData" as key)
    $secretKey = hash_hmac('sha256', BOT_TOKEN, 'WebAppData', true);

    // Compute hash of data-check-string using secret key
    $computedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

    // Compare hashes (timing-safe comparison)
    if (!hash_equals($computedHash, $receivedHash)) {
        error_log('[Mini-App Auth] Hash mismatch - invalid signature');
        return false;
    }

    // Check auth_date (must be within 24 hours)
    if (!isset($data['auth_date'])) {
        error_log('[Mini-App Auth] Missing auth_date');
        return false;
    }

    $authDate = (int)$data['auth_date'];
    $currentTime = time();
    $maxAge = 86400; // 24 hours

    if (($currentTime - $authDate) > $maxAge) {
        error_log('[Mini-App Auth] initData expired (older than 24 hours)');
        return false;
    }

    // Parse user data (JSON string)
    if (!isset($data['user'])) {
        error_log('[Mini-App Auth] Missing user data');
        return false;
    }

    $user = json_decode($data['user'], true);
    if (!$user || !isset($user['id'])) {
        error_log('[Mini-App Auth] Invalid user data format');
        return false;
    }

    // Return validated user data
    return [
        'user_id' => $user['id'],
        'username' => $user['username'] ?? null,
        'first_name' => $user['first_name'] ?? null,
        'last_name' => $user['last_name'] ?? null,
        'auth_date' => $authDate,
    ];
}

/**
 * Send JSON response with CORS headers
 *
 * @param array $data Response data
 * @param int $statusCode HTTP status code
 * @return void
 */
function sendJsonResponse(array $data, int $statusCode = 200) {
    // CORS headers for Telegram Mini App
    header('Access-Control-Allow-Origin: https://oauth.telegram.org');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Content-Type: application/json');
    http_response_code($statusCode);

    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send error response
 *
 * @param string $error Error code
 * @param string $message Human-readable message
 * @param int $statusCode HTTP status code
 * @return void
 */
function sendErrorResponse(string $error, string $message, int $statusCode = 400) {
    sendJsonResponse([
        'success' => false,
        'error' => $error,
        'message' => $message,
    ], $statusCode);
}

/**
 * Handle OPTIONS preflight request
 *
 * @return void
 */
function handlePreflight() {
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        header('Access-Control-Allow-Origin: https://oauth.telegram.org');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        http_response_code(204);
        exit;
    }
}

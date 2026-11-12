<?php
/**
 * POST /api/game/action.php
 *
 * Body: {"init_data": "...", "action": "advance_day", "params": {}}
 *   (web players send "player": "<captain name>" instead of init_data)
 *
 * Runs one action through GameService (see its class doc for actions, outcomes and reason
 * codes) and returns the result plus a fresh snapshot of the game:
 *   {"success": true, "outcome": "...", "data": {...}, "has_session": ..., "session": ...,
 *    "status": ..., "map": ..., "shop": ...}
 *
 * A move the rules refuse is still a successful request: outcome "rejected", data.reason says why.
 */

require_once __DIR__ . '/bootstrap.php';

handlePreflight();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendErrorResponse('method_not_allowed', 'Only POST requests are supported', 405);
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) {
    sendErrorResponse('invalid_json', 'Request body must be valid JSON', 400);
}

$user   = authenticate($body['init_data'] ?? '', $body['player'] ?? '');
$action = $body['action'] ?? '';
$params = is_array($body['params'] ?? null) ? $body['params'] : [];

if ($action === '') {
    sendErrorResponse('missing_action', 'action parameter is required', 400);
}

// The player's name comes from their sign-in (Telegram's signed data or the captain name), never from params
if ($action === 'new_game') {
    $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
    $params = [
        'username'     => $user['username'],
        'display_name' => $name !== '' ? $name : $user['username'],
    ];
}

$game = gameService();

try {
    $result = $game->act($user['user_id'], $action, $params, 'mini-app');
} catch (Throwable $e) {
    error_log('[Game API] Action failed: ' . $e->getMessage());
    sendErrorResponse('action_error', 'Action execution failed', 500);
}

if ($result['outcome'] === 'rejected') {
    $reason = $result['data']['reason'];
    if ($reason === 'unknown_action') {
        sendErrorResponse('unknown_action', "Unsupported action: $action", 400);
    }
    if ($reason === 'no_session') {
        sendErrorResponse('no_session', 'No active game session found', 404);
    }
}

// After an abandon the game is gone; every other outcome leaves a saved game behind
$session = $result['outcome'] === 'abandoned' ? null : $game->getState($user['user_id']);

// Events go to the client without their outcome tables
$data = $result['data'];
if (isset($data['event']) && $session !== null) {
    $data['event'] = $game->describeEvent($data['event'], $session);
}

sendJsonResponse([
    'success' => true,
    'outcome' => $result['outcome'],
    'data'    => $data,
] + gameSnapshot($game, $session), 200);

<?php
/**
 * GET /api/game/state.php?init_data=...   (Telegram)
 * GET /api/game/state.php?player=<name>   (web, honor system)
 *
 * The signed-in player's game: saved game, status details, route map and shop catalog.
 * Answers 200 with has_session:false when the player has no game (they can start one).
 */

require_once __DIR__ . '/bootstrap.php';

handlePreflight();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendErrorResponse('method_not_allowed', 'Only GET requests are supported', 405);
}

$user = authenticate($_GET['init_data'] ?? '', $_GET['player'] ?? '');
$game = gameService();

sendJsonResponse(['success' => true] + gameSnapshot($game, $game->getState($user['user_id'])), 200);

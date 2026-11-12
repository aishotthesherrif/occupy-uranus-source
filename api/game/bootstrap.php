<?php
/**
 * Shared setup for the mini-app API endpoints.
 *
 * The API is a thin door: check who is asking (Telegram sign-in), hand the request to
 * GameService, return what it says. It holds no game rules and no screen text.
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../game/GameSessionManager.php';
require_once __DIR__ . '/../../game/GameEngine.php';
require_once __DIR__ . '/../../game/Telemetry.php';
require_once __DIR__ . '/../../game/GameService.php';
require_once __DIR__ . '/auth.php';

function gameService(): GameService {
    return new GameService(new GameEngine(), new GameSessionManager());
}

/**
 * Who is asking: Telegram's initData if present, otherwise a web player's captain name
 * (honor system, see auth.php). Answers 400/401 and stops if neither is usable.
 *
 * @return array ['user_id', 'username', 'first_name', 'last_name', 'auth_date']
 */
function authenticate(?string $initData, ?string $player): array {
    if (!empty($initData)) {
        $user = validateTelegramWebAppData($initData);
        if (!$user) {
            sendErrorResponse('unauthorized', 'Invalid Telegram authentication', 401);
        }
        return $user;
    }

    if (!empty($player)) {
        $user = webPlayerFromName($player);
        if (!$user) {
            sendErrorResponse('invalid_player_name',
                'Captain names are ' . WEB_PLAYER_NAME_MIN . '-' . WEB_PLAYER_NAME_MAX
                . ' letters, numbers, spaces, or . _ \' -', 400);
        }
        return $user;
    }

    sendErrorResponse('missing_sign_in', 'Send init_data (Telegram) or player (captain name)', 400);
}

/**
 * What a client needs to draw the game after any request: the saved game plus the data
 * screens are built from. All plain data; the mini-app writes its own text.
 */
function gameSnapshot(GameService $game, ?array $session): array {
    if ($session === null) {
        return ['has_session' => false, 'session' => null, 'status' => null, 'map' => null, 'shop' => null,
                'return_costs' => null, 'pending_event' => null, 'ending' => null];
    }

    return [
        'has_session' => true,
        'session'     => $session,
        'status'      => $game->getStatusDetails($session),
        'map'         => $game->getMapData($session),
        'shop'        => $game->getShopCatalog($session),
        'return_costs' => $game->getReturnCosts($session),
        'pending_event' => $game->getPendingEvent($session),
        'ending'      => $game->getEnding($session),
    ];
}

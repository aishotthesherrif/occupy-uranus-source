<?php
/**
 * BotController
 *
 * Turns a Telegram button press into a game action (through GameService) and the result
 * into a bot screen (through ViewManager).
 *
 *   Button -> GameService::act() -> outcome + data -> screen name, context, flash -> ViewManager
 *
 * All Telegram-specific choices live here: which screen follows which outcome, and what the
 * flash text says. GameService decides nothing about screens.
 */
class BotController {
    /** Buttons that make something happen in the game. Every other button only shows a screen. */
    private const BUTTON_ACTIONS = [
        'continue'           => 'advance_day',
        'rest'               => 'rest',
        'event_choice'       => 'process_event',
        'launch'             => 'launch',
        'hunt'               => 'hunt',
        'return_to_waypoint' => 'return_to_waypoint',
        'stop_at_waypoint'   => 'stop_at_waypoint',
        'skip_waypoint'      => 'skip_waypoint',
    ];

    private GameService $game;
    private ViewManager $viewManager;
    private GameText $text;
    private BotPreferences $prefs;

    public function __construct(GameService $game, ViewManager $viewManager, GameText $text, BotPreferences $prefs) {
        $this->game        = $game;
        $this->viewManager = $viewManager;
        $this->text        = $text;
        $this->prefs       = $prefs;
    }

    /**
     * Route a button press to the appropriate action and screen.
     *
     * @param string $button  Button identifier (continue, journey, status, map, etc.)
     * @param int    $userId
     * @param array  $params  Action params (event_id, choice_id) or extra view context
     * @return array ['view_data' => ready to send, 'session' => the game after the action]
     * @throws RuntimeException when the player has no game
     */
    public function route(string $button, int $userId, array $params = []): array {
        if (isset(self::BUTTON_ACTIONS[$button])) {
            $action = self::BUTTON_ACTIONS[$button];
            $result = $this->game->act($userId, $action, $params, 'bot');

            if ($result['outcome'] === 'rejected' && $result['data']['reason'] === 'no_session') {
                throw new RuntimeException("No active session for user $userId");
            }

            if ($result['outcome'] === 'event_resolved') {
                // Shown once, on the next Journey screen
                $this->prefs->set($userId, ['resource_changes' => $result['data']['resource_changes']]);
            }

            $session = $result['session'];
            [$nextView, $context, $flash] = $this->screenFor($result, $action);
        } else {
            $session = $this->loadSession($userId);
            if ($session === null) {
                throw new RuntimeException("No active session for user $userId");
            }

            $nextView = $this->getViewForButton($button, $session);
            $context  = $this->getContextForView($nextView, $session);
            $flash    = null;

            if ($button === 'journey') {
                $changes = $this->prefs->takeResourceChanges($userId);
                if ($changes) {
                    $context['resource_changes'] = $changes;
                }
            }

            // Context passed by the caller (extra view data)
            $context = array_merge($context, $params);
        }

        if ($flash) {
            $context['flash'] = $flash;
        }

        $session['aesthetic'] = $this->prefs->aesthetic($userId);
        $viewData = $this->viewManager->render($nextView, $session, $context);

        return [
            'view_data' => $viewData,
            'session'   => $session,
        ];
    }

    /**
     * Which screen follows an outcome, with its context and flash text.
     *
     * @return array [screen name, context, flash text or null]
     */
    private function screenFor(array $result, string $action): array {
        $data = $result['data'];

        switch ($result['outcome']) {
            case 'day_advanced':
                return ['journey', [], $this->text->daySummaryText()];

            case 'game_over':
                return ['game_over', ['reason' => $data['reason']], null];

            case 'victory':
                return ['victory', [], null];

            case 'waypoint_reached':
                return ['waypoint', ['waypoint' => $data['waypoint']], null];

            case 'event_triggered':
                return ['event', ['event' => $data['event']], null];

            case 'event_resolved':
                return ['post_event', [
                    'outcome_message'  => $this->text->eventResultText($data),
                    'resource_changes' => $data['resource_changes'],
                    'event_outcome'    => $data['event_outcome'],
                ], null];

            case 'rested':
                return ['station', ['station' => $data['station']], $this->text->restText($data)];

            case 'launched':
                return ['journey', [], $this->text->launchText($data, $result['session'])];

            case 'hunted':
                return ['journey', [], $this->text->huntNothingText()];

            case 'returned':
                return ['journey', [], $this->text->returnText($data)];

            case 'docked':
                return ['station', ['station' => $data['station'], 'speed_boost_lost' => $data['had_boost']], null];

            case 'waypoint_skipped':
                return ['waypoint_skipped', [
                    'waypoint'            => $data['waypoint'],
                    'was_already_boosted' => $data['was_already_boosted'],
                ], null];

            case 'rejected':
                return ['journey', [], $this->text->rejectionText($data['reason'], $action)];
        }

        throw new LogicException("No screen for outcome '{$result['outcome']}'");
    }

    /**
     * The player's game as the bot should draw it: saved game plus the bot's own aesthetic.
     * This copy is for display only; it is never saved.
     */
    public function loadSession(int $userId): ?array {
        $session = $this->game->getState($userId);
        if ($session !== null) {
            $session['aesthetic'] = $this->prefs->aesthetic($userId);
        }
        return $session;
    }

    /**
     * Get view name for button (when no action is executed).
     * Context-aware: checks session status to determine correct view.
     */
    private function getViewForButton(string $button, array $session): string {
        // "journey" button is context-aware - shows current gameplay state
        if ($button === 'journey') {
            // If stopped at station, show station view instead of journey view
            if ($session['status'] === 'at_station') {
                return 'station';
            }
            return 'journey';
        }

        $buttonViewMap = [
            'launch_sequence' => 'launch_sequence',
            'status' => 'status',
            'map' => 'map',
            'shop' => 'shop',
            'aesthetic' => 'aesthetic',
            'return_confirm' => 'return_confirm',
            'station' => 'station',
            'main_menu' => 'main_menu',
            'abandon' => 'abandon_confirm',
        ];

        return $buttonViewMap[$button] ?? 'journey';
    }

    /**
     * Get context for a view based on session state.
     */
    private function getContextForView(string $viewName, array $session): array {
        switch ($viewName) {
            case 'station':
                return ['station' => $session['at_waypoint']];
            default:
                return [];
        }
    }

    /**
     * Handle pending events in session.
     *
     * If session has pending_event, show event view immediately.
     */
    public function checkPendingEvent(int $userId): ?array {
        $session = $this->loadSession($userId);

        if ($session === null) {
            return null;
        }

        if (!empty($session['pending_event'])) {
            $event = $session['pending_event'];

            $viewData = $this->viewManager->render('event', $session, ['event' => $event]);

            return [
                'view_data' => $viewData,
                'session' => $session,
            ];
        }

        return null;
    }

    /**
     * Get current view for user without executing any action.
     * Used for "back to journey" type navigation.
     */
    public function getCurrentView(int $userId, string $viewName = 'journey'): ?array {
        $session = $this->loadSession($userId);

        if ($session === null) {
            return null;
        }

        // Determine view based on session status
        $context = [];
        if ($session['status'] === 'pre_launch' || $session['status'] === 'at_station') {
            $viewName = 'shop';
        } elseif (!empty($session['pending_event'])) {
            $viewName = 'event';
            $context = ['event' => $session['pending_event']];
        }

        $viewData = $this->viewManager->render($viewName, $session, $context);

        return [
            'view_data' => $viewData,
            'session' => $session,
        ];
    }
}

<?php
/**
 * ViewManager
 *
 * Renders views as pure functions of session state.
 * Views ALWAYS produce the same output for the same session state,
 * regardless of how the user navigated to the view.
 */
class ViewManager {
    private GameEngine $gameEngine;
    private GameText $gameText;

    public function __construct(GameEngine $gameEngine) {
        $this->gameEngine = $gameEngine;
        $this->gameText = new GameText($gameEngine);
    }

    /**
     * Load available aesthetics from configuration.
     *
     * @return array Array of aesthetic definitions
     */
    private function loadAesthetics(): array {
        $aestheticsFile = DATA_DIR . '/aesthetics.json';
        if (!file_exists($aestheticsFile)) {
            return [
                'aesthetics' => [
                    ['id' => 'main', 'name' => 'Main', 'description' => 'Classic space photography', 'icon' => '🖼️', 'path' => 'main']
                ],
                'default' => 'main'
            ];
        }
        return json_decode(file_get_contents($aestheticsFile), true) ?? [];
    }

    /**
     * Render a view from session state.
     *
     * @param string $viewName The view to render (journey, status, map, event, etc.)
     * @param array|null $session The game session state (passed by reference for waypoint tracking, null for start view)
     * @param array $context Additional context (e.g., event data, flash messages)
     * @return array [message, image_url, keyboard, parse_mode]
     */
    public function render(string $viewName, ?array &$session, array $context = []): array {
        switch ($viewName) {
            case 'journey':
                return $this->renderJourneyView($session, $context);

            case 'status':
                return $this->renderStatusView($session);

            case 'map':
                return $this->renderMapView($session);

            case 'event':
                return $this->renderEventView($session, $context);

            case 'shop':
                return $this->renderShopView($session);

            case 'waypoint':
                return $this->renderWaypointView($session, $context);

            case 'station':
                return $this->renderStationView($session, $context);

            case 'game_over':
                return $this->renderGameOverView($session, $context);

            case 'victory':
                return $this->renderVictoryView($session);

            case 'post_event':
                return $this->renderPostEventView($session, $context);

            case 'return_confirm':
                return $this->renderReturnConfirmView($session);

            case 'aesthetic':
                return $this->renderAestheticView($session);

            case 'start':
                return $this->renderStartView($session, $context);

            case 'main_menu':
                return $this->renderMainMenuView($session, $context);

            case 'launch_sequence':
                return $this->renderLaunchSequenceView($session);

            case 'abandon_confirm':
                return $this->renderAbandonConfirmView($session);

            case 'waypoint_skipped':
                return $this->renderWaypointSkippedView($context);

            default:
                throw new InvalidArgumentException("Unknown view: $viewName");
        }
    }

    /**
     * Render Journey View (concise, action-focused).
     *
     * This is ALWAYS the same for a given session state, regardless of
     * how the user navigated here (Continue button, Back button, etc.).
     *
     * Shows: Day header, progress bar, velocity, resources with days remaining,
     * and context-aware prompt.
     */
    private function renderJourneyView(array $session, array $context = []): array {
        // Build flash message from context
        $flashMessage = $context['flash'] ?? null;

        // If resource changes are provided (from event), format them into flash message
        if (!empty($context['resource_changes'])) {
            $resourceChangesText = $this->formatResourceChanges($context['resource_changes']);
            $flashMessage = ($flashMessage ? $flashMessage . "\n\n" : '') . $resourceChangesText;
        }

        // Note: formatJourney still in GameEngine - will move in future refactor
        $message = $this->gameText->formatJourney($session, $flashMessage);

        return [
            'message' => $message,
            'image_url' => $this->getContextImageUrl('day', $session),
            'keyboard' => $this->getJourneyKeyboard($session),
            'parse_mode' => 'HTML',
        ];
    }

    /**
     * Render Status View (detailed chad dashboard).
     */
    private function renderStatusView(array $session): array {
        // Note: formatDetailedStatus still in GameEngine - will move in future refactor
        $message = $this->gameText->formatDetailedStatus($session);
        $keyboard = $this->getStatusKeyboard($session);

        return [
            'message' => $message,
            'image_url' => $this->getContextImageUrl('status', $session),
            'keyboard' => $keyboard,
            'parse_mode' => 'HTML',
        ];
    }

    /**
     * Render Map View.
     */
    private function renderMapView(array $session): array {
        $message = $this->gameText->generateMap($session);
        $keyboard = $this->getBackButton();

        return [
            'message' => $message,
            'image_url' => null,
            'keyboard' => $keyboard,
            'parse_mode' => 'HTML',
        ];
    }

    /**
     * Render Event View.
     */
    private function renderEventView(array $session, array $context): array {
        if (empty($context['event'])) {
            throw new InvalidArgumentException("Event view requires 'event' in context");
        }

        $event    = $context['event'];
        $message  = $this->formatEnhancedEvent($event, null, null, $session);
        $keyboard = $this->createEnhancedEventKeyboard($event);

        return [
            'message' => $message,
            'image_url' => $this->getContextImageUrl('event', $session),
            'keyboard' => ['inline_keyboard' => $keyboard],
            'parse_mode' => 'HTML',
        ];
    }

    /**
     * Render Shop View.
     */
    private function renderShopView(array $session): array {
        $message  = $this->formatShop($session, null, null);
        $keyboard = $this->getShopKeyboard($session);

        return [
            'message' => $message,
            'image_url' => $this->getContextImageUrl('shop', $session),
            'keyboard' => $keyboard,
            'parse_mode' => 'HTML',
        ];
    }

    /**
     * Render Waypoint View (stop or skip decision).
     */
    private function renderWaypointView(array $session, array $context): array {
        if (empty($context['waypoint'])) {
            throw new InvalidArgumentException("Waypoint view requires 'waypoint' in context");
        }

        $waypoint = $context['waypoint'];
        $message  = $this->gameText->formatWaypointView($session, $waypoint);
        $keyboard = $this->getWaypointKeyboard($waypoint);

        return [
            'message' => $message,
            'image_url' => $this->getContextImageUrl('waypoint', $session),
            'keyboard' => $keyboard,
            'parse_mode' => 'HTML',
        ];
    }

    /**
     * Render Waypoint Skipped View (flew past a waypoint for a gravitational assist).
     */
    private function renderWaypointSkippedView(array $context): array {
        if (empty($context['waypoint'])) {
            throw new InvalidArgumentException("Waypoint skipped view requires 'waypoint' in context");
        }

        return [
            'message' => $this->formatWaypointSkipMessage($context['waypoint']['name'] ?? 'Waypoint', !empty($context['was_already_boosted'])),
            'image_url' => null,
            'keyboard' => $this->getContinueKeyboard(),
            'parse_mode' => 'HTML',
        ];
    }

    /**
     * Render Station View (when stopped at a station).
     * Context: 'station' (waypoint with the chosen story), optional 'speed_boost_lost'.
     */
    private function renderStationView(array $session, array $context): array {
        if (empty($context['station'])) {
            throw new InvalidArgumentException("Station view requires 'station' in context");
        }

        $station = $context['station'];
        $message  = $this->gameText->formatStationView($session, $station);
        if (!empty($context['speed_boost_lost'])) {
            $message = $this->formatSpeedBoostLostMessage() . $message;
        }
        $keyboard = $this->getStationKeyboard($session);

        return [
            'message' => $message,
            'image_url' => $this->getContextImageUrl('station', $session),
            'keyboard' => $keyboard,
            'parse_mode' => 'HTML',
        ];
    }

    /**
     * Render Game Over View.
     */
    private function renderGameOverView(array $session, array $context): array {
        $reason = $context['reason'] ?? 'Unknown';
        $data = $this->formatGameOverWithImage($session, ['reason' => $reason]);

        return [
            'message' => $data['message'],
            'image_url' => $data['image_url'] ?? null,
            'keyboard' => $this->getGameOverKeyboard(),
            'parse_mode' => 'HTML',
        ];
    }

    /**
     * Render Victory View.
     */
    private function renderVictoryView(array $session): array {
        // formatVictoryWithImage needs both session and result array
        // We pass an empty result array since it's not used
        $result = [];
        $data = $this->formatVictoryWithImage($session, $result);

        return [
            'message' => $data['message'],
            'image_url' => $data['image_url'] ?? null,
            'keyboard' => $this->getVictoryKeyboard(),
            'parse_mode' => 'HTML',
        ];
    }

    /**
     * Render Launch Sequence View.
     * Dramatic launch moment - image only with single GO button.
     */
    private function renderLaunchSequenceView(array $session): array {
        return [
            'message' => '',
            'image_url' => $this->getContextImageUrl('launch', $session),
            'keyboard' => $this->getLaunchSequenceKeyboard(),
            'parse_mode' => 'HTML',
        ];
    }

    /**
     * Render Post-Event View.
     * Shows the event outcome and requires user to click "Back to Journey".
     */
    private function renderPostEventView(array $session, array $context): array {
        if (empty($context['outcome_message'])) {
            throw new InvalidArgumentException("Post-event view requires 'outcome_message' in context");
        }

        $message = $context['outcome_message'];

        // Show resource changes (before/after) if available
        if (!empty($context['resource_changes'])) {
            $message .= "\n" . $this->formatResourceChanges($context['resource_changes']);
        }

        // Append resource summary (GameEngine no longer includes this)
        $message .= "\n" . $this->formatResourcesSummary($session);

        $keyboard = $this->getPostEventKeyboard();

        // Get outcome-based image (success or failure)
        $eventOutcome = $context['event_outcome'] ?? 'success';
        $imageUrl = $this->getContextImageUrl('post_event', $session, $eventOutcome);

        return [
            'message' => $message,
            'image_url' => $imageUrl,
            'keyboard' => $keyboard,
            'parse_mode' => 'HTML',
        ];
    }

    /**
     * Render Return to Waypoint Confirmation View.
     * Shows waypoint details, costs, and confirm/cancel options.
     */
    private function renderReturnConfirmView(array $session): array {
        // Get return costs from GameEngine (separation of concerns: GameEngine calculates, ViewManager formats)
        $returnData = $this->gameEngine->calculateReturnCosts($session);
        $lastWaypoint = $returnData['last_waypoint'];
        $fuelCost = $returnData['fuel_cost'];
        $foodCost = $returnData['food_cost'];
        $backtrackKm = $returnData['backtrack_km'];
        $days = $returnData['days'];

        $message  = "↩️ <b>Return to Last Waypoint?</b>\n\n";
        $message .= "You're considering turning back to:\n";
        $message .= "{$lastWaypoint['symbol']} <b>{$lastWaypoint['name']}</b>\n\n";

        $message .= "📊 <b>Cost to Return:</b>\n";
        $message .= "⏱️ Time: <b>{$days} days</b>\n";
        $message .= "⛽ Fuel: <b>{$fuelCost} units</b>\n";
        $message .= "🍖 Food: <b>{$foodCost} units</b>\n";
        $message .= "📏 Distance: <b>" . FormatHelpers::formatDistance($backtrackKm) . " (backtrack)</b>\n\n";

        $message .= "⚠️ <b>Note:</b> Returning will reset your gravitational assist (speed → 1×)\n\n";
        $message .= "Are you sure you want to return?";

        $keyboard = [
            [
                ['text' => '✅ Confirm Return', 'callback_data' => 'action|return_confirm'],
                ['text' => '❌ Cancel', 'callback_data' => 'action|journey'],
            ],
        ];

        return [
            'message' => $message,
            'image_url' => null,
            'keyboard' => $keyboard,
            'parse_mode' => 'HTML',
        ];
    }

    /**
     * Render Abandon Confirmation View.
     */
    private function renderAbandonConfirmView(array $session): array {
        $message = $this->formatAbandonConfirmation($session);

        return [
            'message' => $message,
            'image_url' => $this->getContextImageUrl('journey', $session),
            'keyboard' => $this->getAbandonConfirmKeyboard(),
            'parse_mode' => 'HTML',
        ];
    }

    /**
     * Render Aesthetic Selection View.
     */
    private function renderAestheticView(array $session): array {
        $aestheticsData = $this->loadAesthetics();
        $aesthetics = $aestheticsData['aesthetics'] ?? [];
        $current = $session['aesthetic'] ?? ($aestheticsData['default'] ?? 'main');

        $message = "🎨 <b>SELECT AESTHETIC</b>\n\n";
        $message .= "Choose your preferred visual style:\n\n";

        foreach ($aesthetics as $aesthetic) {
            $icon = $aesthetic['icon'] ?? '🎨';
            $name = $aesthetic['name'] ?? $aesthetic['id'];
            $desc = $aesthetic['description'] ?? '';
            $message .= "{$icon} <b>{$name}</b> — {$desc}\n";
        }

        $message .= "\nCurrent: <b>" . ucfirst($current) . "</b>";

        return [
            'message' => $message,
            'image_url' => $this->getContextImageUrl('title', $session),
            'keyboard' => $this->getAestheticKeyboard($current),
            'parse_mode' => 'HTML',
        ];
    }

    /**
     * Render Start View (entry point for new/returning players).
     *
     * Shows different content based on whether player has active session:
     * - No session: Full start menu with aesthetic selection, new journey, etc.
     * - Active session: Resume or abandon options only
     */
    private function renderStartView(?array $session, array $context): array {
        $chatId = $context['chatId'] ?? null;

        if ($session !== null) {
            // Player has active session - show resume or abandon
            $message = $this->formatActiveJourneyWarning($session);
            $keyboard = $this->getStartViewKeyboard(true, $chatId);
            $imageUrl = null;
        } else {
            // No session - show full start menu
            $introText = "Guide your chad 2.8 billion kilometers through space to establish humanity's first colony on Uranus.";
            $message = $this->formatMainMenuTitle($introText);
            $keyboard = $this->getStartViewKeyboard(false, $chatId);
            $imageUrl = $this->getContextImageUrl('title');
        }

        return [
            'message' => $message,
            'image_url' => $imageUrl,
            'keyboard' => $keyboard,
            'parse_mode' => 'HTML',
        ];
    }

    /**
     * Render Main Menu View (in-game navigation hub).
     *
     * Requires active session. Shows comprehensive navigation options
     * for players to access all game features during gameplay.
     */
    private function renderMainMenuView(?array $session, array $context): array {
        if ($session === null) {
            // No session - this shouldn't happen, but provide fallback
            return [
                'message' => "⚠️ No active session. Please start a new journey!",
                'image_url' => null,
                'keyboard' => $this->getNewJourneyButtonKeyboard(),
                'parse_mode' => 'HTML',
            ];
        }

        // Show main menu with current journey status
        $day = $session['progress']['day'];
        $distanceMkm = round($session['progress']['distance'] / 1000000);
        $status = $session['status'] ?? 'unknown';

        $message = "🏠 <b>MAIN MENU</b>\n\n";
        $message .= "📍 Current Status: <b>" . ucfirst(str_replace('_', ' ', $status)) . "</b>\n";
        $message .= "📅 Day {$day} | 🛸 {$distanceMkm} million km\n\n";
        $message .= "What would you like to do?";

        $chatId = $context['chatId'] ?? null;

        return [
            'message' => $message,
            'image_url' => $this->getContextImageUrl('title', $session),
            'keyboard' => $this->getMainMenuKeyboard($chatId),
            'parse_mode' => 'HTML',
        ];
    }

    /**
     * Format resource summary for display.
     * Delegates to FormatHelpers::formatResources for consistency across views.
     * Kept public for backward compatibility.
     *
     * @param array $session
     * @return string
     */
    public function formatResourcesSummary(array $session): string {
        return FormatHelpers::formatResources($session, 'full');
    }

    // formatEth() moved to FormatHelpers class for consistency

    /**
     * Format resource changes (before → after) for post-event display.
     * Only shows resources that actually changed.
     *
     * @param array $changes Map of resource => ['before' => X, 'after' => Y, 'delta' => Z]
     * @return string
     */
    public function formatResourceChanges(array $changes): string {
        if (empty($changes)) {
            return '';
        }

        $msg = "📊 <b>CHANGES FROM EVENT:</b>\n";

        // Resource display config: emoji, label, suffix
        $resourceConfig = [
            'fuel' => ['⛽', 'Fuel', ''],
            'food' => ['🍖', 'Food', ''],
            'chad' => ['👥', 'Chad', ''],
            'hull' => ['🛡️', 'Hull', '%'],
            'vibes' => ['😊', 'Vibes', '%'],
            'eth' => ['💰', 'ETH', ''],
        ];

        foreach ($changes as $resource => $change) {
            $config = $resourceConfig[$resource] ?? ['📦', ucfirst($resource), ''];
            $emoji = $config[0];
            $label = $config[1];
            $suffix = $config[2];

            $before = $change['before'];
            $after = $change['after'];
            $delta = $change['delta'];

            // Format ETH with decimals
            if ($resource === 'eth') {
                $beforeStr = FormatHelpers::formatEth($before);
                $afterStr = FormatHelpers::formatEth($after);
                $deltaStr = FormatHelpers::formatEth(abs($delta));
                $sign = $delta > 0 ? '+' : '-';
                $msg .= "{$emoji} {$label}: {$beforeStr} → {$afterStr} ({$sign}{$deltaStr})\n";
            } else {
                $sign = $delta > 0 ? '+' : '';
                $msg .= "{$emoji} {$label}: {$before}{$suffix} → {$after}{$suffix} ({$sign}{$delta}{$suffix})\n";
            }
        }

        return $msg;
    }

    // =========================================================================
    // KEYBOARD METHODS
    // =========================================================================

    /**
     * Get shop keyboard with context-aware back button.
     */
    public function getShopKeyboard(array $session): array {
        $buttons = [
            [['text' => '⛽ +100 Fuel | 1.000 ETH',    'callback_data' => 'shop|fuel|100']],
            [['text' => '🍖 +50 Food | 0.500 ETH',     'callback_data' => 'shop|food|50']],
            [['text' => '🔧 +20% Hull | 2.000 ETH',    'callback_data' => 'shop|repair|20']],
        ];

        if ($session['status'] === 'pre_launch') {
            $buttons[] = [['text' => '🚀 Launch Ship', 'callback_data' => 'shop|launch|0']];
        } elseif ($session['status'] === 'at_station') {
            $buttons[] = [['text' => '↩️ Back to Station', 'callback_data' => 'action|station']];
        } else {
            $buttons[] = [['text' => '⬅️ Back', 'callback_data' => 'action|continue']];
        }

        return [
            'inline_keyboard' => $buttons,
        ];
    }

    /**
     * Get waypoint keyboard (stop or skip decision).
     */
    public function getWaypointKeyboard(array $waypoint): array {
        $waypointId = $waypoint['id'] ?? $waypoint['name'] ?? 'unknown';

        return [
            'inline_keyboard' => [
                [['text' => '🛒 Stop for Supplies', 'callback_data' => "station|stop|{$waypointId}"]],
                [['text' => '➡️ Skip Waypoint', 'callback_data' => "station|skip|{$waypointId}"]],
            ],
        ];
    }

    /**
     * Get simple continue keyboard (after skipping waypoint).
     */
    public function getContinueKeyboard(): array {
        return [
            'inline_keyboard' => [
                [['text' => '▶️ Continue', 'callback_data' => 'action|continue']],
            ],
        ];
    }

    /**
     * Get waypoint keyboard (when stopped at a waypoint).
     */
    public function getStationKeyboard(array $session): array {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '🛒 Shop',  'callback_data' => 'action|shop'],
                    ['text' => '😴 Rest',  'callback_data' => 'action|rest'],
                ],
                [['text' => '➡️ Continue Journey', 'callback_data' => 'action|continue']],
                [['text' => '🗺️ Map', 'callback_data' => 'action|map']],
                [['text' => '🏠 Main Menu', 'callback_data' => 'menu|main']],
            ],
        ];
    }

    /**
     * Get journey keyboard (always in-space state).
     *
     * Note: Journey View should only be shown when status = 'in_space'.
     * When stopped at a station (status = 'at_station'), the Station View is shown instead.
     * Shop is NEVER available in Journey View - only at stations.
     */
    public function getJourneyKeyboard(array $session): array {
        $buttons = [
            [['text' => '▶️ Continue', 'callback_data' => 'action|continue']],
            [
                ['text' => '📊 Status', 'callback_data' => 'action|status'],
                ['text' => '🗺️ Map',    'callback_data' => 'action|map'],
            ],
            [['text' => '🦌 Hunt Resources', 'callback_data' => 'action|hunt']],
            [
                ['text' => '↩️ Return to Last Waypoint', 'callback_data' => 'action|return'],
                ['text' => '🏠 Main Menu',               'callback_data' => 'menu|main'],
            ],
        ];

        return ['inline_keyboard' => $buttons];
    }

    /**
     * Get status view keyboard.
     */
    public function getStatusKeyboard(array $session): array {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '🗺️ Map',             'callback_data' => 'action|map'],
                    ['text' => '🏠 Back to Journey', 'callback_data' => 'action|journey'],
                ],
            ],
        ];
    }

    /**
     * Get map view keyboard.
     */
    public function getMapKeyboard(): array {
        return [
            'inline_keyboard' => [
                [
                    ['text' => '📊 Status',          'callback_data' => 'action|status'],
                    ['text' => '🏠 Back to Journey', 'callback_data' => 'action|journey'],
                ],
            ],
        ];
    }

    /**
     * Get post-event keyboard.
     */
    public function getPostEventKeyboard(): array {
        return [
            'inline_keyboard' => [
                [['text' => '🏠 Back to Journey', 'callback_data' => 'action|journey']],
            ],
        ];
    }

    /**
     * Get game over keyboard.
     */
    public function getGameOverKeyboard(): array {
        return [
            'inline_keyboard' => [
                [['text' => '🚀 New Journey', 'callback_data' => 'menu|startnewgame']],
                [
                    ['text' => '🏆 Leaderboard', 'callback_data' => 'menu|leaderboard'],
                    ['text' => '📊 My Stats',    'callback_data' => 'action|mystats'],
                ],
            ],
        ];
    }

    /**
     * Get victory keyboard.
     */
    public function getVictoryKeyboard(): array {
        return [
            'inline_keyboard' => [
                [['text' => '🚀 Play Again', 'callback_data' => 'menu|startnewgame']],
                [
                    ['text' => '🏆 Leaderboard',  'callback_data' => 'menu|leaderboard'],
                    ['text' => '📊 My Stats',      'callback_data' => 'action|mystats'],
                ],
                [['text' => '📤 Share Victory',   'callback_data' => 'action|share']],
            ],
        ];
    }

    /**
     * Get generic back button.
     */
    public function getBackButton(): array {
        return [
            'inline_keyboard' => [
                [['text' => '🏠 Back to Journey', 'callback_data' => 'menu|journey']],
            ],
        ];
    }

    /**
     * Create event choice keyboard with callback data validation.
     */
    public function createEnhancedEventKeyboard(array $event): array {
        $rows = [];
        $maxBytes = defined('TELEGRAM_CALLBACK_DATA_MAX_BYTES') ? TELEGRAM_CALLBACK_DATA_MAX_BYTES : 64;
        $ownerReserve = defined('CALLBACK_DATA_OWNER_STAMP_RESERVE') ? CALLBACK_DATA_OWNER_STAMP_RESERVE : 15;

        foreach ($event['choices'] ?? [] as $choice) {
            $callbackData = "event|{$event['id']}|{$choice['id']}";
            if (strlen($callbackData) > ($maxBytes - $ownerReserve)) {
                error_log(
                    "ViewManager: callback_data too long for event {$event['id']} " .
                    "choice {$choice['id']}: " . strlen($callbackData) . " bytes " .
                    "(limit " . ($maxBytes - $ownerReserve) . " before owner stamp)"
                );
                // Skip this choice to avoid API errors
                continue;
            }
            $rows[] = [['text' => $choice['text'], 'callback_data' => $callbackData]];
        }
        return $rows;
    }

    // =========================================================================
    // BOT HANDLER KEYBOARDS
    // =========================================================================

    /**
     * Get start view keyboard (entry point - no session or active session).
     */
    public function getStartViewKeyboard(bool $hasActiveSession, $chatId): array {
        $buttons = [];
        $isGroup = ($chatId < 0);

        if ($hasActiveSession) {
            // Active session: only show resume and abandon options
            $buttons[] = [['text' => '▶️ Resume Journey', 'callback_data' => 'menu|continue']];
            $buttons[] = [['text' => '🗑️ Abandon & Start New', 'callback_data' => 'menu|abandon']];
        } else {
            // No session: show full start menu
            $buttons[] = [['text' => '🚀 New Journey', 'callback_data' => 'menu|startnewgame']];
            $buttons[] = [['text' => '🏆 Leaderboard', 'callback_data' => 'menu|leaderboard']];
            $buttons[] = [['text' => '👥 Who\'s Playing', 'callback_data' => 'menu|whosplaying']];
            $buttons[] = [$isGroup
                ? ['text' => '💬 Play in DMs', 'callback_data' => 'menu|privatedm']
                : ['text' => '👥 Play in Group', 'callback_data' => 'menu|backtogroup']];
            $buttons[] = [['text' => '❓ How To Play', 'callback_data' => 'menu|help']];
        }

        return ['inline_keyboard' => $buttons];
    }

    /**
     * Get main menu keyboard (in-game navigation hub - requires active session).
     */
    public function getMainMenuKeyboard($chatId = null): array {
        $buttons = [];
        $isGroup = ($chatId !== null && $chatId < 0);

        // Always has session in main menu
        $buttons[] = [['text' => '▶️ Resume Journey', 'callback_data' => 'menu|continue']];
        $buttons[] = [['text' => '🎨 Aesthetic', 'callback_data' => 'menu|aesthetic']];
        $buttons[] = [$isGroup
            ? ['text' => '💬 Play in DMs', 'callback_data' => 'menu|privatedm']
            : ['text' => '👥 Play in Group', 'callback_data' => 'menu|backtogroup']];
        $buttons[] = [['text' => '👥 Who\'s Playing', 'callback_data' => 'menu|whosplaying']];
        $buttons[] = [['text' => '❓ How To Play', 'callback_data' => 'menu|help']];
        $buttons[] = [['text' => '🗑️ Abandon & Start New', 'callback_data' => 'menu|abandon']];

        return ['inline_keyboard' => $buttons];
    }

    /**
     * Get help keyboard (context-aware back button).
     */
    public function getHelpKeyboard(bool $isPreLaunch): array {
        return [
            'inline_keyboard' => [
                [['text' => $isPreLaunch ? '🚀 Back to Launch' : '🏠 Back to Main Menu',
                  'callback_data' => $isPreLaunch ? 'menu|launch' : 'menu|main']],
            ]
        ];
    }

    /**
     * Get launch view keyboard (shown before ship launches).
     */
    public function getNewGameViewKeyboard(): array {
        return [
            'inline_keyboard' => [
                [['text' => '🚀 Launch Ship', 'callback_data' => 'action|launch']],
                [['text' => '🎨 Select Aesthetic', 'callback_data' => 'menu|aesthetic']],
                [['text' => '❓ How to Play', 'callback_data' => 'menu|help']],
                [['text' => '❌ Cancel',      'callback_data' => 'menu|main']],
            ],
        ];
    }

    /**
     * Get launch sequence keyboard (single GO button).
     */
    public function getLaunchSequenceKeyboard(): array {
        return [
            'inline_keyboard' => [
                [['text' => '🚀 GO', 'callback_data' => 'action|go']],
            ],
        ];
    }

    /**
     * Get abandon confirmation keyboard.
     */
    public function getAbandonConfirmKeyboard(): array {
        return [
            'inline_keyboard' => [
                [['text' => '🗑️ Yes, Abandon Mission', 'callback_data' => 'menu|abandonconfirm']],
                [['text' => '↩️ No, Keep Going',        'callback_data' => 'menu|continue']],
            ],
        ];
    }

    /**
     * Get single "New Journey" button keyboard.
     */
    public function getNewJourneyButtonKeyboard(): array {
        return [
            'inline_keyboard' => [
                [['text' => '🚀 New Journey', 'callback_data' => 'menu|startnewgame']],
            ]
        ];
    }

    /**
     * Get aesthetic selection keyboard.
     */
    public function getAestheticKeyboard(string $current): array {
        $aestheticsData = $this->loadAesthetics();
        $aesthetics = $aestheticsData['aesthetics'] ?? [];

        $buttons = [];
        foreach ($aesthetics as $aesthetic) {
            $id = $aesthetic['id'];
            $name = $aesthetic['name'] ?? ucfirst($id);
            $icon = $aesthetic['icon'] ?? '🎨';
            $checkmark = ($current === $id) ? '✅ ' : '';

            $buttons[] = [['text' => "{$checkmark}{$icon} {$name}", 'callback_data' => "aesthetic|{$id}"]];
        }

        $buttons[] = [['text' => '🏠 Back to Main Menu', 'callback_data' => 'menu|main']];

        return ['inline_keyboard' => $buttons];
    }

    // =========================================================================
    // MESSAGE FORMATTING
    // =========================================================================

    /**
     * Format help/how-to-play message.
     */
    public function formatHelpMessage(): string {
        $message  = "🚀 <b>OCCUPY URANUS — HOW TO PLAY</b>\n\n";

        // Objective
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "🎯 <b>YOUR MISSION</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "Travel 2.8 billion km across the solar system to reach Uranus. Keep your chad alive, manage resources, and make tough decisions!\n\n";

        // Getting Started
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "🎮 <b>GETTING STARTED</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "1. Start a new journey\n";
        $message .= "2. Click <b>Launch Ship</b> when ready\n";
        $message .= "3. Press <b>Continue</b> to advance each day\n\n";
        $message .= "You begin with:\n";
        $message .= "⛽ 400 fuel | 🍖 800 food | 💰 15.000 ETH\n";
        $message .= "🛡️ 100% hull | 👥 5 chad | 😊 100% vibes\n\n";

        // Resources
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "📦 <b>RESOURCES</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "⛽ <b>Fuel</b> — 5 per day (constant)\n";
        $message .= "🍖 <b>Food</b> — 3 per chad per day\n";
        $message .= "🛡️ <b>Hull</b> — Ship damage (0% = game over)\n";
        $message .= "👥 <b>Chad</b> — Your team (max 5)\n";
        $message .= "😊 <b>Vibes</b> — Affects event frequency\n";
        $message .= "   • Low vibes = more events\n";
        $message .= "   • Critical vibes = chad desertion!\n";
        $message .= "💰 <b>ETH</b> — Currency for buying supplies at stations\n\n";

        // Actions
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "⚡ <b>ACTIONS</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "▶️ <b>Continue</b> — Travel 20M km (1 day)\n";
        $message .= "   • Speed doubles to 40M km/day after passing Jupiter!\n";
        $message .= "😴 <b>Rest</b> — Restore hull + vibes (at stations only)\n";
        $message .= "   • Advances 1 day, costs food (no fuel!)\n";
        $message .= "   • Only available when docked at a station\n";
        $message .= "🦌 <b>Hunt</b> — Gather resources (while traveling)\n";
        $message .= "↩️ <b>Return to Waypoint</b> — Backtrack to last station\n";
        $message .= "   • Costs fuel + food to travel back\n";
        $message .= "   • Access shop and rest at station\n";
        $message .= "🛒 <b>Shop</b> — Buy supplies (at stations only)\n\n";

        // Waypoints
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "🛰️ <b>SPACE STATIONS</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "When reaching a station:\n";
        $message .= "• <b>Stop</b> → Shop, rest, restore vibes\n";
        $message .= "• <b>Skip</b> → Get 2× speed boost!\n\n";
        $message .= "⚠️ Stations are the ONLY place to rest and restore vibes!\n";
        $message .= "💡 Skip for speed, but you can't rest until the next station.\n\n";

        // Events
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "⚠️ <b>RANDOM EVENTS</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "Events happen randomly during travel. Your choices matter!\n";
        $message .= "• Some give rewards\n";
        $message .= "• Some cost resources\n";
        $message .= "• Some are... just weird\n\n";

        // Tips
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "💡 <b>PRO TIPS</b>\n";
        $message .= "━━━━━━━━━━━━━━━━━━━━\n";
        $message .= "✓ Hunt for resources regularly\n";
        $message .= "✓ Stop at stations when vibes drop below 30%\n";
        $message .= "✓ Return to last station if critically low on supplies\n";
        $message .= "✓ Skip stations only if vibes + supplies are good\n";
        $message .= "✓ Watch your resource estimates!\n\n";

        $message .= "━━━━━━━━━━━━━━━━━━━━\n\n";
        $message .= "⚖️ The game is balanced but challenging — you need a mix of shopping, hunting, and careful resource management to reach Uranus!\n\n";
        $message .= "<i>Good luck, Captain! The journey is long, but Uranus awaits! 🫡</i>";

        return $message;
    }

    /**
     * Format new game message shown in pre-launch setup screen.
     */
    public function formatNewGameMessage(): string {
        $message  = "🚀 <b>JOURNEY TO URANUS BEGINS!</b>\n\n";
        $message .= "Your Chad crew is assembled, ship is fueled, and supplies are loaded.\n";
        $message .= "The 2.8 billion kilometer journey awaits!\n\n";
        $message .= "Press <b>Launch Ship</b> to start traveling. Good luck, Captain! 🫡";
        return $message;
    }

    /**
     * Format waypoint skip message (bypassing without stopping).
     */
    public function formatWaypointSkipMessage(string $waypointName, bool $wasAlreadyBoosted): string {
        $message = "➡️ <b>Bypassing {$waypointName}</b>\n\nYou've continued past this waypoint without stopping.\n\n";
        if ($wasAlreadyBoosted) {
            $message .= "🌌 <i>Gravitational assist maintained (2× speed continues)</i>";
        } else {
            $message .= "🚀 <b>Gravitational Assist Engaged!</b>\n<i>Your speed is now doubled until you stop at another waypoint.</i>";
        }
        return $message;
    }

    /**
     * Format speed boost lost notification.
     */
    public function formatSpeedBoostLostMessage(): string {
        return "⚠️ <i>Gravitational assist lost (speed returned to normal)</i>\n\n";
    }

    /**
     * Format active journey warning (shown on /start when player has active session).
     */
    public function formatActiveJourneyWarning(array $session): string {
        $day = $session['progress']['day'];
        $distance = number_format($session['progress']['distance'] / 1000000, 1);
        $chad = $session['resources']['chad'];

        $message  = "⚠️ <b>You have an active journey!</b>\n\n";
        $message .= "📅 Day: $day\n";
        $message .= "📍 Distance: {$distance}M km\n";
        $message .= "👥 Chad: {$chad}/5\n\n";
        $message .= "What would you like to do?";

        return $message;
    }

    /**
     * Format main menu title message.
     */
    public function formatMainMenuTitle(string $introText): string {
        $message  = "🚀 <b>OCCUPY URANUS</b> 🪐\n\n";
        $message .= "Welcome to the journey of a lifetime!\n\n";
        $message .= $introText . "\n\n";
        return $message;
    }

    /**
     * Format abandon journey confirmation.
     */
    public function formatAbandonConfirmation(array $session): string {
        $day = $session['progress']['day'];
        $distance = number_format($session['progress']['distance'] / 1000000, 1);
        $chad = $session['resources']['chad'];

        $message  = "⚠️ <b>Abandon Journey?</b>\n\n";
        $message .= "You are about to permanently abandon your mission.\n\n";
        $message .= "📅 Day: <b>$day</b>\n";
        $message .= "📍 Distance: <b>{$distance}M km</b>\n";
        $message .= "👥 Chad: <b>$chad/5</b>\n\n";
        $message .= "<b>This cannot be undone.</b>\n\n";
        $message .= "Are you sure?";

        return $message;
    }

    /**
     * Format journey abandoned message.
     */
    public function formatJourneyAbandonedMessage(): string {
        $message  = "🗑️ <b>Journey abandoned.</b>\n\n";
        $message .= "🚀 <b>OCCUPY URANUS</b> 🪐\n\n";
        return $message;
    }

    /**
     * Format private DM prompt.
     */
    public function formatPrivateDMPrompt(): string {
        $message  = "🔒 <b>Play Game in Private Chat?</b>\n\n";
        $message .= "Your journey will begin in our private conversation.\n";
        $message .= "All game updates will be sent to you privately instead of the group.\n\n";
        $message .= "This keeps the group chat clean while you play!";
        return $message;
    }

    /**
     * Format game moved to private message.
     */
    public function formatGameMovedToPrivateMessage(): string {
        $message  = "🔒 <b>Game Moved to Private Chat</b>\n\n";
        $message .= "Your journey continues in our private chat. Check your DMs!";
        return $message;
    }

    /**
     * Format welcome to private mode message.
     */
    public function formatWelcomeToPrivateModeMessage(): string {
        $message  = "🔒 <b>Welcome to Private Mode!</b>\n\n";
        $message .= "Your journey continues here. All updates will be sent to you privately.";
        return $message;
    }

    /**
     * Format starting private game message.
     */
    public function formatStartingPrivateGameMessage(): string {
        $message  = "🔒 <b>Starting Private Game</b>\n\n";
        $message .= "Check your DMs to continue!";
        return $message;
    }

    /**
     * Format active journey warning for new game.
     */
    public function formatActiveJourneyNewGameWarning(int $day, string $distance): string {
        $message  = "⚠️ <b>You have an active journey!</b>\n\n";
        $message .= "📅 Day: <b>$day</b> | 📍 Distance: <b>{$distance}M km</b>\n\n";
        $message .= "Starting a new game will <b>abandon your current mission</b>.\n";
        return $message;
    }

    /**
     * Format game moved back to group message.
     */
    public function formatGameMovedBackToGroupMessage(): string {
        $message  = "🌐 <b>Game Moved Back to Group Chat</b>\n\n";
        $message .= "Your journey will now continue in the group chat.\n";
        $message .= "Use /privatedm to move back to private chat anytime.";
        return $message;
    }

    /**
     * Format game returned to group notification.
     */
    public function formatGameReturnedToGroupMessage(?string $username): string {
        $message  = "🌐 <b>Game Returned to Group</b>\n\n";
        $message .= ($username ? "@{$username}'s" : "A player's") . " journey continues here!";
        return $message;
    }

    // =========================================================================
    // LEGACY FORMAT METHODS (with images)
    // =========================================================================

    /**
     * Format a view with its corresponding image.
     * Legacy method - prefer using render() for new code.
     *
     * @param  string     $view      'journey' | 'status' | 'game_over' | 'victory'
     * @param  array      $session   Current session state
     * @param  int|null   $userId    User ID
     * @param  string|null $username Username
     * @return array  ['message' => string, 'image_url' => string|null]
     */
    public function formatViewWithImage(string $view, array $session, $userId = null, ?string $username = null): array {
        if ($view === 'game_over') {
            return $this->formatGameOverWithImage($session, ['reason' => 'Unknown']);
        }

        if ($view === 'victory') {
            return $this->formatVictoryWithImage($session, []);
        }

        if ($view !== 'journey' && $view !== 'status') {
            throw new \InvalidArgumentException("Unsupported view '$view' for formatViewWithImage");
        }

        $includeChadDetails = ($view === 'status');
        $imageContext       = ($view === 'journey') ? 'day' : 'status';

        return [
            'message'   => $this->gameText->formatStatus($session, $userId, $username, $includeChadDetails),
            'image_url' => $this->getContextImageUrl($imageContext, $session),
        ];
    }

    /**
     * Format the game-over screen with image.
     *
     * @param  array  $session
     * @param  array  $result   Result array containing 'reason'
     * @return array  ['message' => string, 'image_url' => string|null]
     */
    public function formatGameOverWithImage(array $session, array $result): array {
        $message  = "💀 <b>MISSION FAILED</b>\n\n";
        $message .= $this->gameText->gameOverText($result['reason'] ?? '') . "\n\n";
        $message .= "📅 You survived <b>{$session['progress']['day']} days</b>\n";
        $message .= "📍 Reached <b>" . number_format($session['progress']['distance'] / 1000000, 1) . "M km</b>\n";

        // Calculate progress percentage
        $progressPercent = round(($session['progress']['distance'] / TOTAL_DISTANCE) * 100, 1);
        $message .= "🎯 Progress: <b>{$progressPercent}%</b>\n\n";

        $message .= "👥 Chad survived: <b>{$session['resources']['chad']}</b>/" . MAX_CREW . "\n\n";
        $message .= "<i>Better luck next time, Captain.</i>";

        return [
            'message'   => $message,
            'image_url' => $this->getContextImageUrl('gameover', $session),
        ];
    }

    /**
     * Format the victory screen with image.
     *
     * @param  array  $session
     * @param  array  $result   Result array (currently unused)
     * @return array  ['message' => string, 'image_url' => string|null]
     */
    public function formatVictoryWithImage(array $session, array $result): array {
        $message  = "🏆 <b>VICTORY! YOU REACHED URANUS!</b> 🪐\n\n";
        $message .= "After <b>{$session['progress']['day']} days</b> of travel, ";
        $message .= "your chad has successfully colonised Uranus!\n\n";
        $message .= "👥 Chad survived: <b>{$session['resources']['chad']}</b>/" . MAX_CREW . "\n";
        $message .= "🛡️ Hull integrity: <b>{$session['resources']['hull']}%</b>\n";
        $message .= "⛽ Fuel remaining: <b>{$session['resources']['fuel']}</b> units\n";
        $message .= "🍖 Food remaining: <b>{$session['resources']['food']}</b> units\n\n";
        $message .= "<i>Congratulations, Captain! History will remember you.</i>";

        return [
            'message'   => $message,
            'image_url' => $this->getContextImageUrl('victory', $session),
        ];
    }

    // =========================================================================
    // IMAGE SELECTION (Presentation Logic)
    // =========================================================================

    /**
     * Determine the image to display for a given context.
     * Returns path to local file or null if no image should be shown.
     * TelegramAPI handles caching of file_ids automatically.
     *
     * Contexts:
     *  - 'status'      → zone-based status image (status_{zone}.jpg)
     *  - 'day'         → journey/day image with priority:
     *                    milestone PNG → waypoint PNG → day_{zone}.jpg
     *  - 'gameover'    → gameover.jpg
     *  - 'victory'     → victory.jpg
     *  - 'leaderboard' → Leaderboard.jpg (respects aesthetic if session exists)
     *  - 'event'       → random event PNG
     *  - 'waypoint'    → current/last waypoint PNG
     *  - 'shop'        → current station PNG, or Continue-01.png fallback
     *  - 'station'     → alias for 'shop'
     *  - 'title'       → Title-01.jpg (no session required)
     *
     * @param  string       $context
     * @param  array|null   $session
     * @param  string|null  $eventOutcome  For post_event context: 'success' or 'failure'
     * @return string|null  Path to image file under MEDIA_DIR, or null
     */
    public function getContextImageUrl(string $context, ?array $session = null, ?string $eventOutcome = null): ?string {
        if (!defined('MEDIA_DIR') || MEDIA_DIR === '') {
            return null;
        }

        $imageName = null;

        // Contexts that do not require session state (but respect aesthetic if session exists)
        $aesthetic = $session['aesthetic'] ?? 'main';

        if ($context === 'title') {
            $imageName = $this->findStaticImage('Title-01', $aesthetic);
        } elseif ($context === 'newgame') {
            $imageName = $this->findStaticImage('NewGame-01', $aesthetic);
        } elseif ($context === 'launch') {
            $imageName = $this->findStaticImage('Launch-01', $aesthetic);
        } elseif ($context === 'leaderboard') {
            $imageName = $this->findStaticImage('Leaderboard', $aesthetic);
        } elseif ($context === 'whosplaying') {
            $imageName = $this->findStaticImage('WhosPlaying', $aesthetic);
        } else {
            if ($session === null) {
                return null;
            }

            $zone = $this->getDistanceZone($session['progress']['distance']);

            if ($context === 'status') {
                $imageName = $this->selectRandomZoneImage('status', $zone, $aesthetic);
            } elseif ($context === 'gameover') {
                $imageName = $this->findStaticImage('gameover', $aesthetic);
            } elseif ($context === 'victory') {
                $imageName = $this->findStaticImage('victory', $aesthetic);
            } elseif ($context === 'event') {
                // Try event-specific image first (e.g., Event-asteroid_field.jpg)
                $eventId = $session['pending_event'] ?? null;

                if ($eventId !== null) {
                    $eventSpecificImage = "Event-{$eventId}.jpg";
                    $eventSpecificPath = MEDIA_DIR . "/{$aesthetic}/{$eventSpecificImage}";

                    if (file_exists($eventSpecificPath)) {
                        $imageName = $eventSpecificImage;
                    } else {
                        // Log warning for main aesthetic only (other aesthetics expected to use generic)
                        if ($aesthetic === 'main' && defined('DEBUG_MODE') && DEBUG_MODE) {
                            error_log("Event-specific image not found for event '{$eventId}' in main aesthetic, falling back to generic");
                        }
                        // Fall back to generic event images
                        $eventImages = ['Event-01.jpg', 'Event-02.jpg', 'Event-03.jpg', 'Event-04.jpg'];
                        $imageName   = $eventImages[array_rand($eventImages)];
                    }
                } else {
                    // No event ID available, use generic
                    $eventImages = ['Event-01.jpg', 'Event-02.jpg', 'Event-03.jpg', 'Event-04.jpg'];
                    $imageName   = $eventImages[array_rand($eventImages)];
                }
            } elseif ($context === 'post_event') {
                // Select image based on event outcome (success or failure)
                $outcome = $eventOutcome ?? 'success';
                if ($outcome === 'failure') {
                    $failureImages = ['post_event_failure_01.jpg', 'post_event_failure_02.jpg', 'post_event_failure_03.jpg'];
                    $imageName = $failureImages[array_rand($failureImages)];
                } else {
                    $successImages = ['post_event_success_01.jpg', 'post_event_success_02.jpg', 'post_event_success_03.jpg'];
                    $imageName = $successImages[array_rand($successImages)];
                }
            } elseif ($context === 'station' || $context === 'waypoint') {
                $waypoint = $session['at_waypoint'] ?? $this->gameEngine->getCurrentWaypoint($session);
                if ($waypoint !== null) {
                    $imageName = $this->getWaypointImageName($waypoint['name']);
                }
            } elseif ($context === 'shop') {
                $waypoint = $session['at_waypoint'] ?? $this->gameEngine->getCurrentWaypoint($session);
                if ($waypoint !== null) {
                    $imageName = $this->getWaypointImageName($waypoint['name']);
                }
                if ($imageName === null) {
                    $imageName = 'Continue-01.jpg';
                }
            } elseif ($context === 'day') {
                // 1. Milestone images (25/50/75)
                if (!empty($session['milestone_just_reached'])) {
                    $milestone       = (int) $session['milestone_just_reached'];
                    $milestoneImages = [
                        25 => 'milestone-25.jpg',
                        50 => 'milestone-50.jpg',
                        75 => 'milestone-75.jpg',
                    ];
                    if (isset($milestoneImages[$milestone])) {
                        $imageName = $milestoneImages[$milestone];
                    }
                }

                // 2. Waypoint images when docked or just reached
                if ($imageName === null && !empty($session['at_waypoint'])) {
                    $imageName = $this->getWaypointImageName($session['at_waypoint']['name']);
                }

                // 3. Fallback to zone-based day image
                if ($imageName === null) {
                    $imageName = $this->selectRandomZoneImage('day', $zone, $aesthetic);
                }
            }
        }

        if ($imageName === null) {
            return null;
        }

        // Return local file path — TelegramAPI will handle caching
        $localPath = MEDIA_DIR . '/' . $aesthetic . '/' . $imageName;
        return (is_file($localPath) && is_readable($localPath)) ? $localPath : null;
    }

    /**
     * Find a static image file with any supported extension.
     * Used for single static images like Title-01, victory, gameover, Leaderboard, etc.
     *
     * @param  string  $basename  Base filename without extension (e.g., 'Title-01', 'victory', 'Leaderboard')
     * @param  string  $aesthetic Aesthetic folder name
     * @return string|null  Image filename (basename with extension), or null if not found
     */
    private function findStaticImage(string $basename, string $aesthetic = 'main'): ?string {
        if (!defined('MEDIA_DIR') || !is_dir(MEDIA_DIR)) {
            return null;
        }

        // Try all supported extensions
        $pattern = MEDIA_DIR . "/{$aesthetic}/{$basename}.{jpg,jpeg,png,JPG,JPEG,PNG}";
        $matches = glob($pattern, GLOB_BRACE);

        if (empty($matches)) {
            return null;
        }

        // Return first match (there should only be one)
        return basename($matches[0]);
    }

    /**
     * Select a random image for a given context and zone.
     * Supports multiple numbered variants for visual variety.
     *
     * Naming conventions supported:
     *  - Single image: {context}_{zone}.jpg (e.g., day_earth.jpg)
     *  - Multiple images: {context}_{zone}_01.jpg, {context}_{zone}_02.jpg, etc.
     *  - Mixed extensions: .jpg, .png, .jpeg
     *
     * Examples:
     *  - day_earth.jpg, day_earth_01.jpg, day_earth_02.jpg
     *  - status_inner.jpg, status_inner_01.png, status_inner_02.jpg
     *
     * @param  string  $context  'day' or 'status'
     * @param  string  $zone     'earth', 'inner', 'belt', 'outer', 'uranus'
     * @return string|null  Image filename (basename), or null if none found
     */
    private function selectRandomZoneImage(string $context, string $zone, string $aesthetic = 'main'): ?string {
        if (!defined('MEDIA_DIR') || !is_dir(MEDIA_DIR)) {
            return null;
        }

        // Pattern matches: {context}_{zone}.ext or {context}_{zone}_*.ext
        $pattern = MEDIA_DIR . "/{$aesthetic}/{$context}_{$zone}*.{jpg,jpeg,png,JPG,JPEG,PNG}";
        $matches = glob($pattern, GLOB_BRACE);

        if (empty($matches)) {
            return null;
        }

        // Pick one randomly
        $selectedPath = $matches[array_rand($matches)];
        return basename($selectedPath);
    }

    /**
     * Map a waypoint name to its corresponding image filename, if any.
     *
     * @param  string  $waypointName
     * @return string|null
     */
    private function getWaypointImageName(string $waypointName): ?string {
        $map = [
            'Earth Orbit'            => 'Waypoint-Earth_Launch.jpg',
            'Moon'                   => 'Waypoint-Moon.jpg',
            'Mars Base'              => 'Waypoint-Mars-Flyby.jpg',
            'Ceres Asteroid Station' => 'Waypoint-Ceres_Station.jpg',
            'Jupiter Gas Mine'       => 'Waypoint-Jupiter_Slingshot.jpg',
            'Saturn Outpost'         => 'Waypoint-Saturn_Rings.jpg',
            'Deep Space 69'          => 'Waypoint-Deep_Space_69.jpg',
            'Uranus Arrival'         => 'Waypoint-Uranus_Arrival.jpg',
        ];

        return $map[$waypointName] ?? null;
    }

    /**
     * Determine the distance zone for image selection.
     * Zones correspond loosely to regions of the solar system on the route to Uranus.
     *
     * @param  int  $distance  Current distance in km
     * @return string  'earth' | 'inner' | 'belt' | 'outer' | 'uranus'
     */
    private function getDistanceZone(int $distance): string {
        $pct = ($distance / TOTAL_DISTANCE) * 100;
        if ($pct < 10)  return 'earth';   // Departure / near-Earth
        if ($pct < 35)  return 'inner';   // Inner solar system (Mars region)
        if ($pct < 55)  return 'belt';    // Asteroid belt / Jupiter
        if ($pct < 80)  return 'outer';   // Saturn / mid-journey
        return 'uranus';                   // Final approach
    }

    // =========================================================================
    // FORMATTING HELPERS
    // =========================================================================

    // =========================================================================
    // VIEW-SPECIFIC FORMATTERS (moved from GameEngine)
    // =========================================================================

    /**
     * Format shop view message.
     * Moved from GameEngine - belongs in ViewManager as it's pure presentation.
     *
     * @param  array  $session
     * @param  int|null  $userId
     * @param  string|null  $username
     * @return string
     */
    public function formatShop(array $session, $userId = null, ?string $username = null): string {
        // Difficulty system removed 2026-03-02 - all games use standard prices
        $multiplier = 1.0;

        $message  = "🛒 <b>SPACE PORT SUPPLY SHOP</b>\n\n";
        $message .= "<b>Current Supplies:</b>\n";
        $message .= "⛽ Fuel: <b>{$session['resources']['fuel']}</b> units\n";
        $message .= "🍖 Food: <b>{$session['resources']['food']}</b> units\n";
        $message .= "🛡️ Hull: <b>{$session['resources']['hull']}%</b>\n";
        $message .= "💰 ETH: <b>" . FormatHelpers::formatEth($session['resources']['eth']) . "</b>\n\n";

        $message .= "<b>Available Items:</b>\n";
        $message .= "⛽ Fuel Cells (100 units) — " . FormatHelpers::formatEth(1.000 * $multiplier) . " ETH\n";
        $message .= "🍖 Food Rations (50 units) — " . FormatHelpers::formatEth(0.500 * $multiplier) . " ETH\n";
        $message .= "🔧 Repair Kit (+20 hull) — " . FormatHelpers::formatEth(2.000 * $multiplier) . " ETH\n\n";

        if ($session['status'] === 'pre_launch') {
            $message .= "<i>Stock up and launch when ready!</i>";
        } else {
            $message .= "<i>Resupply your ship before continuing.</i>";
        }

        return $message;
    }

    /**
     * Format shop purchase result message.
     * Shows what was purchased and current resource levels.
     *
     * @param  array  $result   Purchase result from GameEngine
     * @param  array  $session  Updated session state (post-purchase)
     * @return string
     */
    public function formatPurchaseResult(array $result, array $session): string {
        // Handle failure cases
        if (!$result['success']) {
            if ($result['reason'] === 'invalid_item') {
                return "❌ Invalid item";
            }
            if ($result['reason'] === 'insufficient_eth') {
                $needed = FormatHelpers::formatEth($result['cost']);
                $have = FormatHelpers::formatEth($result['eth_available']);
                return "❌ Not enough ETH! Need {$needed}, have {$have}";
            }
            return "❌ Purchase failed";
        }

        // Build success message
        $item = $result['item'];
        $quantityAdded = $result['quantity_added'];
        $cost = FormatHelpers::formatEth($result['cost']);
        $capped = $result['capped'];

        $message = '';

        // Purchase confirmation
        if ($item === 'fuel') {
            $message = "✅ Purchased {$quantityAdded} Fuel Cells for {$cost} ETH!";
            if ($capped) {
                $message .= " (Tank was near capacity — only {$quantityAdded} units added)";
            }
        } elseif ($item === 'food') {
            $message = "✅ Purchased {$quantityAdded} Food Rations for {$cost} ETH!";
            if ($capped) {
                $message .= " (Storage was near capacity — only {$quantityAdded} units added)";
            }
        } elseif ($item === 'repair') {
            $message = "✅ Purchased Repair Kit for {$cost} ETH!";
            if ($capped) {
                $message .= " (Hull was near maximum — only +{$quantityAdded}% added)";
            }
        }

        // Current supplies
        $message .= "\n\n<b>Current Supplies:</b>\n";
        $message .= "⛽ Fuel: <b>{$session['resources']['fuel']}</b> units\n";
        $message .= "🍖 Food: <b>{$session['resources']['food']}</b> units\n";
        $message .= "🛡️ Hull: <b>{$session['resources']['hull']}%</b>\n";
        $message .= "💰 ETH: <b>" . FormatHelpers::formatEth($session['resources']['eth']) . "</b>";

        return $message;
    }

    /**
     * Format event view message.
     * Moved from GameEngine - belongs in ViewManager as it's pure presentation.
     *
     * @param  array  $event
     * @param  int|null  $userId
     * @param  string|null  $username
     * @param  array|null  $session
     * @return string
     */
    public function formatEnhancedEvent(array $event, $userId = null, ?string $username = null, ?array $session = null): string {
        $description = $event['description'] ?? '';
        if ($session !== null) {
            // Use GameEngine to replace chad templates (data gathering)
            $description = $this->gameEngine->replaceChadTemplates($description, $session);
        }

        $message  = "🚨 <b>EVENT!</b> 🚨\n\n";
        $message .= "<b>" . ($event['title'] ?? 'Unknown Event') . "</b>\n\n";
        $message .= $description . "\n";

        // Chad reactions
        if (!empty($event['chad_reactions'])) {
            $message .= "\n<b>Chad Reactions:</b>\n";
            foreach ($event['chad_reactions'] as $reaction) {
                if (is_string($reaction) && $session !== null) {
                    $reaction = $this->parseChadReactionTemplate($reaction, $session);
                }
                $name  = $reaction['name'] ?? 'Chad';
                $role  = $reaction['role']  ?? '';
                $says  = $reaction['says']  ?? '';
                $emoji = $this->getChadEmojiByRole($role);
                $message .= "{$emoji} <b>{$name}</b>: \"{$says}\"\n";
            }
            $message .= "\n";
        }

        $message .= "<b>What do you do?</b>";

        return $message;
    }

    /**
     * Format leaderboard view message.
     * Moved from GameEngine - belongs in ViewManager as it's pure presentation.
     *
     * @param  array  $data  Leaderboard data by difficulty
     * @param  int|null  $userId
     * @param  string|null  $username
     * @return string
     */
    public function formatLeaderboard(array $data, $userId = null, ?string $username = null): string {
        $message  = "🏆 <b>LEADERBOARDS</b>\n\n";

        // Helper function to format date
        $formatDate = function($timestamp) {
            return date('M j', $timestamp);
        };

        // Helper function to format number with commas
        $formatNumber = function($num) {
            return number_format($num);
        };

        // 1. ⚡ Speed Demon
        $message .= "⚡ <b>SPEED DEMON</b> — Fastest Journeys\n";
        $entries = array_slice($data['speed_demon'] ?? [], 0, 5);
        if (empty($entries)) {
            $message .= "   <i>No victories yet!</i>\n\n";
        } else {
            foreach ($entries as $index => $entry) {
                $rank = $index + 1;
                $name = $entry['username'] ?? 'Unknown';
                $days = $entry['days'] ?? '?';
                $chad = $entry['chad'] ?? '?';
                $date = $formatDate($entry['timestamp'] ?? time());
                $isPlayer = $userId && ($entry['user_id'] ?? 0) === $userId;

                $marker = $isPlayer ? '👉 ' : '   ';
                $bold = $isPlayer ? '<b>' : '';
                $endB = $isPlayer ? '</b>' : '';

                $message .= "{$marker}{$rank}. {$bold}{$name}{$endB} — {$days} days\n";
                $message .= "   👥 {$chad}/5 chad — {$date}\n";
            }
            $message .= "\n";
        }

        // 2. ❤️ Perfect Captain
        $message .= "❤️ <b>PERFECT CAPTAIN</b> — Flawless Journeys\n";
        $entries = array_slice($data['perfect_captain'] ?? [], 0, 5);
        if (empty($entries)) {
            $message .= "   <i>No perfect runs yet!</i>\n\n";
        } else {
            foreach ($entries as $index => $entry) {
                $rank = $index + 1;
                $name = $entry['username'] ?? 'Unknown';
                $days = $entry['days'] ?? '?';
                $date = $formatDate($entry['timestamp'] ?? time());
                $isPlayer = $userId && ($entry['user_id'] ?? 0) === $userId;

                $marker = $isPlayer ? '👉 ' : '   ';
                $bold = $isPlayer ? '<b>' : '';
                $endB = $isPlayer ? '</b>' : '';

                $message .= "{$marker}{$rank}. {$bold}{$name}{$endB} — {$days} days\n";
                $message .= "   👥 5/5 chad, 🛡️ 100% — {$date}\n";
            }
            $message .= "\n";
        }

        // 3. 💰 Space Tycoon
        $message .= "💰 <b>SPACE TYCOON</b> — Richest Captains\n";
        $entries = array_slice($data['space_tycoon'] ?? [], 0, 5);
        if (empty($entries)) {
            $message .= "   <i>No victories yet!</i>\n\n";
        } else {
            foreach ($entries as $index => $entry) {
                $rank = $index + 1;
                $name = $entry['username'] ?? 'Unknown';
                $eth = FormatHelpers::formatEth($entry['eth'] ?? 0);
                $days = $entry['days'] ?? '?';
                $date = $formatDate($entry['timestamp'] ?? time());
                $isPlayer = $userId && ($entry['user_id'] ?? 0) === $userId;

                $marker = $isPlayer ? '👉 ' : '   ';
                $bold = $isPlayer ? '<b>' : '';
                $endB = $isPlayer ? '</b>' : '';

                $message .= "{$marker}{$rank}. {$bold}{$name}{$endB} — 💰 {$eth} ETH\n";
                $message .= "   {$days} days — {$date}\n";
            }
            $message .= "\n";
        }

        // 4. 📦 Resource Master
        $message .= "📦 <b>RESOURCE MASTER</b> — Best Stockpiles\n";
        $entries = array_slice($data['resource_master'] ?? [], 0, 5);
        if (empty($entries)) {
            $message .= "   <i>No victories yet!</i>\n\n";
        } else {
            foreach ($entries as $index => $entry) {
                $rank = $index + 1;
                $name = $entry['username'] ?? 'Unknown';
                $score = $formatNumber($entry['resource_score'] ?? 0);
                $fuel = $formatNumber($entry['fuel'] ?? 0);
                $food = $formatNumber($entry['food'] ?? 0);
                $date = $formatDate($entry['timestamp'] ?? time());
                $isPlayer = $userId && ($entry['user_id'] ?? 0) === $userId;

                $marker = $isPlayer ? '👉 ' : '   ';
                $bold = $isPlayer ? '<b>' : '';
                $endB = $isPlayer ? '</b>' : '';

                $message .= "{$marker}{$rank}. {$bold}{$name}{$endB} — {$score} points\n";
                $message .= "   ⛽ {$fuel}, 🍖 {$food} — {$date}\n";
            }
            $message .= "\n";
        }

        // 5. 🎲 Event Master
        $message .= "🎲 <b>EVENT MASTER</b> — Best Event Performance\n";
        $entries = array_slice($data['event_master'] ?? [], 0, 5);
        if (empty($entries)) {
            $message .= "   <i>No victories yet!</i>\n\n";
        } else {
            foreach ($entries as $index => $entry) {
                $rank = $index + 1;
                $name = $entry['username'] ?? 'Unknown';
                $netTotal = $entry['event_net_total'] ?? 0;
                $netFuel = $entry['event_net_fuel'] ?? 0;
                $netFood = $entry['event_net_food'] ?? 0;
                $netEth = $entry['event_net_eth'] ?? 0;
                $netHull = $entry['event_net_hull'] ?? 0;
                $date = $formatDate($entry['timestamp'] ?? time());
                $isPlayer = $userId && ($entry['user_id'] ?? 0) === $userId;

                $marker = $isPlayer ? '👉 ' : '   ';
                $bold = $isPlayer ? '<b>' : '';
                $endB = $isPlayer ? '</b>' : '';

                // Format with + or - signs
                $sign = $netTotal >= 0 ? '+' : '';
                $fuelSign = $netFuel >= 0 ? '+' : '';
                $foodSign = $netFood >= 0 ? '+' : '';
                $ethSign = $netEth >= 0 ? '+' : '';
                $hullSign = $netHull >= 0 ? '+' : '';

                $ethFormatted = FormatHelpers::formatEth(abs($netEth));

                $message .= "{$marker}{$rank}. {$bold}{$name}{$endB} — {$sign}{$netTotal} net resources\n";
                $message .= "   ⛽ {$fuelSign}{$netFuel}, 🍖 {$foodSign}{$netFood}, 💰 {$ethSign}{$ethFormatted}, 🛡️ {$hullSign}{$netHull} — {$date}\n";
            }
            $message .= "\n";
        }

        return $message;
    }

    /**
     * Format "Who's Playing" view showing all active players.
     * Players are sorted by distance (farthest first).
     *
     * @param  array  $players  Array of player data from GameEngine::getActivePlayers()
     * @param  int|null  $userId  Current user's ID (to highlight their entry)
     * @return string  Formatted message with player list
     */
    public function formatWhosPlaying(array $players, ?int $userId = null): string {
        $message = "👥 <b>WHO'S PLAYING</b>\n\n";

        if (empty($players)) {
            $message .= "<i>No active players right now!</i>\n";
            return $message;
        }

        // Helper function to format number with commas
        $formatNumber = function($num) {
            return number_format($num);
        };

        foreach ($players as $index => $player) {
            $rank = $index + 1;
            $displayName = $player['display_name'] ?? 'Unknown';
            $distance = $player['distance'] ?? 0;
            $percentage = round($player['percentage'] ?? 0, 1);
            $days = $player['days'] ?? 1;
            $chad = $player['chad'] ?? 0;
            $status = $player['status'] ?? 'unknown';
            $lastActivityDate = $player['last_activity_date'] ?? '?';
            $isCurrentUser = $userId && ($player['user_id'] ?? 0) === $userId;

            $marker = $isCurrentUser ? '👉 ' : '   ';
            $bold = $isCurrentUser ? '<b>' : '';
            $endB = $isCurrentUser ? '</b>' : '';

            // Line 1: Name + distance/status
            if ($status === 'finished') {
                $message .= "{$marker}{$rank}. {$bold}{$displayName}{$endB} — 🏆 Victory!\n";
            } else {
                $distanceFormatted = $formatNumber($distance);
                $message .= "{$marker}{$rank}. {$bold}{$displayName}{$endB} — {$distanceFormatted} km ({$percentage}%)\n";
            }

            // Line 2: Days + chad + last activity
            $message .= "   ⏱️ Day {$days} — 👥 {$chad} chad — Last: {$lastActivityDate}\n";
        }

        return $message;
    }

    /**
     * Parse a chad reaction template string.
     * Helper for formatEnhancedEvent.
     *
     * @param  string  $template
     * @param  array   $session
     * @return array  ['name' => string, 'role' => string, 'says' => string]
     */
    private function parseChadReactionTemplate(string $template, array $session): array {
        $resolved = $this->gameEngine->replaceChadTemplates($template, $session);
        // Match "Name: \"says\"" or "Name: says"
        if (preg_match('/^([^:]+):\s*"([^"]*)"\s*$/', $resolved, $m)) {
            return ['name' => trim($m[1]), 'role' => '', 'says' => $m[2]];
        }
        if (preg_match('/^([^:]+):\s*(.+)$/', $resolved, $m)) {
            return ['name' => trim($m[1]), 'role' => '', 'says' => trim($m[2])];
        }
        return ['name' => 'Chad', 'role' => '', 'says' => $resolved];
    }

    /**
     * Get emoji for chad role.
     * Helper for formatEnhancedEvent.
     *
     * @param  string  $role
     * @return string
     */
    private function getChadEmojiByRole(string $role): string {
        $map = [
            'Captain'    => '👨‍✈️',
            'Engineer'   => '👨‍🔧',
            'Quant'      => '📊',
            'Medic'      => '👨‍⚕️',
            'HODLer' => '💎',
        ];
        return $map[$role] ?? '👤';
    }

}

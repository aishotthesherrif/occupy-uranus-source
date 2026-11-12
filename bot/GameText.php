<?php
/**
 * GameText
 *
 * Telegram-bot text for game screens: journey, status, waypoint, station and map.
 * Formatting only. All numbers and game content come from GameEngine / GameService; this
 * class never changes game state.
 *
 * Lives in bot/ because the output is Telegram HTML. The mini-app writes its own text
 * from the data GameEngine returns.
 */
class GameText {
    /** Width of the inner content area of the ASCII map box (between ║ characters) */
    private const MAP_INNER_WIDTH = 28;

    private GameEngine $engine;

    public function __construct(GameEngine $engine) {
        $this->engine = $engine;
    }

    /**
     * Format waypoint view message (stop or skip decision).
     *
     * @param  array  $session  Current session state
     * @param  array  $waypoint  Waypoint data (name, distance, symbol, id, etc.)
     * @return string
     */
    public function formatWaypointView(array $session, array $waypoint): string {
        $waypointName = $waypoint['name'] ?? 'Unknown Waypoint';
        $day = $session['progress']['day'];

        $speedMultiplier = $session['progress']['speed_multiplier'] ?? 1.0;
        $hasBoost = $speedMultiplier > 1.0;

        $message  = "📍 <b>Waypoint Reached!</b>\n\n";
        $message .= "You've reached <b>{$waypointName}</b> on day {$day}.\n\n";
        $message .= "📦 Current Resources:\n";
        $message .= "⛽ Fuel: <b>{$session['resources']['fuel']}</b> units\n";
        $message .= "🍖 Food: <b>{$session['resources']['food']}</b> units\n";
        $message .= "🛡️ Hull: <b>{$session['resources']['hull']}%</b>\n";
        $message .= "👥 Chad: <b>{$session['resources']['chad']}/5</b>\n";
        $message .= "😊 Vibes: <b>{$session['resources']['vibes']}%</b>\n";
        $message .= "💰 ETH: <b>" . FormatHelpers::formatEth($session['resources']['eth']) . "</b>\n\n";

        if ($hasBoost) {
            $message .= "⚡ <b>Current Status:</b> Gravitational Assist Active (2× speed)\n\n";
        }

        $message .= "💡 <b>Your Options:</b>\n";
        $message .= "🛒 <b>Stop:</b> Shop for supplies and rest";
        if ($hasBoost) {
            $message .= " (loses speed boost)";
        }
        $message .= "\n";
        $message .= "➡️ <b>Skip:</b> ";
        if ($hasBoost) {
            $message .= "Maintain speed boost";
        } else {
            $message .= "Gain 2× speed boost until next stop";
        }
        $message .= "\n\n<i>Choose wisely — stopping resets your speed to normal.</i>";

        return $message;
    }

    /**
     * Format station view message with local character story.
     *
     * @param  array  $session  Current session state
     * @param  array  $station  Waypoint with the chosen description / local_story (see GameEngine::selectStationContent)
     * @return string           Formatted message
     */
    public function formatStationView(array $session, array $station): string {
        // The story and description were chosen when the ship docked (GameEngine::selectStationContent)
        $stationName = $station['name'] ?? 'Unknown Station';
        $symbol = $station['symbol'] ?? '📍';
        $day = $session['progress']['day'];

        // Header
        $message  = "{$symbol} <b>{$stationName}</b>\n";
        $message .= "Day {$day} of your journey\n\n";

        // Local character story (if available)
        if (!empty($station['local_character']) && !empty($station['local_story'])) {
            $character = $station['local_character'];
            $job = $station['local_job'] ?? 'Local';
            $story = $station['local_story'];

            $message .= "<i>{$character} — {$job}</i>\n\n";
            $message .= "\"{$story}\"\n\n";
            $message .= str_repeat("━", 31) . "\n\n";
        } else {
            // Fallback if no story
            $description = $station['description'] ?? '';
            if ($description) {
                $message .= "<i>{$description}</i>\n\n";
                $message .= str_repeat("━", 31) . "\n\n";
            }
        }

        // Chad status
        $chad = $session['resources']['chad'];
        $vibes = $session['resources']['vibes'];
        $vibesStatus = $this->getVibesStatus($vibes);

        // Format: "Chad is in high spirits" -> "Your Chad crew of X is in high spirits"
        $message .= str_replace("Chad is ", "Your Chad crew of {$chad} is ", $vibesStatus);
        if ($vibes >= 50) {
            $message .= " ({$vibes}% vibes)";
        }
        $message .= ".\n\n";

        // Ship status
        $message .= "<b>Ship Status:</b>\n";

        // Fuel
        $fuel = $session['resources']['fuel'];
        $fuelPerDay = FUEL_PER_DAY;
        $fuelDays = $this->engine->calculateDaysRemaining($fuel, $fuelPerDay);
        $message .= FormatHelpers::formatResourceWithDays("⛽", "Fuel", $fuel, $fuelDays) . "\n";

        // Food
        $food = $session['resources']['food'];
        $foodPerDay = $chad * FOOD_PER_CREW;
        $foodDays = $this->engine->calculateDaysRemaining($food, $foodPerDay);
        $message .= FormatHelpers::formatResourceWithDays("🍖", "Food", $food, $foodDays) . "\n";

        // Hull
        $hull = $session['resources']['hull'];
        $hullStatus = $this->getHullStatus($hull);
        $message .= "🛡️ Hull: {$hull}% (" . lcfirst($hullStatus) . ")\n";

        // ETH
        $eth = $session['resources']['eth'];
        $message .= "💰 ETH: " . FormatHelpers::formatEth($eth) . "\n\n";

        // Distance remaining
        $distanceRemaining = TOTAL_DISTANCE - $session['progress']['distance'];
        $distanceRemainingM = round($distanceRemaining / 1_000_000);
        $message .= "Distance to Uranus: {$distanceRemainingM}M km";

        return $message;
    }

    /**
     * Format the mission status message (overview, ship, chad).
     *
     * @param  array       $session
     * @param  int|null    $userId
     * @param  string|null $username
     * @param  bool        $includeChadDetails  Include Individual Chad Members (names, roles, status, activity). Journey View omits; Status Detail View includes.
     * @return string
     */
    /**
     * Format the Journey view (concise, action-focused).
     * Shows current day status with progress bar, velocity, resources, and context-aware prompt.
     *
     * @param  array       $session
     * @param  string|null $flashMessage  Optional flash message (event outcomes, action results)
     * @return string
     */
    public function formatJourney(array $session, ?string $flashMessage = null): string {
        $day = $session['progress']['day'];
        $distance = $session['progress']['distance'];
        $speedMultiplier = $session['progress']['speed_multiplier'] ?? 1.0;
        $baseSpeed = $session['progress']['speed'];
        $effectiveSpeed = $baseSpeed * $speedMultiplier;
        $totalTraveled = $session['stats']['total_distance_traveled'] ?? $distance;

        // Header with velocity
        $velocityDisplay = FormatHelpers::formatVelocityDisplay($baseSpeed, $speedMultiplier);
        $message = "🚀 <b>DAY {$day} — {$velocityDisplay}</b>\n\n";

        // Flash message (event outcomes, etc.) or "No Events Today"
        if ($flashMessage !== null && $flashMessage !== '') {
            $message .= $flashMessage . "\n";
        } else {
            $message .= "✅ No Events Today\n";
        }

        // Chad quote (only for post-event reactions)
        $context = $this->engine->getQuoteContext($session);
        if ($context === 'post_event_success' || $context === 'post_event_failure') {
            $activeChad = $session['resources']['chad'];
            if ($activeChad > 0) {
                $chadIndex = ($day - 1) % $activeChad; // Rotate through active chad
                $roster = $this->engine->getChadRoster($session);
                $member = $roster[$chadIndex] ?? null;

                if ($member !== null) {
                    $name = $member['name'] ?? ($session['chad_names'][$chadIndex] ?? "Chad Member");
                    $role = $member['role'] ?? $this->engine->getChadTitle($chadIndex);
                    $quote = $this->engine->getChadQuote($member, $session, $chadIndex);

                    $message .= "💬 \"{$quote}\" — {$name} ({$role})\n\n";
                }
            }
        }

        // Waypoint banner (if at waypoint)
        $atWaypoint = $this->engine->getAtWaypoint($session);
        if ($atWaypoint !== null) {
            $waypointName = strtoupper($atWaypoint['name'] ?? 'WAYPOINT');
            $message .= "═══════════════════════════\n";
            $message .= "🛰️ <b>ARRIVED: {$waypointName}</b>\n";
            $message .= "═══════════════════════════\n\n";
        }

        // Compact progress bar with remaining distance
        $remaining = TOTAL_DISTANCE - $distance;
        $progressPercent = $this->engine->getProgressPercent($session);
        $progressBar = FormatHelpers::formatProgressBar($progressPercent);
        $message .= FormatHelpers::formatDistance($remaining) . " to Uranus ({$progressPercent}%)\n";
        $message .= "[{$progressBar}]\n\n";

        // Distance traveled
        $message .= "📏 Traveled: " . FormatHelpers::formatDistance($totalTraveled) . "\n";

        // Distance from Earth (current position)
        $message .= "🌍 Dist from Earth: " . FormatHelpers::formatDistance($distance) . "\n";

        // Next waypoint or current location
        if ($atWaypoint !== null) {
            $message .= "🛰️ Current Location: " . ($atWaypoint['name'] ?? 'Waypoint') . "\n\n";
        } else {
            $nextWaypoint = $this->engine->getNextWaypoint($session);
            if ($nextWaypoint !== null) {
                $distanceToNext = $nextWaypoint['distance'] - $distance;
                $daysToNext = $effectiveSpeed > 0 ? (int) ceil($distanceToNext / $effectiveSpeed) : 999;
                $message .= "🛰️ Dist to {$nextWaypoint['name']} - " . FormatHelpers::formatDistance($distanceToNext) . " (~{$daysToNext}d)\n\n";
            } else {
                $message .= "\n";
            }
        }

        // Critical resource warning
        $criticalWarnings = $this->getCriticalResourceWarnings($session);
        if (!empty($criticalWarnings)) {
            $message .= "⚠️ <b>WARNING:</b> " . implode(', ', $criticalWarnings) . "\n";
        }

        // Resources with days remaining (compact format)
        $message .= FormatHelpers::formatResources($session, 'compact') . "\n\n";

        // Context-aware footer
        if ($atWaypoint !== null) {
            $message .= "Rest here to restore vibes?";
        } else {
            $message .= "Ready to continue your journey?";
        }

        return $message;
    }

    /** Warning sentences for the journey screen, from the engine's warning codes. */
    private function getCriticalResourceWarnings(array $session): array {
        $lines = [];
        foreach ($this->engine->getCriticalWarnings($session) as $w) {
            $lines[] = match ($w['code']) {
                'fuel_low'      => "Fuel critically low ({$w['days']}d)!",
                'food_low'      => "Food critically low ({$w['days']}d)!",
                'hull_critical' => "Hull integrity critical!",
                'vibes_low'     => "Chad vibes dangerously low!",
            };
        }
        return $lines;
    }

    /**
     * Format the detailed Status view (Chad Dashboard style).
     * Chad-focused with personality quotes, contextual to game state.
     *
     * @param  array  $session
     * @return string
     */
    public function formatDetailedStatus(array $session): string {
        $day = $session['progress']['day'];
        $distance = $session['progress']['distance'];
        $speedMultiplier = $session['progress']['speed_multiplier'] ?? 1.0;
        $baseSpeed = $session['progress']['speed'];
        $effectiveSpeed = $baseSpeed * $speedMultiplier;

        // Header
        $message = "📊 <b>DETAILED STATUS — Day {$day} of the Journey</b>\n\n";

        // Mission Overview Section
        $message .= "═══════════════════════════\n";
        $message .= "<b>MISSION OVERVIEW</b>\n";
        $message .= "═══════════════════════════\n";

        $progressPercent = $this->engine->getProgressPercent($session);
        $remaining = TOTAL_DISTANCE - $distance;
        $daysToUranus = $effectiveSpeed > 0 ? (int) ceil($remaining / $effectiveSpeed) : 999;

        $message .= "🎯 {$progressPercent}% to Uranus (" . FormatHelpers::formatDistance($distance) . " / " . FormatHelpers::formatDistance(TOTAL_DISTANCE) . ")\n";

        $velocityDisplay = FormatHelpers::formatVelocityDisplay($baseSpeed, $speedMultiplier);
        $message .= "🚀 Traveling at {$velocityDisplay}\n";
        $message .= "⏳ {$daysToUranus} days remaining\n";

        // Gravitational assist indicator
        if ($speedMultiplier > 1.0) {
            $message .= "\n⚡ <b>Gravitational Assist:</b> 2× speed boost active\n";
        }

        $message .= "\n";

        // Chad Status Section
        $message .= "═══════════════════════════\n";
        $message .= "<b>CREW STATUS ({$session['resources']['chad']}/" . MAX_CREW . " active)</b>\n";
        $message .= "═══════════════════════════\n";

        $vibes = $session['resources']['vibes'];
        $vibesStatus = $this->getVibesStatus($vibes);
        $message .= "😊 <b>Vibes:</b> {$vibes}% — {$vibesStatus}\n\n";

        // Individual chads with contextual quotes
        $roster = $this->engine->getChadRoster($session);
        $activeChad = $session['resources']['chad'];

        for ($i = 0; $i < MAX_CREW; $i++) {
            $member = $roster[$i] ?? null;
            $name = $member['name'] ?? ($session['chad_names'][$i] ?? "Chad Member " . ($i + 1));
            $role = $member['role'] ?? $this->engine->getChadTitle($i);
            $trait = $member['trait'] ?? 'Optimistic';
            $emoji = $this->getChadEmoji($i);

            if ($i < $activeChad) {
                // Active chad: show quote + activity
                $quote = $this->engine->getChadQuote($member, $session, $i);
                $activity = $this->engine->getRandomChadActivity($role);

                $message .= "{$emoji} <b>{$name}</b> ({$role}) — {$trait}\n";
                $message .= "   💬 \"{$quote}\"\n";
                $message .= "   📍 {$activity}\n\n";
            } else {
                // Lost chad: memorial
                $message .= "{$emoji} <b>{$name}</b> ({$role}) — {$trait}\n";
                $message .= "   ❌ <b>Lost</b>\n\n";
            }
        }

        // Ship & Supplies Section
        $message .= "═══════════════════════════\n";
        $message .= "<b>SHIP &amp; SUPPLIES</b>\n";
        $message .= "═══════════════════════════\n";

        $hull = $session['resources']['hull'];
        $fuel = $session['resources']['fuel'];
        $food = $session['resources']['food'];
        $eth = $session['resources']['eth'] ?? 0;

        // Calculate days remaining
        $chad = $session['resources']['chad'];
        $fuelPerDay = FUEL_PER_DAY;
        $foodPerDay = $chad * FOOD_PER_CREW;
        $fuelDays = $this->engine->calculateDaysRemaining($fuel, $fuelPerDay);
        $foodDays = $this->engine->calculateDaysRemaining($food, $foodPerDay);

        // Hull status
        $hullStatus = $this->getHullStatus($hull);
        $message .= "🛡️ <b>Hull:</b> {$hull}% — {$hullStatus}\n";

        // Fuel status
        $message .= "⛽ <b>Fuel:</b> {$fuel} units — {$fuelDays} days at current rate\n";

        // Food status
        $message .= "🍖 <b>Food:</b> {$food} units — {$foodDays} days for full chad\n";

        // ETH
        $message .= "💰 <b>ETH:</b> " . FormatHelpers::formatEth($eth) . "\n\n";

        // Difficulty system removed 2026-03-02

        return $message;
    }

    /** Vibes description for the band the engine reports. */
    private function getVibesStatus(int $vibes): string {
        return match ($this->engine->getVibesBand($vibes)) {
            'high'     => "Chad is in high spirits",
            'good'     => "Chad is managing well",
            'strained' => "Chad is feeling strained",
            'low'      => "Chad vibes are dangerously low",
            'mutiny'   => "Chad is on the verge of mutiny",
        };
    }

    /** Hull description for the band the engine reports. */
    private function getHullStatus(int $hull): string {
        return match ($this->engine->getHullBand($hull)) {
            'excellent'          => "Excellent condition",
            'minor_damage'       => "Minor repairs needed",
            'significant_damage' => "Significant damage",
            'critical'           => "Critical structural issues",
            'failing'            => "Ship is barely holding together",
        };
    }

    public function formatStatus(array $session, $userId = null, ?string $username = null, bool $includeChadDetails = true): string {
        $message  = "📊 <b>MISSION STATUS — Day {$session['progress']['day']}</b>\n";

        // Difficulty system removed 2026-03-02
        $message .= "📍 Status: " . ucfirst($session['status']) . "\n";

        // Quick status indicator for gravitational assist
        $speedMultiplierCheck = $session['progress']['speed_multiplier'] ?? 1.0;
        if ($speedMultiplierCheck > 1.0) {
            $message .= "\n⚡ <b>GRAVITATIONAL ASSIST ACTIVE</b> — 2× Speed Boost\n";
        }

        $message .= "\n";

        $message .= "<b>Journey Progress:</b>\n";
        $message .= "📍 Total KM Travelled: " . FormatHelpers::formatDistance($session['stats']['total_distance_traveled']) . "\n";
        $message .= "🎯 KM to Uranus: " . FormatHelpers::formatDistance(TOTAL_DISTANCE - $session['progress']['distance']) . "\n";

        // Show speed with multiplier if active
        $speedMultiplier = $session['progress']['speed_multiplier'] ?? 1.0;
        $baseSpeed = $session['progress']['speed'];
        $effectiveSpeed = $baseSpeed * $speedMultiplier;

        if ($speedMultiplier > 1.0) {
            $message .= "🚀 Speed: " . FormatHelpers::formatDistance($effectiveSpeed) . "/day ";
            $message .= "<b>(2× GRAVITATIONAL ASSIST)</b>\n\n";
        } else {
            $message .= "🚀 Speed: " . FormatHelpers::formatDistance($baseSpeed) . "/day\n\n";
        }

        $remaining    = TOTAL_DISTANCE - $session['progress']['distance'];
        $daysRemaining = $effectiveSpeed > 0
            ? (int) ceil($remaining / $effectiveSpeed)
            : '∞';
        $message .= "⏳ Estimated days to Uranus: {$daysRemaining}\n\n";

        $message .= "<b>Ship Status:</b>\n";
        $message .= "🛡️ Hull: {$session['resources']['hull']}%\n";
        $message .= "⛽ Fuel: {$session['resources']['fuel']} units\n";
        $message .= "🍖 Food: {$session['resources']['food']} units\n";

        // Show speed boost status in ship status
        if ($speedMultiplier > 1.0) {
            $message .= "🚀 Speed Boost: <b>2× ACTIVE</b>\n";
        }

        $message .= "\n";

        $message .= "<b>Chad Status:</b>\n";
        $message .= "👥 Chad: {$session['resources']['chad']}/" . MAX_CREW . "\n";
        $message .= "😊 Vibes: {$session['resources']['vibes']}%\n\n";

        if ($includeChadDetails) {
            $message .= "<b>Individual Chad Members:</b>\n";
            $roster     = $this->engine->getChadRoster($session);
            $activeChad = $session['resources']['chad'];

            for ($i = 0; $i < MAX_CREW; $i++) {
                $member = $roster[$i] ?? null;
                $name   = $member['name'] ?? ($session['chad_names'][$i] ?? "Chad Member " . ($i + 1));
                $title  = $member['role'] ?? $this->engine->getChadTitle($i);
                $trait  = $member['trait'] ?? '';
                $status = $i < $activeChad ? "✅ Active" : "❌ Lost";
                $emoji  = $this->getChadEmoji($i);

                if ($i < $activeChad) {
                    $activity = $this->engine->getRandomChadActivity($title);
                    $traitStr = $trait !== '' ? " ({$trait})" : '';
                    $message .= "{$emoji} <b>{$name}</b> — {$title}{$traitStr}: {$status}\n";
                    $message .= "   Currently: {$activity}\n\n";
                } else {
                    $message .= "{$emoji} <b>{$name}</b> — {$title}: {$status}\n\n";
                }
            }
        }

        $message .= "Use the buttons below to continue your journey.";

        return $message;
    }

    /**
     * Generate the ASCII solar system map.
     * All content lines are padded to MAP_INNER_WIDTH characters so the right
     * border ║ stays aligned regardless of content length.
     *
     * Note: Unicode emoji have inherent display-width variance across Telegram
     * clients; the waypoint symbol column will appear approximately aligned but
     * pixel-perfect alignment with emoji is not achievable via padding alone.
     *
     * @param  array  $session
     * @return string  Plain text (no HTML) — send with sendMessagePlain()
     */
    public function generateMap(array $session): string {
        $waypoints       = $this->engine->loadWaypoints();
        $currentDistance = $session['progress']['distance'];
        $totalTraveled   = $session['stats']['total_distance_traveled'] ?? $currentDistance;
        $day             = $session['progress']['day'];
        $chad            = $session['resources']['chad'];
        $fuel            = $session['resources']['fuel'];
        $food            = $session['resources']['food'];
        $speed           = $session['progress']['speed'];

        // Calculate progress
        $progressPercent = $this->engine->getProgressPercent($session);
        $positionM = round($currentDistance / 1000000);

        // Determine current location
        $transitFrom = null;
        $transitTo = null;
        $atWaypoint = $this->engine->isAtWaypoint($session);

        if (!$atWaypoint) {
            $lastPassedName = null;
            $nextName = null;

            foreach ($waypoints as $wp) {
                if ($wp['distance'] <= $currentDistance) {
                    $lastPassedName = $wp['name'];
                }
                if ($nextName === null && $wp['distance'] > $currentDistance) {
                    $nextName = $wp['name'];
                }
            }

            $transitFrom = $lastPassedName ?? 'Earth';
            $transitTo = $nextName ?? 'Uranus';
        }

        // Find next waypoint
        $nextWaypoint = null;
        foreach ($waypoints as $wp) {
            if ($wp['distance'] > $currentDistance) {
                $nextWaypoint = $wp;
                break;
            }
        }

        // Build Map - Cosmic Void Style
        $map = "";

        // Header with stars
        $map .= "  ･  ✹  ･    ･  ✹  ･\n";
        $map .= "     ･   ✹  ･    ･\n";
        $map .= " ✹      ･       ✹\n\n";
        $map .= " 🛰️ DEEP SPACE NAV 🛰️\n\n";
        $map .= "  ･  ✹  ･    ･  ✹  ･\n\n";

        // Mission Status
        $map .= "⟬ MISSION STATUS ⟭\n\n";
        $map .= "Day $day ┃ {$positionM}M ┃ {$progressPercent}%\n";

        if ($atWaypoint) {
            $wpName = $session['at_waypoint']['name'];
            $map .= "Status: At $wpName\n";
        } else {
            $map .= "Status: Deep Space\n";
        }

        $map .= "\n     *  ･  *  ･  *\n\n";

        // Solar System Tree
        $map .= "⟬ SOLAR TRAVERSE ⟭\n\n";
        $map .= "☉ Sol\n";
        $map .= " ┃\n";

        // Iterate through all waypoints
        $waypointCount = count($waypoints);
        $currentIndex = 0;
        $playerShown = false;

        foreach ($waypoints as $index => $wp) {
            $wpDist = $wp['distance'];
            $wpDistM = round($wpDist / 1000000);
            $symbol = $wp['symbol'] ?? '•';
            $isStation = in_array($wp['id'] ?? '', ['mars', 'ceres', 'jupiter', 'saturn', 'deep_space']);
            $isDestination = ($wp['id'] ?? '') === 'uranus';
            $isLast = ($index === $waypointCount - 1);

            // Determine waypoint status
            if ($wpDist < $currentDistance || ($wpDist == $currentDistance && !$atWaypoint)) {
                // Passed waypoint
                $status = '✓';
                $prefix = ' ├─';

                // Shorten names for display
                $displayName = $this->shortenWaypointName($wp['name']);

                $map .= "{$prefix}{$status} {$symbol} {$displayName} ";
                $map .= str_repeat('·', max(0, 9 - mb_strlen($displayName))) . " {$wpDistM}M\n";

                if ($isStation) {
                    $map .= " ┃    " . $this->getWaypointLabel($wp) . "\n";
                } elseif ($wp['name'] === 'Earth Orbit') {
                    $map .= " ┃    Start\n";
                }

                $map .= " ┃\n";

            } elseif ($wpDist == $currentDistance && $atWaypoint) {
                // Current waypoint (docked)
                $displayName = $this->shortenWaypointName($wp['name']);

                $map .= " ├─→ 🚀 YOU ";
                $map .= str_repeat('·', max(0, 9 - mb_strlen($displayName))) . " {$wpDistM}M\n";
                $map .= " ┃   ≫ Docked at {$displayName}\n";

                if ($isStation) {
                    $map .= " ┃   " . $this->getWaypointLabel($wp) . "\n";
                }

                $map .= " ┃\n";
                $currentIndex = $index;
                $playerShown = true;

            } else {
                // Not yet reached - check if we need to show player position first
                if ($currentDistance < $wpDist && !$playerShown && !$atWaypoint) {
                    // Show player in transit
                    $distToNext = $wpDist - $currentDistance;
                    $distToNextM = round($distToNext / 1000000);

                    $map .= " ┃   🌠 {$distToNextM}M\n";
                    $map .= " ┃      ⋮\n";
                    $map .= " ├─→ 🚀 YOU ";
                    $map .= str_repeat('·', 4) . " {$positionM}M\n";
                    $map .= " ┃   ≫ {$transitFrom} → {$transitTo}\n";
                    $map .= " ┃      ⋮\n";
                    $map .= " ┃   🌠 {$distToNextM}M\n";
                    $map .= " ┃\n";

                    $currentIndex = $index;
                    $playerShown = true;
                }

                // Upcoming waypoint
                $connector = $isLast ? ' └─' : ' ├─';
                $displayName = $this->shortenWaypointName($wp['name']);

                $map .= "{$connector}○ {$symbol} {$displayName} ";
                $map .= str_repeat('·', max(0, 9 - mb_strlen($displayName))) . " {$wpDistM}M\n";

                // Add details for next waypoint
                if ($nextWaypoint && $wp['name'] === $nextWaypoint['name']) {
                    $distToNext = $nextWaypoint['distance'] - $currentDistance;
                    $daysToNext = ceil($distToNext / $speed);
                    $fuelNeeded = $daysToNext * FUEL_PER_DAY;
                    $foodNeeded = $daysToNext * $chad * FOOD_PER_CREW;

                    if ($isStation) {
                        $map .= " ┃    " . $this->getWaypointLabel($wp) . "\n";
                    }
                    $map .= " ┃    ⚠️ ~{$daysToNext} days\n";
                    $map .= " ┃    {$fuelNeeded}⛽ {$foodNeeded}🍖\n";
                } else {
                    if ($isStation) {
                        $map .= " ┃    " . $this->getWaypointLabel($wp) . "\n";
                    } elseif ($isDestination) {
                        $map .= "       🎯 End\n";
                    }
                }

                if (!$isLast) {
                    $map .= " ┃\n";
                }
            }
        }

        // Footer stars
        $map .= "\n     *  ･  *  ･  *\n\n";
        $map .= "  ･  ✹  ･    ･  ✹  ･\n";

        return $map;
    }

    /**
     * Shorten waypoint names for compact map display.
     *
     * @param  string  $name
     * @return string
     */
    private function shortenWaypointName(string $name): string {
        $shortNames = [
            'Earth Orbit' => 'Earth',
            'Mars Base' => 'Mars',
            'Ceres Asteroid Station' => 'Ceres',
            'Jupiter Gas Mine' => 'Jupiter',
            'Saturn Outpost' => 'Saturn',
            'Deep Space 69' => 'Deep',
            'Uranus Arrival' => 'Uranus',
        ];

        return $shortNames[$name] ?? $name;
    }

    /**
     * Get label text for waypoint in map.
     *
     * @param  array  $waypoint
     * @return string
     */
    private function getWaypointLabel(array $waypoint): string {
        $id = $waypoint['id'] ?? '';

        switch ($id) {
            case 'mars':
                return '💰 Hub';
            case 'ceres':
                return '💰 Outpost';
            case 'jupiter':
                return '💰 Refuel';
            case 'saturn':
                return '💰 Station';
            case 'deep_space':
                return '💰 Void';
            default:
                return '';
        }
    }

    private function getChadEmoji(int $position): string {
        return ['👨‍✈️', '👨‍🔧', '📊', '👨‍⚕️', '💎'][$position] ?? '👤';
    }

    // =========================================================================
    // RESULT TEXT: turns the data GameEngine returns into Telegram text
    // =========================================================================

    /** Sentence shown on the game-over screen for a reason code from GameEngine::checkGameOver(). */
    public function gameOverText(string $code): string {
        return match ($code) {
            'out_of_fuel'   => "Your ship ran out of fuel and drifted into the void...",
            'starved'       => "Your Chad crew starved in the cold vacuum of space...",
            'crew_lost'     => "All chads have perished...",
            'hull_breached' => "Your ship's hull breached catastrophically...",
            default         => 'Your journey has ended.',
        };
    }

    /** Message for an action GameEngine refused. $action is the engine action that was attempted. */
    public function rejectionText(string $reason, string $action = ''): string {
        return match ($reason) {
            'not_launched'    => '🛒 You need to finish shopping first! Use the buttons below to continue.',
            'event_pending'   => '⚠️ You must respond to the current event first!',
            'event_not_found' => '❌ Event not found',
            'choice_not_found' => '❌ Choice not found',
            'not_at_station'  => '❌ You can only rest at stations!',
            'not_in_space'    => $action === 'hunt'
                ? '❌ You can only hunt during your journey!'
                : '❌ You can only return during your journey!',
            'at_waypoint'     => $action === 'hunt'
                ? "❌ You're at a station. Use Rest or Shop instead."
                : "❌ You're at a station. Use Rest or Continue.",
            default           => '❌ That is not possible right now.',
        };
    }

    /** Result screen text after the player picks an event choice (data from GameEngine::processEventChoice). */
    public function eventResultText(array $r): string {
        $message  = "🎲 <b>Result:</b>\n\n";
        $message .= "🎯 <b>YOUR CHOICE:</b> " . $r['choice_text'] . "\n\n";
        $message .= $r['outcome_text'] . "\n";

        if (!empty($r['chad_reaction'])) {
            $message .= "\n💬 <b>{$r['chad_reaction']['name']}</b>: \"{$r['chad_reaction']['says']}\"\n";
        }

        if (!empty($r['deaths'])) {
            $lines = array_map(fn($name) => "💀 <b>{$name}</b> has perished.", $r['deaths']);
            $message .= "\n" . implode("\n", $lines);
        }

        return $message;
    }

    /**
     * Flash after a day of travel. Deliberately minimal, as it was before the refactor.
     * The 'day_advanced' data also carries distance_traveled, fuel_consumed, food_consumed,
     * vibes_state, deserter, slingshot and milestone if the bot should show more.
     */
    public function daySummaryText(): string {
        return "📅 Day complete!\n";
    }

    /** Flash after resting at a station (data of the 'rested' outcome). */
    public function restText(array $r): string {
        $flash  = "😴 The chad rests for a day.\n";
        $flash .= "🛡️ Hull repaired: +{$r['hull_repaired']}%\n";
        $flash .= "😊 Vibes restored: +{$r['vibes_gained']}%";
        return $flash;
    }

    /** Flash after a successful hunt that found nothing. */
    public function huntNothingText(): string {
        return "🦌 <b>Hunt Resources</b>\n\nYou spend the day scavenging but find nothing of use.";
    }

    /** Flash after returning to the last waypoint (data from GameEngine::returnToLastWaypoint). */
    public function returnText(array $r): string {
        $name = $r['waypoint']['name'];
        $message  = "↩️ <b>Return to Last Waypoint</b>\n\n";
        $message .= "You turned back to {$name}. {$r['days']} days passed.\n\n";
        $message .= "📍 Now at: {$name}\n";
        $message .= "⛽ Fuel consumed: {$r['fuel_consumed']} units\n";
        $message .= "🍖 Food consumed: {$r['food_consumed']} units";
        return $message;
    }

    /** Flash after launching (data of the 'launched' outcome, plus the session for the supply numbers). */
    public function launchText(array $r, array $session): string {
        if ($r['initial']) {
            if ($r['low_supplies']) {
                $min = $r['minimums'];
                $warn  = "⚠️ <b>LOW SUPPLIES WARNING!</b>\n\n";
                $warn .= "Your supplies are critically low:\n";
                $warn .= "⛽ Fuel: {$session['resources']['fuel']} (min {$min['fuel']})\n";
                $warn .= "🍖 Food: {$session['resources']['food']} (min {$min['food']})\n";
                $warn .= "🛡️ Hull: {$session['resources']['hull']}% (min {$min['hull']}%)\n\n";
                $warn .= "This will be extremely difficult!\n\n";
                $warn .= "🚀 <b>LAUNCHING!</b> Your journey begins!";
                return $warn;
            }
            return "🚀 <b>Ship Launched!</b>\n\nYour journey to Uranus begins!";
        }

        $message = "🚀 <b>Departing Station</b>\n\nYour journey continues!";
        if ($r['had_boost']) {
            $message .= "\n\n⚠️ <i>Gravitational assist lost (speed returned to normal)</i>";
        }
        return $message;
    }
}

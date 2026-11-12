<?php
/**
 * FormatHelpers - Utility class for consistent UI formatting across views.
 *
 * Centralizes all display formatting logic to ensure consistency and eliminate duplication.
 * All methods are static as they are pure formatting functions with no state.
 *
 * @package OccupyUranus
 * @since 2026-03-03
 */

class FormatHelpers {

    /**
     * Format ETH amount with 3 decimal places.
     *
     * @param  float $amount ETH amount
     * @return string Formatted string (e.g., "15.000", "0.500")
     */
    public static function formatEth(float $amount): string {
        return number_format($amount, 3, '.', '');
    }

    /**
     * Format distance in human-readable format.
     *
     * @param  int $km Distance in kilometers
     * @return string Formatted string (e.g., "2.80B km", "150.0M km", "5,000 km")
     */
    public static function formatDistance(int $km): string {
        if ($km >= 1_000_000_000) {
            return number_format($km / 1_000_000_000, 2) . "B km";
        }
        if ($km >= 1_000_000) {
            return number_format($km / 1_000_000, 1) . "M km";
        }
        return number_format($km) . " km";
    }

    /**
     * Format velocity display with optional boost indicator.
     *
     * @param  int   $baseSpeed       Base speed in km/day
     * @param  float $speedMultiplier Speed multiplier (e.g., 2.0 for boost)
     * @return string Formatted velocity (e.g., "20.0M km/d" or "⚡ 40.0M km/d")
     */
    public static function formatVelocityDisplay(int $baseSpeed, float $speedMultiplier): string {
        $effectiveSpeed = (int) ($baseSpeed * $speedMultiplier);
        $velocityStr = self::formatDistance($effectiveSpeed) . " km/d";

        if ($speedMultiplier > 1.0) {
            return "⚡ {$velocityStr}";
        }
        return $velocityStr;
    }

    /**
     * Format resource display with days remaining.
     *
     * @param  string $emoji  Resource emoji (e.g., "⛽", "🍖")
     * @param  string $label  Resource name (e.g., "Fuel", "Food")
     * @param  int    $amount Current amount
     * @param  int    $days   Days remaining
     * @return string Formatted string (e.g., "⛽ Fuel: 100 units (20d)")
     */
    public static function formatResourceWithDays(string $emoji, string $label, int $amount, int $days): string {
        return "{$emoji} {$label}: {$amount} units ({$days}d)";
    }

    /**
     * Format progress bar for journey display.
     *
     * @param  float $progressPercent Progress percentage (0-100)
     * @param  int   $width          Number of blocks in the bar (default 16)
     * @return string Formatted progress bar (e.g., "████████░░░░░░░░")
     */
    public static function formatProgressBar(float $progressPercent, int $width = 16): string {
        $filledBlocks = (int) round(($progressPercent / 100) * $width);
        $emptyBlocks = $width - $filledBlocks;
        return str_repeat('█', $filledBlocks) . str_repeat('░', $emptyBlocks);
    }

    /**
     * Format view header with optional day number.
     *
     * @param  string   $viewName View name/title (e.g., "Journey to Uranus", "DEEP SPACE NAV")
     * @param  int|null $day      Optional day number
     * @param  string   $emoji    Optional emoji prefix (default "🚀")
     * @return string Formatted header (e.g., "🚀 DAY 42 — Journey to Uranus")
     */
    public static function formatViewHeader(string $viewName, ?int $day = null, string $emoji = '🚀'): string {
        if ($day !== null) {
            return "{$emoji} DAY {$day} — {$viewName}";
        }
        return "{$emoji} {$viewName}";
    }

    /**
     * Format resources summary with standard display options.
     *
     * Three format levels:
     * - 'full': 2 lines, all 6 resources, no days remaining
     * - 'compact': 2 lines, all 6 resources, with days remaining for fuel/food
     * - 'minimal': 1 line, emoji + value only
     *
     * @param  array  $session Session state containing resources
     * @param  string $format  Format style: 'full', 'compact', or 'minimal' (default 'full')
     * @param  int    $chad    Chad count for food calculation (default from session)
     * @return string Formatted resource display
     */
    public static function formatResources(array $session, string $format = 'full', ?int $chad = null): string {
        $fuel = $session['resources']['fuel'];
        $food = $session['resources']['food'];
        $hull = $session['resources']['hull'];
        $chadCount = $chad ?? $session['resources']['chad'];
        $vibes = $session['resources']['vibes'];
        $eth = $session['resources']['eth'];

        switch ($format) {
            case 'compact':
                // Calculate days remaining
                $fuelPerDay = defined('FUEL_PER_DAY') ? FUEL_PER_DAY : 5;
                $foodPerDay = $chadCount * (defined('FOOD_PER_CREW') ? FOOD_PER_CREW : 3);
                $fuelDays = $fuelPerDay > 0 ? (int) floor($fuel / $fuelPerDay) : 999;
                $foodDays = $foodPerDay > 0 ? (int) floor($food / $foodPerDay) : 999;

                $msg  = self::formatResourceWithDays("⛽", "Fuel", $fuel, $fuelDays) . "\n";
                $msg .= self::formatResourceWithDays("🍖", "Food", $food, $foodDays) . "\n";
                $msg .= "🛡️ Hull: {$hull}%  |  💰 ETH: " . self::formatEth($eth) . "\n";
                $msg .= "👥 Chad: {$chadCount}/" . (defined('MAX_CREW') ? MAX_CREW : 5) . "  |  😊 Vibes: {$vibes}%";
                return $msg;

            case 'minimal':
                $msg  = "⛽{$fuel} 🍖{$food} 🛡️{$hull}% 👥{$chadCount} 😊{$vibes}% 💰" . self::formatEth($eth);
                return $msg;

            case 'full':
            default:
                $msg  = "📦 <b>Resources:</b>\n";
                $msg .= "⛽ Fuel: {$fuel} | ";
                $msg .= "🍖 Food: {$food} | ";
                $msg .= "🛡️ Hull: {$hull}%\n";
                $msg .= "👥 Chad: {$chadCount}/" . (defined('MAX_CREW') ? MAX_CREW : 5) . " | ";
                $msg .= "😊 Vibes: {$vibes}% | ";
                $msg .= "💰 ETH: " . self::formatEth($eth);
                return $msg;
        }
    }
}

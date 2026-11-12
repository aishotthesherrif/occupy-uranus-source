/**
 * Data Formatting Utilities (display only: no game rules live here)
 */

export class Formatters {
    /**
     * Format distance in km to human-readable string
     */
    static formatDistance(km) {
        if (km >= 1_000_000_000) {
            return `${(km / 1_000_000_000).toFixed(2)}B km`;
        } else if (km >= 1_000_000) {
            return `${(km / 1_000_000).toFixed(1)}M km`;
        } else if (km >= 1_000) {
            return `${(km / 1_000).toFixed(1)}K km`;
        } else {
            return `${Math.round(km)} km`;
        }
    }

    /**
     * Format ETH (the game stores whole ETH with fractions, shown to 3 places like the bot)
     */
    static formatEth(eth) {
        return Number(eth).toFixed(3);
    }

    /**
     * Format large numbers with commas
     */
    static formatNumber(num) {
        return Math.round(num).toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    /**
     * Format percentage
     */
    static formatPercent(value, max) {
        return Math.round((value / max) * 100);
    }

    /**
     * Format resource with days remaining (days come from the server)
     */
    static formatResourceWithDays(amount, days) {
        if (days === null || days === undefined || days >= 999) {
            return `${this.formatNumber(amount)} units`;
        }
        return `${this.formatNumber(amount)} units (${days}d)`;
    }

    /**
     * Get resource status color based on days remaining
     */
    static getResourceStatusColor(days) {
        if (days === null || days === undefined || days >= 999) return 'var(--teal)';
        if (days < 5) return 'var(--red)';
        if (days < 10) return 'var(--orange)';
        return 'var(--green)';
    }

    /**
     * Format velocity with multiplier
     */
    static formatVelocity(speed, multiplier = 1.0) {
        const speedM = speed / 1_000_000;
        const effectiveSpeed = speedM * multiplier;
        const multiplierText = multiplier > 1.0 ? ` ⚡${multiplier.toFixed(1)}×` : '';
        return `${effectiveSpeed.toFixed(1)}M km/d${multiplierText}`;
    }

    /**
     * Get emoji for resource warning level
     */
    static getWarningEmoji(days) {
        if (days === null || days === undefined) return '';
        if (days < 5) return '🚨';
        if (days < 10) return '⚠️';
        return '';
    }
}

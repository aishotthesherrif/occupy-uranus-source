/**
 * Messages: every sentence the mini-app shows about what happened.
 *
 * The server sends outcome codes and plain data (see game/GameService.php); this file turns
 * them into words. The Telegram bot has its own text in bot/GameText.php.
 */

import { Formatters } from './formatters.js';

export const RESOURCE_LABELS = {
    fuel:  { icon: '⛽', name: 'Fuel' },
    food:  { icon: '🍖', name: 'Food' },
    hull:  { icon: '🛡️', name: 'Hull' },
    vibes: { icon: '😊', name: 'Vibes' },
    chad:  { icon: '👥', name: 'Crew' },
    eth:   { icon: '💰', name: 'ETH' },
};

export const SHOP_LABELS = {
    fuel:   { icon: '⛽', name: 'Fuel cells',   gives: (q) => `+${q} fuel` },
    food:   { icon: '🍖', name: 'Food rations', gives: (q) => `+${q} food` },
    repair: { icon: '🛠️', name: 'Hull repair',  gives: (q) => `+${q}% hull` },
};

const GAME_OVER = {
    out_of_fuel:   'Your ship ran out of fuel and drifted into the void...',
    starved:       'Your Chad crew starved in the cold vacuum of space...',
    crew_lost:     'All chads have perished...',
    hull_breached: "Your ship's hull breached catastrophically...",
};

const REJECTIONS = {
    no_session:         'No active journey. Start a new one!',
    session_exists:     'You already have a journey in progress.',
    not_launched:       'Finish shopping and launch first.',
    not_at_station:     'You can only do that at a station.',
    not_in_space:       'You can only do that during your journey.',
    at_waypoint:        "You're at a station. Rest or continue instead.",
    no_pending_arrival: 'There is no waypoint to decide on right now.',
    event_not_found:    'That event no longer exists.',
    choice_not_found:   'That choice is not available.',
    invalid_item:       'That item is not for sale.',
    invalid_params:     'That did not work. Please try again.',
    unknown_action:     'That is not possible.',
};

const VIBES_BANDS = {
    high: 'In high spirits', good: 'Managing well', strained: 'Feeling strained',
    low: 'Dangerously low', mutiny: 'On the verge of mutiny',
};

const HULL_BANDS = {
    excellent: 'Excellent condition', minor_damage: 'Minor repairs needed', significant_damage: 'Significant damage',
    critical: 'Critical structural issues', failing: 'Barely holding together',
};

export const Messages = {
    // Sign-in and start page (web players sign in with a captain name; see web-player.js)
    captain: (name) => `👨‍🚀 Captain ${name}`,
    nameRules: (min, max) => `Pick a name of ${min}–${max} letters or numbers (spaces and . _ ' - are fine too).`,
    switchCaptain: 'Switch captain',
    switchCaptainHint: 'Your ship stays saved under this name',
    close: 'Close',
    telegramSameGame: 'Start one here. It is the same game as in the bot: you can switch at any time.',
    webSameGame: 'Start one here. Type the same captain name on any device to pick up where you left off.',

    gameOver(code) {
        return GAME_OVER[code] || 'Your journey has ended.';
    },

    rejection(reason, data = {}) {
        if (reason === 'insufficient_eth') {
            return `Not enough ETH! Need ${Formatters.formatEth(data.cost)}, have ${Formatters.formatEth(data.eth_available)}.`;
        }
        return REJECTIONS[reason] || 'That is not possible right now.';
    },

    vibesBand: (band) => VIBES_BANDS[band] || band,
    hullBand: (band) => HULL_BANDS[band] || band,

    warning(w) {
        switch (w.code) {
            case 'fuel_low':      return `Fuel critically low (${w.days}d)`;
            case 'food_low':      return `Food critically low (${w.days}d)`;
            case 'hull_critical': return 'Hull integrity critical';
            case 'vibes_low':     return 'Crew vibes dangerously low';
            default:              return w.code;
        }
    },

    /** Lines shown after a day of travel (data of the 'day_advanced' outcome). */
    day(data) {
        const lines = [];
        const parts = [];
        if (data.distance_traveled) parts.push(`🚀 ${Formatters.formatDistance(data.distance_traveled)}`);
        if (data.fuel_consumed) parts.push(`⛽ −${data.fuel_consumed}`);
        if (data.food_consumed) parts.push(`🍖 −${data.food_consumed}`);
        lines.push(parts.length ? `Day complete · ${parts.join(' · ')}` : 'Day complete');

        if (data.milestone) lines.push(`🏁 ${data.milestone}% of the journey!`);
        if (data.slingshot) lines.push('🚀 Gravitational slingshot! Your base speed doubled.');
        if (data.vibes_state === 'critical') lines.push('😡 Critical vibes! The crew is on the verge of mutiny.');
        else if (data.vibes_state === 'low') lines.push('😟 Low vibes. Consider resting.');
        if (data.deserter) lines.push(`💨 ${data.deserter} has abandoned the mission!`);
        return lines.join('\n');
    },

    /** Lines after resting (data of the 'rested' outcome). */
    rest(data) {
        return `😴 The crew rests for a day.\n🛡️ Hull +${data.hull_repaired}%  😊 Vibes +${data.vibes_gained}%  🍖 −${data.food_consumed}`;
    },

    launch(data, session) {
        if (!data.initial) {
            return data.had_boost
                ? '🚀 Departing the station.\n⚠️ Gravitational assist lost (speed back to normal).'
                : '🚀 Departing the station.';
        }
        if (data.low_supplies) {
            const r = session.resources;
            const m = data.minimums;
            return `⚠️ Launching with low supplies!\n⛽ ${r.fuel} (min ${m.fuel})  🍖 ${r.food} (min ${m.food})  🛡️ ${r.hull}% (min ${m.hull}%)\nThis will be extremely difficult.`;
        }
        return '🚀 Ship launched! Your journey to Uranus begins.';
    },

    returned(data) {
        return `↩️ Turned back to ${data.waypoint.name}. ${data.days} days passed.\n⛽ −${data.fuel_consumed}  🍖 −${data.food_consumed}`;
    },

    huntNothing: () => '🎣 You spend the day scavenging but find nothing of use.',

    docked(data) {
        const lines = [`🛰️ Docked at ${data.station.name}.`];
        if (data.had_boost) lines.push('⚠️ Gravitational assist lost.');
        return lines.join('\n');
    },

    skipped(data) {
        return data.was_already_boosted
            ? `➡️ Bypassed ${data.waypoint.name}. Speed boost maintained (2×).`
            : `➡️ Bypassed ${data.waypoint.name}. Gravitational assist engaged: 2× speed until your next stop.`;
    },

    purchase(data) {
        const shop = SHOP_LABELS[data.item];
        const name = shop ? shop.name : data.item;
        let text = `✅ Bought ${name} for ${Formatters.formatEth(data.cost)} ETH (${shop ? shop.gives(data.quantity_added) : data.quantity_added}).`;
        if (data.capped) text += ' It was nearly full, so only part fit.';
        return text;
    },

    /** One line per changed resource: "⛽ Fuel 355 → 405 (+50)". */
    resourceChanges(changes) {
        return Object.entries(changes || {}).map(([key, c]) => {
            const label = RESOURCE_LABELS[key] || { icon: '', name: key };
            const fmt = key === 'eth' ? Formatters.formatEth : (v) => v;
            const delta = c.delta > 0 ? `+${fmt(c.delta)}` : `${fmt(c.delta)}`;
            return { key, up: c.delta > 0, text: `${label.icon} ${label.name} ${fmt(c.before)} → ${fmt(c.after)} (${delta})` };
        });
    },
};

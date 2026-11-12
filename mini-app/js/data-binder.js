/**
 * Data Binder - draws the game on the dashboard.
 *
 * Everything shown comes from the server snapshot (status, shop, session). Nothing here
 * works out game rules: days remaining, percentages, crew and limits all arrive ready.
 */

import { Formatters } from './formatters.js';

export class DataBinder {
    /**
     * Bind a snapshot ({session, status, shop, ...}) to the UI
     */
    bindSnapshot(snap) {
        this.bindHeader(snap);
        this.bindProgress(snap);
        this.bindResources(snap);
        this.bindCrew(snap);
        this.updateButtons(snap);
    }

    bindHeader(snap) {
        const dayElement = document.querySelector('.day-display');
        if (dayElement) {
            dayElement.textContent = snap.status.day;
        }
    }

    bindProgress(snap) {
        const { progress, speed } = snap.status;

        const currentDistElement = document.querySelector('.current-distance');
        if (currentDistElement) {
            currentDistElement.textContent = Formatters.formatDistance(progress.distance);
        }

        const remainingDistElement = document.querySelector('.remaining-distance');
        if (remainingDistElement) {
            remainingDistElement.textContent = `${Formatters.formatDistance(progress.remaining)} to go`;
        }

        const percent = Math.min(100, (progress.distance / progress.total_distance) * 100);
        const progressFill = document.querySelector('.progress-fill');
        if (progressFill) {
            progressFill.style.width = `${percent}%`;
        }

        const shipIcon = document.querySelector('.ship-icon');
        if (shipIcon) {
            shipIcon.style.left = `${percent}%`;
        }

        const velocityElement = document.querySelector('.velocity-display');
        if (velocityElement) {
            velocityElement.textContent = Formatters.formatVelocity(speed.base, speed.multiplier);
        }

        this.updateLocationLabel(snap);
    }

    updateLocationLabel(snap) {
        const locationElement = document.querySelector('.location-label');
        if (!locationElement) return;

        const { session, status } = snap;
        if (session.status === 'pre_launch') {
            locationElement.textContent = 'Preparing for launch';
        } else if (session.status === 'at_station' && status.at_waypoint) {
            locationElement.textContent = `At ${status.at_waypoint.name}`;
        } else if (session.pending_station_arrival) {
            locationElement.textContent = `Approaching ${session.pending_station_arrival.name}`;
        } else if (status.next_waypoint) {
            const days = status.next_waypoint.days !== null ? ` · ~${status.next_waypoint.days}d` : '';
            locationElement.textContent = `Next: ${status.next_waypoint.name}${days}`;
        } else {
            locationElement.textContent = 'Final approach to Uranus';
        }
    }

    bindResources(snap) {
        const r = snap.status.resources;
        const limit = (resource) => snap.shop.items.find((item) => item.resource === resource)?.max;

        this.bindResource('fuel', { current: r.fuel.amount, max: limit('fuel'), days: r.fuel.days });
        this.bindResource('food', { current: r.food.amount, max: limit('food'), days: r.food.days });
        this.bindResource('hull', { current: r.hull.amount, max: 100, isPercent: true });
        this.bindResource('morale', { current: r.vibes.amount, max: 100, isPercent: true });

        const ethElement = document.querySelector('.eth-value');
        if (ethElement) {
            ethElement.textContent = Formatters.formatEth(r.eth);
        }
    }

    bindResource(type, data) {
        const container = document.querySelector(`.gauge-${type}`);
        if (!container) return;

        const percent = Math.min(100, Formatters.formatPercent(data.current, data.max));

        container.setAttribute('data-value', data.current);
        container.setAttribute('data-max', data.max);

        const valueElement = container.querySelector('.gauge-value');
        if (valueElement) {
            valueElement.textContent = data.isPercent
                ? `${data.current}%`
                : Formatters.formatResourceWithDays(data.current, data.days);
        }

        const needleElement = container.querySelector('.gauge-needle');
        if (needleElement) {
            needleElement.style.setProperty('--needle-pos', `${percent}%`);
        }

        const fillElement = container.querySelector('.gauge-fill');
        if (fillElement) {
            fillElement.style.width = `${percent}%`;
            if (!data.isPercent) {
                fillElement.style.background = Formatters.getResourceStatusColor(data.days);
            }
        }

        const warningElement = container.querySelector('.gauge-warning');
        if (warningElement && !data.isPercent) {
            const emoji = Formatters.getWarningEmoji(data.days);
            warningElement.textContent = emoji;
            warningElement.style.display = emoji ? 'inline' : 'none';
        }

        container.classList.toggle('warning', !data.isPercent && data.days < 10);
    }

    bindCrew(snap) {
        const crewContainer = document.querySelector('.crew-roster');
        if (!crewContainer) return;

        crewContainer.innerHTML = '';
        snap.status.crew.forEach((member) => crewContainer.appendChild(this.createCrewCard(member)));
    }

    createCrewCard(member) {
        const card = document.createElement('div');
        card.className = `crew-card ${member.active ? 'alive' : 'dead'}`;

        const avatar = document.createElement('div');
        avatar.className = 'crew-avatar';
        avatar.textContent = member.active ? '👨‍🚀' : '💀';

        const info = document.createElement('div');
        info.className = 'crew-info';

        const name = document.createElement('div');
        name.className = 'crew-name';
        name.textContent = member.name;

        const role = document.createElement('div');
        role.className = 'crew-role';
        role.textContent = member.role;

        info.append(name, role);

        const status = document.createElement('div');
        status.className = 'crew-status';
        status.textContent = member.active ? '✓' : '†';

        card.append(avatar, info, status);
        return card;
    }

    /**
     * Show only the controls that make sense in the current situation
     */
    updateButtons(snap) {
        const status = snap.session.status;
        const show = (id, visible) => {
            const button = document.getElementById(id);
            if (button) button.style.display = visible ? 'flex' : 'none';
        };

        const atStation = status === 'at_station';
        const inSpace = status === 'in_space';

        show('btn-continue', status !== 'pre_launch');
        show('btn-hunt', inSpace);
        show('btn-return', inSpace && !snap.status.at_waypoint);
        show('btn-rest', atStation);
        show('btn-shop', atStation || status === 'pre_launch');
        show('btn-status', true);
        show('btn-abandon', true);

        const continueText = document.querySelector('#btn-continue .btn-text');
        const continueHint = document.querySelector('#btn-continue .btn-hint');
        if (continueText) continueText.textContent = atStation ? 'Depart' : 'Continue';
        if (continueHint) continueHint.textContent = atStation ? 'leave the station' : 'advance 1 day';

        const shopText = document.querySelector('#btn-shop .btn-text');
        if (shopText) shopText.textContent = status === 'pre_launch' ? 'Launch Pad' : 'Shop';
    }
}

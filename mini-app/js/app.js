/**
 * Occupy Uranus Mini-App
 * Main application logic
 *
 * The server owns the game. This file asks it what the game looks like (api.getState), tells it
 * what the player did (api.act) and draws what comes back. Every sentence comes from messages.js.
 *
 * Inside Telegram the player is signed in by Telegram. In a plain browser they sign in with a
 * captain name (honor system, see web-player.js); the same name always opens the same game.
 */

import { TelegramSDK } from './telegram-sdk.js';
import { API, ApiError } from './api.js';
import { DataBinder } from './data-binder.js';
import { Formatters } from './formatters.js';
import { Messages, RESOURCE_LABELS, SHOP_LABELS } from './messages.js';
import { WebPlayer, NAME_MIN, NAME_MAX } from './web-player.js';
import { el } from './dom.js';

const POLL_INTERVAL = 10000; // ms

class OccupyUranusApp {
    constructor() {
        this.telegram = new TelegramSDK();
        this.webPlayer = new WebPlayer();
        this.api = new API(this.telegram, this.webPlayer);
        this.dataBinder = new DataBinder();

        this.snap = null;            // latest snapshot from the server
        this.pollInterval = null;
        this.isProcessingAction = false;
        this.uiState = 'journey';    // journey | event | waypoint | panel | start | ended
        this.panelRefresh = null;    // redraws the open panel after the game changes
        this.flashTimer = null;
        this.listenersReady = false;
    }

    // =========================================================================
    // STARTUP
    // =========================================================================

    async init() {
        this.telegram.init();
        this.setupSignIn();

        if (!this.telegram.isTelegram() && !this.webPlayer.name()) {
            this.showSignIn();
            return;
        }
        await this.start();
    }

    /** Load the signed-in player's game and show it. */
    async start() {
        this.showLoading();

        try {
            await this.refresh();
            if (!this.listenersReady) {
                this.setupEventListeners();
                this.listenersReady = true;
            }
            this.hideLoading();
            this.resume();
            this.startPolling();
        } catch (error) {
            console.error('[App] Initialization failed:', error);
            this.hideLoading();
            if (error instanceof ApiError && error.code === 'invalid_player_name') {
                this.webPlayer.clear();
                this.showSignIn(Messages.nameRules(NAME_MIN, NAME_MAX));
                return;
            }
            this.showError(this.describeError(error));
        }
    }

    async refresh() {
        this.snap = await this.api.getState();
    }

    /**
     * Show whatever the game is waiting for: start page, ending, event, waypoint decision,
     * launch pad, or just the dashboard.
     */
    resume() {
        this.closeOverlays();
        const snap = this.snap;

        if (!snap.has_session) {
            this.showStartPage();
            return;
        }

        this.hideStartPage();
        this.dataBinder.bindSnapshot(snap);

        const session = snap.session;
        if (snap.ending && (snap.ending.game_over || snap.ending.victory)) {
            this.showEnding(snap.ending);
        } else if (session.pending_event && snap.pending_event) {
            this.showEventModal(snap.pending_event);
        } else if (session.pending_station_arrival) {
            this.showWaypointModal(session.pending_station_arrival);
        } else if (session.status === 'pre_launch') {
            this.showLaunchPanel();
        } else {
            this.uiState = 'journey';
        }

        // Polling stops at an ending; a new journey needs it again
        if (!this.pollInterval && !(snap.ending && (snap.ending.game_over || snap.ending.victory))) {
            this.startPolling();
        }
    }

    setupEventListeners() {
        const on = (id, handler) => document.getElementById(id)?.addEventListener('click', handler);

        on('btn-continue', () => this.act('advance_day', {}, 'medium'));
        on('btn-hunt', () => this.act('hunt', {}, 'medium'));
        on('btn-rest', () => this.act('rest', {}, 'medium'));
        on('btn-shop', () => this.openShop());
        on('btn-status', () => this.showStatusPanel());
        on('btn-return', () => this.showReturnPanel());
        on('btn-abandon', () => this.showAbandonPanel());

        on('startPageNewBtn', () => this.act('new_game', {}, 'medium'));
        on('startPageCloseBtn', () => (this.telegram.isTelegram() ? this.telegram.close() : this.switchCaptain()));
        on('btnStopAtWaypoint', () => this.decideWaypoint('stop_at_waypoint'));
        on('btnSkipWaypoint', () => this.decideWaypoint('skip_waypoint'));
        on('gameOverNewBtn', () => this.newJourney());
        on('victoryNewBtn', () => this.newJourney());
    }

    // =========================================================================
    // ACTIONS
    // =========================================================================

    /**
     * Run one action on the server and draw the result.
     * @returns the server response, or null if nothing was sent or the request failed
     */
    async act(action, params = {}, haptic = 'light') {
        if (this.isProcessingAction) return null;

        this.isProcessingAction = true;
        this.telegram.hapticFeedback(haptic);
        this.setButtonsDisabled(true);

        try {
            const response = await this.api.act(action, params);
            this.snap = response;
            this.handleOutcome(response);
            this.telegram.hapticFeedback(response.outcome === 'rejected' ? 'warning' : 'success');
            return response;
        } catch (error) {
            console.error('[App] Action failed:', action, error);
            this.showError(this.describeError(error));
            this.telegram.hapticFeedback('error');
            if (error instanceof ApiError && error.code === 'no_session') {
                await this.refresh().catch(() => {});
                this.resume();
            }
            return null;
        } finally {
            this.isProcessingAction = false;
            this.setButtonsDisabled(false);
        }
    }

    /** Draw what an action did. The outcome codes are documented in game/GameService.php. */
    handleOutcome(response) {
        const { outcome, data } = response;
        const session = response.session;

        if (response.has_session) {
            this.dataBinder.bindSnapshot(response);
        }

        switch (outcome) {
            case 'game_created':
            case 'abandoned':
                this.resume();
                break;

            case 'launched':
                this.closePanel();
                this.showFlashMessage(Messages.launch(data, session));
                break;

            case 'day_advanced':
                this.closePanel();
                this.showFlashMessage(Messages.day(data));
                break;

            case 'event_triggered':
                this.closePanel();
                this.showEventModal(data.event);
                break;

            case 'event_resolved':
                this.showEventResult(data);
                break;

            case 'waypoint_reached':
                this.closePanel();
                this.showWaypointModal(data.waypoint);
                break;

            case 'docked':
                this.showFlashMessage(Messages.docked(data));
                this.showStationPanel();
                break;

            case 'waypoint_skipped':
                this.showFlashMessage(Messages.skipped(data));
                break;

            case 'rested':
                this.showFlashMessage(Messages.rest(data));
                this.refreshPanel();
                break;

            case 'hunted':
                this.showFlashMessage(Messages.huntNothing());
                break;

            case 'returned':
                this.closePanel();
                this.showFlashMessage(Messages.returned(data));
                break;

            case 'purchased':
                this.showFlashMessage(Messages.purchase(data));
                this.refreshPanel();
                break;

            case 'rejected':
                this.showFlashMessage(Messages.rejection(data.reason, data));
                this.refreshPanel();
                break;

            case 'game_over':
                this.closeOverlays();
                this.showEnding({ game_over: data.reason, victory: false });
                break;

            case 'victory':
                this.closeOverlays();
                this.showEnding({ game_over: null, victory: true });
                break;

            default:
                console.warn('[App] Unknown outcome:', outcome);
        }
    }

    /** End screens' "New Game": clear the finished game and start a fresh one. */
    async newJourney() {
        const abandoned = await this.act('abandon');
        if (abandoned) {
            await this.act('new_game');
        }
    }

    describeError(error) {
        if (error instanceof ApiError) {
            if (error.code === 'network') return '🌐 Cannot connect to the server. Check your connection.';
            if (error.status === 401) return '🔒 Invalid sign-in. Please reopen the game from Telegram.';
            if (error.code === 'no_session') return '📡 No journey found. Start a new one!';
            return `❌ ${error.message}`;
        }
        return '❌ Something went wrong. Please try again.';
    }

    // =========================================================================
    // POLLING (picks up moves made elsewhere, e.g. in the bot)
    // =========================================================================

    startPolling() {
        if (this.pollInterval) return;
        this.pollInterval = setInterval(async () => {
            if (this.uiState !== 'journey' || this.isProcessingAction) return;

            try {
                const fresh = await this.api.getState();
                if (JSON.stringify(fresh.session) !== JSON.stringify(this.snap.session)) {
                    this.snap = fresh;
                    this.resume();
                }
            } catch (error) {
                console.warn('[App] Poll error:', error);
            }
        }, POLL_INTERVAL);
    }

    stopPolling() {
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
    }

    // =========================================================================
    // LOADING, ERRORS, FLASH
    // =========================================================================

    showLoading() {
        const spinner = document.getElementById('loadingSpinner');
        if (spinner) spinner.style.display = 'flex';
    }

    hideLoading() {
        const spinner = document.getElementById('loadingSpinner');
        if (spinner) spinner.style.display = 'none';
    }

    /** While a request is in flight every button is inert (see body.busy in the stylesheet). */
    setButtonsDisabled(disabled) {
        document.body.classList.toggle('busy', disabled);
    }

    showFlashMessage(message) {
        const flashPanel = document.getElementById('flashMessage');
        const flashText = document.querySelector('.flash-text');
        if (!flashPanel || !flashText) return;

        flashText.textContent = message;
        flashText.style.whiteSpace = 'pre-line';
        flashPanel.style.display = 'block';

        clearTimeout(this.flashTimer);
        this.flashTimer = setTimeout(() => { flashPanel.style.display = 'none'; }, 6000);
    }

    showError(message) {
        const toast = document.getElementById('errorToast');
        const text = document.getElementById('errorText');
        if (!toast || !text) {
            alert(message);
            return;
        }

        text.textContent = message;
        toast.style.display = 'flex';
        setTimeout(() => this.dismissError(), 5000);
    }

    dismissError() {
        const toast = document.getElementById('errorToast');
        if (toast) toast.style.display = 'none';
    }

    // =========================================================================
    // WEB SIGN-IN (captain name, honor system; never shown inside Telegram)
    // =========================================================================

    setupSignIn() {
        const form = document.getElementById('signInForm');
        if (!form) return;
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            const name = WebPlayer.normalize(document.getElementById('signInName').value);
            if (name.length < NAME_MIN || name.length > NAME_MAX) {
                this.showSignIn(Messages.nameRules(NAME_MIN, NAME_MAX));
                return;
            }
            this.webPlayer.save(name);
            this.hideSignIn();
            await this.start();
        });
    }

    showSignIn(errorText = '') {
        this.uiState = 'signin';
        const dashboard = document.querySelector('.dashboard');
        if (dashboard) dashboard.style.display = 'none';
        const page = document.getElementById('signInPage');
        if (page) page.style.display = 'flex';
        const error = document.getElementById('signInError');
        if (error) error.textContent = errorText;
        document.getElementById('signInName')?.focus();
    }

    hideSignIn() {
        const page = document.getElementById('signInPage');
        if (page) page.style.display = 'none';
    }

    /** Leave this ship (the game stays saved under its name) and sign in as someone else. */
    switchCaptain() {
        this.stopPolling();
        this.closePanel();
        this.closeOverlays();
        this.hideStartPage();
        this.webPlayer.clear();
        this.snap = null;
        const input = document.getElementById('signInName');
        if (input) input.value = '';
        this.showSignIn();
    }

    // =========================================================================
    // START PAGE
    // =========================================================================

    showStartPage() {
        this.uiState = 'start';
        const web = !this.telegram.isTelegram();
        const captain = document.getElementById('startCaptain');
        if (captain) {
            captain.textContent = web ? Messages.captain(this.webPlayer.name()) : '';
            captain.style.display = web ? 'block' : 'none';
        }
        const sameGame = document.getElementById('startSameGame');
        if (sameGame) sameGame.textContent = web ? Messages.webSameGame : Messages.telegramSameGame;
        const close = document.querySelector('#startPageCloseBtn .btn-text');
        if (close) close.textContent = web ? Messages.switchCaptain : Messages.close;
        const dashboard = document.querySelector('.dashboard');
        if (dashboard) dashboard.style.display = 'none';
        const startPage = document.getElementById('startPage');
        if (startPage) startPage.style.display = 'flex';
    }

    hideStartPage() {
        const startPage = document.getElementById('startPage');
        if (startPage) startPage.style.display = 'none';
        const dashboard = document.querySelector('.dashboard');
        if (dashboard) dashboard.style.display = '';
    }

    // =========================================================================
    // OVERLAYS
    // =========================================================================

    closeOverlays() {
        ['eventModal', 'waypointModal', 'panelModal', 'gameOverScreen', 'victoryScreen'].forEach((id) => {
            const node = document.getElementById(id);
            if (node) node.style.display = 'none';
        });
        this.panelRefresh = null;
    }

    // ---- Event ------------------------------------------------------------

    showEventModal(event) {
        const modal = document.getElementById('eventModal');
        const title = document.getElementById('eventTitle');
        const description = document.getElementById('eventDescription');
        const choices = document.getElementById('eventChoices');
        if (!modal || !title || !description || !choices) return;

        title.textContent = event.title;
        description.replaceChildren(
            el('p', {}, event.description),
            ...(event.reactions || []).map((r) => el('p', { class: 'event-reaction' }, `💬 ${r.name}: ${r.says}`)),
        );

        choices.replaceChildren(...event.choices.map((choice) => {
            const hasCost = choice.cost && choice.cost !== 'None';
            const hasRisk = choice.risk && choice.risk !== 'None';
            return el('button', { class: 'event-choice-btn', onclick: () => this.chooseEvent(event.id, choice.id) },
                el('div', { class: 'event-choice-depth' }),
                el('div', { class: 'event-choice-face' },
                    el('div', { class: 'event-choice-text' }, choice.text),
                    el('div', { class: 'event-choice-meta' },
                        hasCost ? el('span', {}, `💰 ${choice.cost}`) : null,
                        hasRisk ? el('span', {}, `⚠️ ${choice.risk}`) : null,
                    ),
                ),
            );
        }));

        modal.style.display = 'flex';
        this.uiState = 'event';
    }

    async chooseEvent(eventId, choiceId) {
        const response = await this.act('process_event', { event_id: eventId, choice_id: choiceId }, 'medium');
        if (response) {
            const modal = document.getElementById('eventModal');
            if (modal && response.outcome !== 'event_triggered') modal.style.display = 'none';
        }
    }

    showEventResult(data) {
        document.getElementById('eventModal').style.display = 'none';

        const changes = Messages.resourceChanges(data.resource_changes);
        this.showPanel({
            icon: data.event_outcome === 'failure' ? '💥' : '🎲',
            title: 'Result',
            body: [
                el('p', { class: 'panel-note' }, `You chose: ${data.choice_text}`),
                el('p', {}, data.outcome_text),
                data.chad_reaction ? el('p', { class: 'event-reaction' }, `💬 ${data.chad_reaction.name}: "${data.chad_reaction.says}"`) : null,
                ...(data.deaths || []).map((name) => el('p', { class: 'panel-warning' }, `💀 ${name} has perished.`)),
                changes.length ? el('div', { class: 'panel-list' },
                    ...changes.map((c) => el('div', { class: `panel-row ${c.up ? 'up' : 'down'}` }, c.text))) : null,
            ],
            actions: [{ label: 'Back to Journey', primary: true, onClick: () => this.closePanel() }],
        });
    }

    // ---- Waypoint -----------------------------------------------------------

    showWaypointModal(waypoint) {
        const modal = document.getElementById('waypointModal');
        const nameEl = document.getElementById('waypointName');
        if (!modal || !nameEl) return;

        nameEl.textContent = `${waypoint.symbol || '🛰️'} ${waypoint.name || 'Waypoint Station'}`;
        modal.style.display = 'flex';
        this.uiState = 'waypoint';
    }

    async decideWaypoint(action) {
        const response = await this.act(action, {}, 'medium');
        if (response && response.outcome !== 'rejected') {
            document.getElementById('waypointModal').style.display = 'none';
            if (this.uiState === 'waypoint') this.uiState = 'journey';
        } else if (response) {
            await this.refresh();
            this.resume();
        }
    }

    // ---- Panels -------------------------------------------------------------

    /**
     * Open the general-purpose panel.
     * @param {object} p {icon, title, subtitle, body: Node[], actions: [{label, hint, primary, disabled, onClick}]}
     */
    showPanel(p) {
        const modal = document.getElementById('panelModal');
        if (!modal) return;

        document.getElementById('panelIcon').textContent = p.icon || '';
        document.getElementById('panelTitle').textContent = p.title || '';
        const subtitle = document.getElementById('panelSubtitle');
        subtitle.textContent = p.subtitle || '';
        subtitle.style.display = p.subtitle ? 'block' : 'none';

        document.getElementById('panelBody').replaceChildren(...(p.body || []).filter(Boolean));
        document.getElementById('panelActions').replaceChildren(...(p.actions || []).map((a) =>
            el('button', {
                class: `panel-btn ${a.primary ? 'primary' : ''}`,
                disabled: a.disabled,
                onclick: a.onClick,
            }, el('span', { class: 'panel-btn-label' }, a.label), a.hint ? el('span', { class: 'panel-btn-hint' }, a.hint) : null),
        ));

        modal.style.display = 'flex';
        this.uiState = 'panel';
    }

    closePanel() {
        const modal = document.getElementById('panelModal');
        if (modal) modal.style.display = 'none';
        this.panelRefresh = null;
        if (this.uiState === 'panel') this.uiState = 'journey';
    }

    /** Redraw the open panel (after a purchase, a rest, a refusal) if it can change. */
    refreshPanel() {
        if (this.panelRefresh && this.uiState === 'panel') {
            this.panelRefresh();
        }
    }

    openShop() {
        if (this.snap.session.status === 'pre_launch') {
            this.showLaunchPanel();
        } else {
            this.showStationPanel();
        }
    }

    /** Shop rows: what is for sale, what the player has, and a Buy button. */
    shopRows() {
        return el('div', { class: 'panel-list' }, ...this.snap.shop.items.map((item) => {
            const label = SHOP_LABELS[item.id] || { icon: '', name: item.id, gives: (q) => `+${q}` };
            return el('div', { class: 'panel-row shop-row' },
                el('div', { class: 'shop-info' },
                    el('div', { class: 'shop-name' }, `${label.icon} ${label.name}`),
                    el('div', { class: 'shop-sub' }, `${label.gives(item.quantity)} · you have ${Formatters.formatNumber(item.current)} / ${Formatters.formatNumber(item.max)}`),
                ),
                el('button', {
                    class: 'panel-btn shop-buy',
                    disabled: !item.affordable || item.full,
                    onclick: () => this.act('purchase', { item: item.id }, 'light'),
                }, item.full ? 'Full' : `${Formatters.formatEth(item.price)} ETH`),
            );
        }));
    }

    ethLine() {
        return el('p', { class: 'panel-note' }, `💰 ${Formatters.formatEth(this.snap.shop.eth)} ETH available`);
    }

    showLaunchPanel() {
        this.showPanel({
            icon: '🚀',
            title: 'Launch Pad',
            subtitle: 'Stock up before you leave Earth',
            body: [this.ethLine(), this.shopRows()],
            actions: [{ label: 'Launch Ship', hint: 'begin the journey', primary: true, onClick: () => this.act('launch', {}, 'heavy') }],
        });
        this.panelRefresh = () => this.showLaunchPanel();
    }

    showStationPanel() {
        const station = this.snap.status.at_waypoint;
        if (!station) return;

        const story = station.local_story
            ? [
                el('p', { class: 'panel-note' }, `${station.local_character}${station.local_job ? ` — ${station.local_job}` : ''}`),
                el('p', { class: 'station-story' }, `"${station.local_story}"`),
            ]
            : [station.description ? el('p', { class: 'station-story' }, station.description) : null];

        this.showPanel({
            icon: station.symbol || '🛰️',
            title: station.name,
            subtitle: `Day ${this.snap.status.day}`,
            body: [...story, this.ethLine(), this.shopRows()],
            actions: [
                { label: 'Rest', hint: '+20 hull, +30 vibes, costs a day of food', onClick: () => this.act('rest', {}, 'medium') },
                { label: 'Depart', hint: 'leave the station', primary: true, onClick: () => { this.closePanel(); this.act('advance_day', {}, 'medium'); } },
            ],
        });
        this.panelRefresh = () => this.showStationPanel();
    }

    showStatusPanel() {
        const st = this.snap.status;
        const r = st.resources;
        const row = (text, cls = '') => el('div', { class: `panel-row ${cls}` }, text);

        this.showPanel({
            icon: '📊',
            title: 'Status',
            subtitle: `Day ${st.day} of the journey`,
            body: [
                el('div', { class: 'panel-section' }, 'Mission'),
                el('div', { class: 'panel-list' },
                    row(`🎯 ${st.progress.percent}% to Uranus (${Formatters.formatDistance(st.progress.distance)} of ${Formatters.formatDistance(st.progress.total_distance)})`),
                    row(`🚀 ${Formatters.formatVelocity(st.speed.base, st.speed.multiplier)}`),
                    row(st.days_to_uranus !== null ? `⏳ About ${st.days_to_uranus} days remaining` : '⏳ Not moving'),
                    st.speed.boost_active ? row('⚡ Gravitational assist active (2× speed)', 'up') : null,
                ),
                st.warnings.length ? el('div', { class: 'panel-list' }, ...st.warnings.map((w) => row(`⚠️ ${Messages.warning(w)}`, 'down'))) : null,
                el('div', { class: 'panel-section' }, `Crew (${r.chad.active}/${r.chad.max})`),
                row(`😊 Vibes ${r.vibes.amount}% · ${Messages.vibesBand(r.vibes.band)}`),
                el('div', { class: 'panel-list' }, ...st.crew.map((m) => el('div', { class: `panel-row crew-row ${m.active ? '' : 'lost'}` },
                    el('div', { class: 'shop-name' }, `${m.active ? '👨‍🚀' : '💀'} ${m.name}`),
                    el('div', { class: 'shop-sub' }, `${m.role}${m.trait ? ` · ${m.trait}` : ''}${m.active ? '' : ' · lost'}`),
                    m.quote ? el('div', { class: 'crew-quote' }, `"${m.quote}"`) : null,
                ))),
                el('div', { class: 'panel-section' }, 'Ship & supplies'),
                el('div', { class: 'panel-list' },
                    row(`🛡️ Hull ${r.hull.amount}% · ${Messages.hullBand(r.hull.band)}`),
                    row(`⛽ Fuel ${Formatters.formatNumber(r.fuel.amount)} · ${r.fuel.days} days at current rate`),
                    row(`🍖 Food ${Formatters.formatNumber(r.food.amount)} · ${r.food.days} days for the full crew`),
                    row(`💰 ${Formatters.formatEth(r.eth)} ETH`),
                ),
                this.telegram.isTelegram() ? null : el('div', { class: 'panel-section' }, Messages.captain(this.webPlayer.name())),
            ],
            actions: [
                { label: 'Close', primary: true, onClick: () => this.closePanel() },
                this.telegram.isTelegram() ? null : { label: Messages.switchCaptain, hint: Messages.switchCaptainHint, onClick: () => this.switchCaptain() },
            ].filter(Boolean),
        });
    }

    showReturnPanel() {
        const costs = this.snap.return_costs;
        this.showPanel({
            icon: '↩️',
            title: 'Turn back?',
            subtitle: `Return to ${costs.last_waypoint.name}`,
            body: [
                el('p', {}, `Turning back takes ${costs.days} days and puts you ${Formatters.formatDistance(costs.backtrack_km)} behind.`),
                el('div', { class: 'panel-list' },
                    el('div', { class: 'panel-row down' }, `⛽ −${costs.fuel_cost} fuel`),
                    el('div', { class: 'panel-row down' }, `🍖 −${costs.food_cost} food`),
                ),
            ],
            actions: [
                { label: 'Turn back', onClick: () => this.act('return_to_waypoint', {}, 'medium') },
                { label: 'Stay on course', primary: true, onClick: () => this.closePanel() },
            ],
        });
    }

    showAbandonPanel() {
        this.showPanel({
            icon: '🗑️',
            title: 'Abandon mission?',
            body: [el('p', {}, `Your journey (day ${this.snap.status.day}) will be deleted for good, in the bot too.`)],
            actions: [
                { label: 'Yes, abandon it', onClick: () => this.act('abandon', {}, 'heavy') },
                { label: 'Keep going', primary: true, onClick: () => this.closePanel() },
            ],
        });
    }

    // ---- Endings --------------------------------------------------------------

    showEnding(ending) {
        this.stopPolling();
        this.uiState = 'ended';
        const session = this.snap.session || {};
        const stats = session.stats || {};
        const progress = session.progress || {};

        const statItem = (value, label) => el('div', { class: 'stat-item' },
            el('div', { class: 'stat-value' }, value), el('div', { class: 'stat-label' }, label));

        if (ending.victory) {
            document.getElementById('victoryStats')?.replaceChildren(
                statItem(progress.day || 0, 'Total Days'),
                statItem(Formatters.formatDistance(progress.distance || 0), 'Distance'),
                statItem(session.resources?.chad ?? 0, 'Chad Survived'),
                statItem(stats.events_encountered || 0, 'Events'),
            );
            document.getElementById('victoryScreen').style.display = 'flex';
            this.startConfetti();
            return;
        }

        document.getElementById('gameOverReason').textContent = Messages.gameOver(ending.game_over);
        document.getElementById('gameOverStats')?.replaceChildren(
            statItem(progress.day || 0, 'Days Survived'),
            statItem(Formatters.formatDistance(progress.distance || 0), 'Distance Traveled'),
            statItem(stats.chad_lost || 0, 'Chad Lost'),
            statItem(`${this.snap.status ? this.snap.status.progress.percent : 0}%`, 'Progress'),
        );
        document.getElementById('gameOverScreen').style.display = 'flex';
    }

    startConfetti() {
        const canvas = document.getElementById('confettiCanvas');
        if (!canvas) return;

        const ctx = canvas.getContext('2d');
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;

        const colors = ['#ffb833', '#55ee77', '#44ddcc', '#ff9944'];
        const particles = Array.from({ length: 100 }, () => ({
            x: Math.random() * canvas.width,
            y: Math.random() * canvas.height - canvas.height,
            vx: (Math.random() - 0.5) * 2,
            vy: Math.random() * 3 + 2,
            rotation: Math.random() * 360,
            vr: (Math.random() - 0.5) * 10,
            size: Math.random() * 8 + 4,
            color: colors[Math.floor(Math.random() * colors.length)],
        }));

        const animate = () => {
            if (this.uiState !== 'ended') return;
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            particles.forEach((p) => {
                ctx.save();
                ctx.translate(p.x, p.y);
                ctx.rotate(p.rotation * Math.PI / 180);
                ctx.fillStyle = p.color;
                ctx.fillRect(-p.size / 2, -p.size / 2, p.size, p.size);
                ctx.restore();

                p.x += p.vx;
                p.y += p.vy;
                p.rotation += p.vr;
                p.vy += 0.1;

                if (p.y > canvas.height) {
                    p.y = -10;
                    p.x = Math.random() * canvas.width;
                }
            });

            requestAnimationFrame(animate);
        };

        animate();
    }
}

// Initialize app when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.app = new OccupyUranusApp();
        window.app.init();
    });
} else {
    window.app = new OccupyUranusApp();
    window.app.init();
}

/**
 * API Client for the Mini-App
 *
 * Talks to api/game/: one endpoint to read the game, one to act on it.
 * Actions are GameService action names, results are GameService outcomes (see game/GameService.php).
 */

export class ApiError extends Error {
    constructor(code, message, status) {
        super(message);
        this.code = code;
        this.status = status;
    }
}

export class API {
    constructor(telegramSDK, webPlayer) {
        this.telegram = telegramSDK;
        this.webPlayer = webPlayer;
        // Relative to this page, so it works under any deploy folder (/mini-app/ sits beside /api/)
        this.baseURL = new URL('../api/game/', window.location.href).href;
    }

    /** Who is asking: Telegram's signed initData, or a web player's captain name. */
    signIn() {
        return this.telegram.isTelegram()
            ? { init_data: this.telegram.getInitData() }
            : { player: this.webPlayer.name() || '' };
    }

    /** The signed-in player's game: has_session, session, status, map, shop, return_costs, pending_event */
    async getState() {
        const query = new URLSearchParams(this.signIn()).toString();
        return this.request(`${this.baseURL}state.php?${query}`, { method: 'GET' });
    }

    /**
     * Run one action. Resolves with {outcome, data, ...snapshot}. A move the rules refuse
     * resolves too (outcome 'rejected'); it only throws for network, sign-in and server errors.
     */
    async act(action, params = {}) {
        return this.request(`${this.baseURL}action.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ...this.signIn(), action, params }),
        });
    }

    async request(url, options) {
        let response;
        try {
            response = await fetch(url, options);
        } catch (error) {
            throw new ApiError('network', 'Cannot reach the server', 0);
        }

        let body = null;
        try {
            body = await response.json();
        } catch (error) {
            throw new ApiError('bad_response', 'The server sent something unexpected', response.status);
        }

        if (!response.ok || body.success === false) {
            throw new ApiError(body.error || 'error', body.message || 'Request failed', response.status);
        }
        return body;
    }
}

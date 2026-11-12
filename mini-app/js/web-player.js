/**
 * The captain name of a web player (the Mini-App opened in a plain browser, not in Telegram).
 *
 * Honor system: the name is the whole sign-in. The server turns it into the player's game,
 * so the same name opens the same game in any browser. It is remembered in localStorage so
 * the player is not asked again; storage can be unavailable (private mode), so every access
 * is guarded and the app simply asks for the name again.
 */

const STORAGE_KEY = 'uranus.captain';

// Must match WEB_PLAYER_NAME_MIN / _MAX in api/game/auth.php (the server has the last word)
export const NAME_MIN = 2;
export const NAME_MAX = 20;

export class WebPlayer {
    constructor() {
        this.current = null;
        try {
            this.current = localStorage.getItem(STORAGE_KEY) || null;
        } catch (e) {
            this.current = null;
        }
    }

    name() {
        return this.current;
    }

    save(name) {
        this.current = name;
        try { localStorage.setItem(STORAGE_KEY, name); } catch (e) { /* remembered for this visit only */ }
    }

    clear() {
        this.current = null;
        try { localStorage.removeItem(STORAGE_KEY); } catch (e) { /* nothing stored */ }
    }

    /** Tidy what was typed (trim, single spaces); the server validates for real. */
    static normalize(raw) {
        return String(raw || '').trim().replace(/\s+/g, ' ');
    }
}

/**
 * Telegram Web App SDK Wrapper
 */

export class TelegramSDK {
    constructor() {
        this.webApp = null;
        this.initData = '';
        this.user = null;
    }

    /**
     * Initialize Telegram Web App SDK
     */
    init() {
        // telegram-web-app.js defines Telegram.WebApp in any browser; only Telegram fills initData
        const webApp = window.Telegram?.WebApp;
        if (!webApp || !webApp.initData) {
            console.info('[Telegram SDK] Not inside Telegram: web players sign in with a captain name');
            return;
        }

        this.webApp = webApp;

        // Signal ready to Telegram
        this.webApp.ready();

        // Get init data
        this.initData = this.webApp.initData;

        // Get user data
        this.user = this.webApp.initDataUnsafe?.user || null;

        // Expand to full height
        this.webApp.expand();

        // Set header color
        this.webApp.setHeaderColor('#030810');

        console.log('[Telegram SDK] Initialized:', {
            platform: this.webApp.platform,
            version: this.webApp.version,
            user: this.user,
        });
    }

    /** True when opened inside Telegram (signed in by Telegram), false in a plain browser. */
    isTelegram() {
        return this.webApp !== null;
    }

    /**
     * Get initData for API authentication
     */
    getInitData() {
        return this.initData;
    }

    /**
     * Get current user
     */
    getUser() {
        return this.user;
    }

    /**
     * Trigger haptic feedback
     */
    hapticFeedback(type = 'medium') {
        if (!this.webApp || !this.webApp.HapticFeedback) {
            console.log('[Telegram SDK] Haptic feedback not available');
            return;
        }

        switch (type) {
            case 'light':
                this.webApp.HapticFeedback.impactOccurred('light');
                break;
            case 'medium':
                this.webApp.HapticFeedback.impactOccurred('medium');
                break;
            case 'heavy':
                this.webApp.HapticFeedback.impactOccurred('heavy');
                break;
            case 'success':
                this.webApp.HapticFeedback.notificationOccurred('success');
                break;
            case 'warning':
                this.webApp.HapticFeedback.notificationOccurred('warning');
                break;
            case 'error':
                this.webApp.HapticFeedback.notificationOccurred('error');
                break;
            default:
                this.webApp.HapticFeedback.impactOccurred('medium');
        }
    }

    /**
     * Show main button
     */
    showMainButton(text, onClick) {
        if (!this.webApp || !this.webApp.MainButton) {
            console.log('[Telegram SDK] Main button not available');
            return;
        }

        this.webApp.MainButton.setText(text);
        this.webApp.MainButton.onClick(onClick);
        this.webApp.MainButton.show();
    }

    /**
     * Hide main button
     */
    hideMainButton() {
        if (!this.webApp || !this.webApp.MainButton) return;
        this.webApp.MainButton.hide();
    }

    /**
     * Close mini app
     */
    close() {
        if (!this.webApp) {
            console.log('[Telegram SDK] Cannot close - not in Telegram environment');
            return;
        }

        this.webApp.close();
    }

    /**
     * Show confirm dialog
     */
    showConfirm(message, callback) {
        if (!this.webApp) {
            const result = confirm(message);
            callback(result);
            return;
        }

        this.webApp.showConfirm(message, callback);
    }

    /**
     * Show alert
     */
    showAlert(message, callback) {
        if (!this.webApp) {
            alert(message);
            if (callback) callback();
            return;
        }

        this.webApp.showAlert(message, callback);
    }
}

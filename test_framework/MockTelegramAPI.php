<?php
/**
 * Mock Telegram API for Testing
 *
 * Captures all Telegram API calls without making real HTTP requests.
 * Allows tests to inspect what messages/keyboards the bot would send.
 *
 * Note: This extends TelegramAPI for type compatibility but overrides
 * all methods to avoid making real API calls.
 */

require_once __DIR__ . '/../bot/TelegramAPI.php';

class MockTelegramAPI extends TelegramAPI {
    public $sentMessages = [];
    public $editedMessages = [];
    public $deletedMessages = [];
    public $answeredCallbacks = [];
    public $fileUploads = [];

    // Override parent constructor to avoid cURL initialization
    public function __construct() {
        // Don't call parent::__construct() - we don't need real cURL
    }

    // Override parent destructor to avoid cURL cleanup
    public function __destruct() {
        // No-op
    }

    public function sendMessage($chatId, string $text, ?array $keyboard = null, ?int $threadId = null) {
        error_log("MockTelegramAPI::sendMessage called with text: " . substr($text, 0, 50));

        $message = [
            'chat_id' => $chatId,
            'text' => $text,
            'keyboard' => $keyboard,
            'thread_id' => $threadId,
            'message_id' => count($this->sentMessages) + 1000,
        ];

        $this->sentMessages[] = $message;
        error_log("MockTelegramAPI: Total messages now: " . count($this->sentMessages));

        // Return fake response
        return [
            'ok' => true,
            'result' => [
                'message_id' => $message['message_id'],
                'chat' => ['id' => $chatId],
                'text' => $text,
            ],
        ];
    }

    public function sendPhoto($chatId, string $photo, ?string $caption = null, ?array $keyboard = null, ?int $threadId = null) {
        $message = [
            'chat_id' => $chatId,
            'photo' => $photo,
            'caption' => $caption,
            'keyboard' => $keyboard,
            'thread_id' => $threadId,
            'message_id' => count($this->sentMessages) + 1000,
        ];

        $this->sentMessages[] = $message;
        $this->fileUploads[] = $photo;

        // Return fake response
        return [
            'ok' => true,
            'result' => [
                'message_id' => $message['message_id'],
                'chat' => ['id' => $chatId],
                'photo' => [['file_id' => 'fake_file_id_' . rand(1000, 9999)]],
                'caption' => $caption,
            ],
        ];
    }

    public function editMessage($chatId, int $messageId, string $text, ?array $keyboard = null) {
        $edit = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'keyboard' => $keyboard,
        ];

        $this->editedMessages[] = $edit;

        return ['ok' => true];
    }

    public function deleteMessage($chatId, int $messageId): bool {
        $this->deletedMessages[] = [
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ];

        return true;
    }

    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null, bool $showAlert = false) {
        $this->answeredCallbacks[] = [
            'callback_query_id' => $callbackQueryId,
            'text' => $text,
            'show_alert' => $showAlert,
        ];

        return ['ok' => true];
    }

    // Webhook methods - not needed for testing, just return success
    public function setWebhook(string $url, ?string $secretToken = null, array $allowedUpdates = []) {
        return ['ok' => true];
    }

    public function getWebhookInfo() {
        return [
            'ok' => true,
            'result' => [
                'url' => 'http://localhost/test',
                'has_custom_certificate' => false,
                'pending_update_count' => 0,
            ],
        ];
    }

    // Utility methods for testing

    public function getLastMessage() {
        return end($this->sentMessages) ?: null;
    }

    public function getMessageCount() {
        return count($this->sentMessages);
    }

    public function getLastKeyboard() {
        $lastMsg = $this->getLastMessage();
        return $lastMsg ? $lastMsg['keyboard'] : null;
    }

    public function reset() {
        $this->sentMessages = [];
        $this->editedMessages = [];
        $this->deletedMessages = [];
        $this->answeredCallbacks = [];
        $this->fileUploads = [];
    }
}

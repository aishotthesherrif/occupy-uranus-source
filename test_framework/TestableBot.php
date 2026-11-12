<?php
/**
 * Testable Bot Wrapper
 *
 * Extends OccupyUranusBot to inject MockTelegramAPI for testing.
 * Allows processing webhook updates without making real Telegram API calls.
 */

// Prevent uranus_game_bot.php from executing when included
define('TESTING_MODE', true);

require_once __DIR__ . '/MockTelegramAPI.php';
require_once __DIR__ . '/../uranus_game_bot.php';

class TestableBot extends OccupyUranusBot {
    public $mockTelegram;

    public function __construct() {
        // Call parent constructor
        parent::__construct();

        // Replace TelegramAPI with mock using reflection
        $this->mockTelegram = new MockTelegramAPI();

        $reflection = new ReflectionClass('OccupyUranusBot');
        $telegramProperty = $reflection->getProperty('telegram');
        $telegramProperty->setValue($this, $this->mockTelegram);
    }

    /**
     * Process a simulated webhook update
     * Uses parent's modified processWebhook() method that accepts update data
     */
    public function processUpdate(array $update): bool {
        return $this->processWebhook($update);
    }

    /**
     * Get the last message sent by the bot
     */
    public function getLastMessage() {
        return $this->mockTelegram->getLastMessage();
    }

    /**
     * Get all sent messages
     */
    public function getSentMessages() {
        return $this->mockTelegram->sentMessages;
    }

    /**
     * Reset mock for next test
     */
    public function resetMock() {
        $this->mockTelegram->reset();
    }
}

#!/usr/bin/env php
<?php
/**
 * Local Game Tester
 *
 * Tests the game UI locally by simulating Telegram webhook requests
 * and calling uranus_game_bot.php directly.
 *
 * Usage:
 *   php local_game_tester.php
 *   Then follow the interactive prompts
 */

// Color output for terminal
function colorize($text, $color) {
    $colors = [
        'green'  => "\033[32m",
        'blue'   => "\033[34m",
        'yellow' => "\033[33m",
        'red'    => "\033[31m",
        'cyan'   => "\033[36m",
        'reset'  => "\033[0m",
    ];
    return ($colors[$color] ?? '') . $text . $colors['reset'];
}

function printHeader($text) {
    echo "\n" . colorize(str_repeat('=', 70), 'cyan') . "\n";
    echo colorize($text, 'cyan') . "\n";
    echo colorize(str_repeat('=', 70), 'cyan') . "\n";
}

function separator() {
    echo colorize(str_repeat('-', 70), 'blue') . "\n";
}

// Simulate Telegram webhook request
function sendWebhookRequest($updateData) {
    // Create a temporary stream for stdin to capture webhook response
    $webhookInput = json_encode($updateData);

    // Capture current output buffer
    ob_start();

    // Simulate webhook environment
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['CONTENT_TYPE'] = 'application/json';

    // Mock the php://input stream
    $tempFile = tempnam(sys_get_temp_dir(), 'webhook_');
    file_put_contents($tempFile, $webhookInput);

    // Include the bot (it will process the webhook)
    // We need to capture its behavior

    // For now, let's do a simpler approach: directly instantiate and call methods
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/bot/TelegramAPI.php';
    require_once __DIR__ . '/game/GameSessionManager.php';
    require_once __DIR__ . '/bot/FormatHelpers.php';
    require_once __DIR__ . '/game/GameEngine.php';
    require_once __DIR__ . '/bot/GameText.php';
    require_once __DIR__ . '/bot/ViewManager.php';

    // Parse the update
    $update = $updateData;

    // Create bot instance (we'll need to mock or capture Telegram API calls)
    // This is complex - let's use a different approach

    $output = ob_get_clean();
    unlink($tempFile);

    return $output;
}

// Simpler approach: directly interact with game classes
class LocalGameTester {
    private $userId;
    private $chatId;
    private $game;
    private $gameEngine;
    private $viewManager;
    private $botController;
    private $messageLog = [];

    public function __construct($userId = 999999) {
        $this->userId = $userId;
        $this->chatId = $userId; // Use same for testing

        require_once __DIR__ . '/config.php';
        require_once __DIR__ . '/game/GameSessionManager.php';
        require_once __DIR__ . '/game/GameEngine.php';
        require_once __DIR__ . '/game/Telemetry.php';
        require_once __DIR__ . '/game/GameService.php';
        require_once __DIR__ . '/bot/FormatHelpers.php';
        require_once __DIR__ . '/bot/GameText.php';
        require_once __DIR__ . '/bot/ViewManager.php';
        require_once __DIR__ . '/bot/BotPreferences.php';
        require_once __DIR__ . '/bot/BotController.php';

        $this->gameEngine = new GameEngine();
        $this->game = new GameService($this->gameEngine, new GameSessionManager());
        $this->viewManager = new ViewManager($this->gameEngine);
        $this->botController = new BotController(
            $this->game,
            $this->viewManager,
            new GameText($this->gameEngine),
            new BotPreferences()
        );
    }

    public function start() {
        printHeader("LOCAL GAME TESTER");
        echo colorize("User ID: {$this->userId}\n", 'yellow');
        echo colorize("Session file: data/sessions/{$this->userId}.json\n", 'yellow');
        echo "\n";

        $this->mainMenu();
    }

    private function mainMenu() {
        while (true) {
            separator();
            echo "\nOptions:\n";
            echo "  1. Start new game\n";
            echo "  2. View current session\n";
            echo "  3. Send command\n";
            echo "  4. Press button\n";
            echo "  5. View last message\n";
            echo "  6. Delete session\n";
            echo "  0. Exit\n";
            echo "\n";
            echo colorize("Choice: ", 'green');

            $choice = trim(fgets(STDIN));

            switch ($choice) {
                case '1':
                    $this->startNewGame();
                    break;
                case '2':
                    $this->viewSession();
                    break;
                case '3':
                    $this->sendCommand();
                    break;
                case '4':
                    $this->pressButton();
                    break;
                case '5':
                    $this->viewLastMessage();
                    break;
                case '6':
                    $this->deleteSession();
                    break;
                case '0':
                    echo colorize("\nGoodbye!\n\n", 'green');
                    return;
                default:
                    echo colorize("Invalid choice!\n", 'red');
            }
        }
    }

    private function startNewGame() {
        printHeader("START NEW GAME");

        if ($this->game->getState($this->userId) !== null) {
            $this->game->act($this->userId, 'abandon', [], 'bot');
        }
        $session = $this->game->act($this->userId, 'new_game', ['username' => 'local_tester'], 'bot')['session'];

        echo colorize("\n✅ New game created!\n", 'green');
        echo colorize("Status: {$session['status']}\n", 'yellow');
        echo colorize("\nUse 'Press button' → 'shop' to see the shop, or 'launch_sequence' to launch.\n", 'cyan');
    }

    private function viewSession() {
        printHeader("CURRENT SESSION");

        $session = $this->game->getState($this->userId);
        if (!$session) {
            echo colorize("No active session!\n", 'red');
            return;
        }

        echo json_encode($session, JSON_PRETTY_PRINT) . "\n";
    }

    private function sendCommand() {
        printHeader("SEND COMMAND");

        echo "Available commands:\n";
        echo "  /game - Start new game\n";
        echo "  /status - View status\n";
        echo "  /help - Help\n";
        echo "\n";
        echo "Command: ";

        $command = trim(fgets(STDIN));

        // Process command through game engine
        echo colorize("\n⚠️  Command processing not fully implemented yet\n", 'yellow');
        echo colorize("Use 'Press button' option instead\n", 'yellow');
    }

    private function pressButton() {
        printHeader("PRESS BUTTON");

        $session = $this->game->getState($this->userId);
        if (!$session) {
            echo colorize("No active session! Start a new game first.\n", 'red');
            return;
        }

        echo "\nCurrent status: " . colorize($session['status'], 'yellow') . "\n";
        echo "\nCommon buttons:\n";
        echo "  continue - Advance day / Continue journey\n";
        echo "  launch_sequence - Launch ship button\n";
        echo "  journey - Journey view\n";
        echo "  status - Status view\n";
        echo "  map - Map view\n";
        echo "  shop - Shop view\n";
        echo "  rest - Rest at station\n";
        echo "\n";
        echo "Button to press: ";

        $button = trim(fgets(STDIN));

        try {
            $result = $this->botController->route($button, $this->userId, []);
            $this->displayMessage($result['view_data']);
        } catch (Exception $e) {
            echo colorize("\n❌ Error: " . $e->getMessage() . "\n", 'red');
        }
    }

    private function displayMessage($viewData) {
        printHeader("MESSAGE");

        // Store for "view last message"
        $this->messageLog[] = $viewData;

        // Display message text
        echo $this->stripHtml($viewData['message']) . "\n";

        // Display image URL if present
        if (!empty($viewData['image_url'])) {
            echo "\n" . colorize("📷 Image: {$viewData['image_url']}", 'cyan') . "\n";
        }

        // Display keyboard
        if (!empty($viewData['keyboard']['inline_keyboard'])) {
            separator();
            echo colorize("KEYBOARD:\n", 'yellow');
            foreach ($viewData['keyboard']['inline_keyboard'] as $row) {
                foreach ($row as $button) {
                    $text = $button['text'];
                    $data = $button['callback_data'];
                    echo "  [{$text}] → {$data}\n";
                }
            }
        }

        echo "\n";
    }

    private function stripHtml($text) {
        // Convert HTML to readable terminal output
        $text = str_replace('<b>', colorize('', 'green'), $text);
        $text = str_replace('</b>', colorize('', 'reset'), $text);
        $text = str_replace('<i>', colorize('', 'cyan'), $text);
        $text = str_replace('</i>', colorize('', 'reset'), $text);
        $text = strip_tags($text);
        return $text;
    }

    private function viewLastMessage() {
        if (empty($this->messageLog)) {
            echo colorize("No messages yet!\n", 'red');
            return;
        }

        $lastMessage = end($this->messageLog);
        $this->displayMessage($lastMessage);
    }

    private function deleteSession() {
        printHeader("DELETE SESSION");

        echo colorize("Are you sure? (yes/no): ", 'red');
        $confirm = trim(fgets(STDIN));

        if (strtolower($confirm) === 'yes') {
            $sessionFile = __DIR__ . "/data/sessions/{$this->userId}.json";
            if (file_exists($sessionFile)) {
                unlink($sessionFile);
                echo colorize("✅ Session deleted!\n", 'green');
            } else {
                echo colorize("No session file found.\n", 'yellow');
            }
        } else {
            echo "Cancelled.\n";
        }
    }
}

// Run the tester
$tester = new LocalGameTester();
$tester->start();

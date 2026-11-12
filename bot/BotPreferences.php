<?php
/**
 * BotPreferences
 *
 * Things only the Telegram bot cares about, kept per player outside the saved game:
 *   - chat:             where the bot replies ('group' | 'private'), and the group to return to
 *   - aesthetic:        which picture set the bot shows
 *   - resource_changes: what the last event changed, shown once on the next Journey screen
 *
 * The saved game (game/GameSessionManager) holds game state only, so a game started in the
 * mini-app and continued in the bot needs none of this.
 */
class BotPreferences {
    public const DEFAULT_AESTHETIC = 'main';

    private string $dir;

    public function __construct(?string $dir = null) {
        $this->dir = $dir ?? (LOGS_DIR . '/bot_prefs');
    }

    public function get(int $userId): array {
        $file = $this->file($userId);
        if (!is_file($file)) {
            return [];
        }
        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : [];
    }

    /** Merge $changes into the player's preferences. A null value removes the key. */
    public function set(int $userId, array $changes): void {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0755, true);
        }
        $prefs = array_merge($this->get($userId), $changes);
        $prefs = array_filter($prefs, fn($v) => $v !== null);
        file_put_contents($this->file($userId), json_encode($prefs), LOCK_EX);
    }

    /** 'private', 'group', or null when the player has not chosen (reply where they wrote). */
    public function preferredChat(int $userId): ?string {
        return $this->get($userId)['chat'] ?? null;
    }

    /** The group to return to after playing in DMs, or null. */
    public function originalChatId(int $userId) {
        return $this->get($userId)['original_chat_id'] ?? null;
    }

    public function aesthetic(int $userId): string {
        return $this->get($userId)['aesthetic'] ?? self::DEFAULT_AESTHETIC;
    }

    /** Game started from this chat: DMs mean private, groups mean group. */
    public function setChatFromStart(int $userId, $chatId): void {
        $this->set($userId, ['chat' => $chatId < 0 ? 'group' : 'private', 'original_chat_id' => null]);
    }

    public function setPrivate(int $userId, $originalChatId): void {
        $this->set($userId, ['chat' => 'private', 'original_chat_id' => $originalChatId]);
    }

    public function setGroup(int $userId): void {
        $this->set($userId, ['chat' => 'group', 'original_chat_id' => null]);
    }

    /** Returns the stored event resource changes once, then forgets them. */
    public function takeResourceChanges(int $userId): ?array {
        $changes = $this->get($userId)['resource_changes'] ?? null;
        if ($changes !== null) {
            $this->set($userId, ['resource_changes' => null]);
        }
        return $changes;
    }

    /** Forget where the player wanted replies (their game ended): reply in the chat they write in. */
    public function forgetChat(int $userId): void {
        $this->set($userId, ['chat' => null, 'original_chat_id' => null]);
    }

    /** Forget everything about every player (admin full reset). */
    public function clearAll(): void {
        foreach (glob($this->dir . '/*.json') ?: [] as $file) {
            unlink($file);
        }
    }

    /** Forget everything about a player. */
    public function clear(int $userId): void {
        $file = $this->file($userId);
        if (is_file($file)) {
            unlink($file);
        }
    }

    private function file(int $userId): string {
        return $this->dir . '/' . $userId . '.json';
    }
}

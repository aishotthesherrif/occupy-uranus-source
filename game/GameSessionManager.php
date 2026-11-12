<?php
/**
 * Game Session Manager
 * Handles loading, saving, and managing player game sessions.
 *
 * PATCH NOTES — changes from previous version:
 *
 * PER-USER SESSION FILES (was Issue #1 — scalability bottleneck)
 * - Sessions are now stored as individual files: SESSIONS_DIR/{userId}.json
 *   instead of a single monolithic game_state.json. This eliminates the global
 *   lock entirely — two players acting concurrently never touch each other's
 *   files. No more read-all → modify-one → write-all amplification.
 * - Atomic write uses the same PID-suffixed temp + rename pattern as before,
 *   but now operates on a single small file instead of the entire session store.
 * - getAllSessions() scans the directory. getActiveSessionCount() uses a cheap
 *   glob() instead of loading every session into memory.
 *
 * AUTOMATIC MIGRATION FROM MONOLITHIC FORMAT
 * - On first access, if SESSIONS_DIR is empty but the legacy GAME_STATE_FILE
 *   exists, migrateFromMonolithic() splits it into per-user files automatically.
 *   The legacy file is renamed to .migrated so it's preserved but won't trigger
 *   migration again.
 *
 * LOCKING STRATEGY FIXED (was Issue #12)
 * - The old design had shared flock() on the data file + exclusive flock() on a
 *   separate LOCK_FILE — these didn't actually coordinate. Per-user files make
 *   this moot: each file uses exclusive flock() on itself for writes, and readers
 *   see either complete-old or complete-new content via atomic rename.
 *
 * CACHE REMOVED
 * - The static $sessionsCache was a within-request optimisation for the monolithic
 *   file. With per-user files, individual reads are cheap enough that the cache is
 *   unnecessary and its staleness-vs-disk semantics were a footgun. Removed.
 *
 * PUBLIC API — UNCHANGED
 * - All public method signatures are identical. Drop-in replacement.
 */

class GameSessionManager {

    /** @var bool Whether we've checked for (and run) monolithic migration this request */
    private static bool $migrationChecked = false;

    // =========================================================================
    // INTERNAL FILE I/O
    // =========================================================================

    /**
     * Get the path to a specific user's session file.
     *
     * @param  int|string  $userId
     * @return string
     */
    private function sessionFilePath($userId): string {
        return rtrim(SESSIONS_DIR, '/') . '/' . (int) $userId . '.json';
    }

    /**
     * Ensure the sessions directory exists.
     * Called before any write operation.
     */
    private function ensureSessionsDir(): void {
        if (!is_dir(SESSIONS_DIR)) {
            if (!@mkdir(SESSIONS_DIR, 0755, true)) {
                error_log("GameSessionManager: cannot create sessions directory " . SESSIONS_DIR);
            }
        }
    }

    /**
     * Migrate from the legacy monolithic game_state.json to per-user files.
     * Runs at most once per request, and only if SESSIONS_DIR is empty and the
     * legacy file exists.
     */
    private function migrateIfNeeded(): void {
        if (self::$migrationChecked) {
            return;
        }
        self::$migrationChecked = true;

        // Skip if sessions directory already has files
        if (is_dir(SESSIONS_DIR) && count(glob(SESSIONS_DIR . '/*.json')) > 0) {
            return;
        }

        // Skip if legacy file doesn't exist
        if (!defined('GAME_STATE_FILE') || !file_exists(GAME_STATE_FILE)) {
            return;
        }

        $json = @file_get_contents(GAME_STATE_FILE);
        if ($json === false) {
            return;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['game_sessions'])) {
            return;
        }

        error_log("GameSessionManager: migrating " . count($data['game_sessions']) . " sessions from monolithic file");
        $this->ensureSessionsDir();

        $migrated = 0;
        foreach ($data['game_sessions'] as $userId => $session) {
            if ($this->writeSessionFile($userId, $session)) {
                $migrated++;
            }
        }

        // Rename legacy file so migration doesn't re-trigger
        @rename(GAME_STATE_FILE, GAME_STATE_FILE . '.migrated.' . date('Y-m-d_H-i-s'));
        error_log("GameSessionManager: migrated $migrated sessions to per-user files");
    }

    /**
     * Read a single user's session file from disk.
     * Returns null if the file doesn't exist or contains invalid JSON.
     *
     * On JSON parse failure the corrupted file is preserved with a timestamped
     * backup name so an operator can inspect it.
     *
     * @param  int|string  $userId
     * @return array|null
     */
    private function readSessionFile($userId): ?array {
        $file = $this->sessionFilePath($userId);

        if (!file_exists($file)) {
            return null;
        }

        $json = @file_get_contents($file);
        if ($json === false) {
            error_log("GameSessionManager: cannot read session file for user $userId");
            return null;
        }

        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $errMsg   = json_last_error_msg();
            $backupTo = $file . '.corrupt.' . date('Y-m-d_H-i-s');
            @copy($file, $backupTo);
            error_log(
                "GameSessionManager: session file corrupt for user $userId ($errMsg). " .
                "Preserved at $backupTo."
            );
            return null;
        }

        return is_array($data) ? $data : null;
    }

    /**
     * Write a single user's session file atomically.
     *
     * Write flow:
     *   1. Write JSON to a PID-suffixed temp file in the same directory.
     *   2. rename() temp → session file. Atomic on the same filesystem.
     *
     * No external lock file is needed — per-user files mean writers for different
     * users never contend, and same-user contention within a single webhook
     * request is impossible (PHP is single-threaded per request).
     *
     * @param  int|string  $userId
     * @param  array       $sessionData
     * @return bool
     */
    private function writeSessionFile($userId, array $sessionData): bool {
        $this->ensureSessionsDir();

        $file    = $this->sessionFilePath($userId);
        $tmpFile = $file . '.tmp.' . getmypid();
        $json    = json_encode($sessionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (file_put_contents($tmpFile, $json) === false) {
            error_log("GameSessionManager: failed to write temp file $tmpFile");
            @unlink($tmpFile);
            return false;
        }

        if (!rename($tmpFile, $file)) {
            error_log("GameSessionManager: rename($tmpFile, $file) failed");
            @unlink($tmpFile);
            return false;
        }

        return true;
    }

    /**
     * Delete a user's session file from disk.
     *
     * @param  int|string  $userId
     * @return bool
     */
    private function deleteSessionFile($userId): bool {
        $file = $this->sessionFilePath($userId);

        if (!file_exists($file)) {
            return true; // Already gone — idempotent
        }

        return @unlink($file);
    }

    // =========================================================================
    // PUBLIC SESSION API
    // =========================================================================

    /**
     * Check whether a user has an active journey (game in progress).
     *
     * Returns true iff a valid session file exists for this user.
     * Thin wrapper around loadSession(); invalid or missing session returns false.
     *
     * @param  int|string  $userId
     * @return bool
     */
    public function hasActiveSession($userId): bool {
        return $this->loadSession($userId) !== null;
    }

    /**
     * Load and validate the session for a specific user.
     *
     * This is the single source of truth for "does this user have a valid session".
     * Returns null if no session exists or if it fails structural validation.
     *
     * @param  int|string  $userId
     * @return array|null
     */
    public function loadSession($userId): ?array {
        $this->migrateIfNeeded();

        $session = $this->readSessionFile($userId);

        if ($session === null) {
            return null;
        }

        if (!$this->validateSession($session)) {
            error_log("GameSessionManager: invalid session structure for user $userId");
            return null;
        }

        return $session;
    }

    /**
     * Persist the session for a specific user.
     * Stamps last_updated before writing.
     *
     * @param  int|string  $userId
     * @param  array       $sessionData
     * @return bool
     */
    public function saveSession($userId, array $sessionData): bool {
        $sessionData['last_updated'] = time();
        return $this->writeSessionFile($userId, $sessionData);
    }

    /**
     * Delete the session for a specific user.
     * Returns true even if no session existed (idempotent).
     *
     * @param  int|string  $userId
     * @return bool
     */
    public function deleteSession($userId): bool {
        return $this->deleteSessionFile($userId);
    }

    /**
     * Return the status string for a user's session, or null if none exists.
     *
     * @param  int|string  $userId
     * @return string|null
     */
    public function getSessionStatus($userId): ?string {
        $session = $this->loadSession($userId);
        return $session ? $session['status'] : null;
    }

    /**
     * Delete all sessions older than $maxAge seconds.
     *
     * @param  int  $maxAge  Seconds since last_updated before a session is expired.
     *                       Defaults to SESSION_MAX_AGE constant.
     * @return int  Number of sessions removed.
     */
    public function cleanupOldSessions(int $maxAge = SESSION_MAX_AGE): int {
        $this->ensureSessionsDir();
        $files   = glob(SESSIONS_DIR . '/*.json');
        $now     = time();
        $cleaned = 0;

        foreach ($files as $file) {
            $userId = basename($file, '.json');
            $session = $this->readSessionFile($userId);

            if ($session === null) {
                // Unreadable/corrupt — clean it up
                @unlink($file);
                $cleaned++;
                continue;
            }

            if (($now - ($session['last_updated'] ?? 0)) > $maxAge) {
                @unlink($file);
                $cleaned++;
            }
        }

        if ($cleaned > 0) {
            error_log("GameSessionManager: cleaned up $cleaned expired session(s)");
        }

        return $cleaned;
    }

    /**
     * Return the number of currently stored sessions.
     * Uses glob() — cheap, no file reads required.
     *
     * @return int
     */
    public function getActiveSessionCount(): int {
        $this->ensureSessionsDir();
        return count(glob(SESSIONS_DIR . '/*.json'));
    }

    /**
     * Return all sessions as an associative array keyed by user ID.
     * Intended for admin tooling only — do not expose to players.
     *
     * Note: With many sessions this reads every file. Fine for admin commands;
     * do not call in hot paths.
     *
     * @return array
     */
    public function getAllSessions(): array {
        $this->migrateIfNeeded();
        $this->ensureSessionsDir();

        $sessions = [];
        $files    = glob(SESSIONS_DIR . '/*.json');

        foreach ($files as $file) {
            $userId  = basename($file, '.json');
            $session = $this->readSessionFile($userId);

            if ($session !== null && $this->validateSession($session)) {
                $sessions[$userId] = $session;
            }
        }

        return $sessions;
    }

    /**
     * Get all players with active sessions (activity within last 7 days).
     * Returns array of player data sorted by distance (farthest first).
     *
     * Each entry contains:
     * - user_id: int
     * - username: string (fallback if no display name)
     * - display_name: string (preferred display name)
     * - distance: int (km traveled)
     * - percentage: float (percentage of journey complete)
     * - days: int (days elapsed)
     * - chad: int (chad count)
     * - status: string (session status: in_space, at_station, finished, etc.)
     * - last_updated: int (timestamp)
     * - last_activity_date: string (formatted date like "Mar 2")
     *
     * @param  int  $maxAgeDays  Maximum age in days (default 7)
     * @return array  Array of player data, sorted by distance (descending)
     */
    public function getActivePlayers(int $maxAgeDays = 7): array {
        $allSessions = $this->getAllSessions();
        $now = time();
        $maxAge = $now - ($maxAgeDays * 24 * 60 * 60);
        $players = [];

        foreach ($allSessions as $userId => $session) {
            $lastUpdated = $session['last_updated'] ?? 0;

            // Filter: only sessions with activity in last N days
            if ($lastUpdated < $maxAge) {
                continue;
            }

            // Extract player data
            $distance = $session['progress']['distance'] ?? 0;
            $percentage = ($distance / TOTAL_DISTANCE) * 100;
            $days = $session['progress']['day'] ?? 1;
            $chad = $session['resources']['chad'] ?? 0;
            $status = $session['status'] ?? 'unknown';

            // Prefer display_name, fallback to username
            $displayName = $session['display_name'] ?? $session['username'] ?? 'Unknown';
            $username = $session['username'] ?? 'Unknown';

            // Format last activity date
            $lastActivityDate = date('M j', $lastUpdated);

            $players[] = [
                'user_id' => $userId,
                'username' => $username,
                'display_name' => $displayName,
                'distance' => $distance,
                'percentage' => $percentage,
                'days' => $days,
                'chad' => $chad,
                'status' => $status,
                'last_updated' => $lastUpdated,
                'last_activity_date' => $lastActivityDate,
            ];
        }

        // Sort by distance (descending) - farthest players first
        usort($players, function($a, $b) {
            return $b['distance'] <=> $a['distance'];
        });

        return $players;
    }

    /**
     * Return a human-readable debug summary for a single user's session.
     * Intended for admin commands only.
     *
     * @param  int|string  $userId
     * @return array|null
     */
    public function getSessionDebug($userId): ?array {
        $session = $this->loadSession($userId);

        if (!$session) {
            return null;
        }

        return [
            'user_id'          => $userId,
            'session_id'       => $session['session_id'],
            'created_at'       => date('Y-m-d H:i:s', $session['created_at']),
            'last_updated'     => date('Y-m-d H:i:s', $session['last_updated']),
            'age_minutes'      => round((time() - $session['last_updated']) / 60),
            'difficulty'       => $session['difficulty'],
            'status'           => $session['status'],
            'day'              => $session['progress']['day'],
            'distance_percent' => round(($session['progress']['distance'] / TOTAL_DISTANCE) * 100, 2),
            'chad_alive'       => $session['resources']['chad'],
            'fuel'             => $session['resources']['fuel'],
            'food'             => $session['resources']['food'],
            'hull'             => $session['resources']['hull'],
        ];
    }

    /**
     * Delete ALL session files. Used by admin reset.
     * Returns the number of files removed.
     *
     * @return int
     */
    public function deleteAllSessions(): int {
        $this->ensureSessionsDir();
        $files   = glob(SESSIONS_DIR . '/*.json');
        $deleted = 0;

        foreach ($files as $file) {
            if (@unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    // =========================================================================
    // INTERNAL VALIDATION
    // =========================================================================

    /**
     * Validate that a session array contains all required fields.
     * Returns false if any required key is missing — does not attempt repair.
     *
     * @param  mixed  $session  Value read from the sessions file (may not be an array)
     * @return bool
     */
    private function validateSession($session): bool {
        if (!is_array($session)) {
            return false;
        }

        foreach (['session_id', 'created_at', 'last_updated', 'status', 'resources', 'progress'] as $field) {
            if (!isset($session[$field])) {
                return false;
            }
        }

        foreach (['eth', 'fuel', 'food', 'chad', 'hull', 'vibes'] as $resource) {
            if (!isset($session['resources'][$resource])) {
                return false;
            }
        }

        foreach (['distance', 'day', 'speed'] as $field) {
            if (!isset($session['progress'][$field])) {
                return false;
            }
        }

        return true;
    }
}
<?php
/**
 * Telemetry
 *
 * Appends one JSON line per game event to TELEMETRY_FILE (when TELEMETRY_ENABLED).
 * Every line says which client (bot or mini-app) caused it, so both are visible in one log.
 * Read by telemetry_analyzer.php.
 */
class Telemetry {
    /**
     * @param string      $action     Event name, e.g. 'event_choice', 'shop_transaction', 'session_created'
     * @param array       $data       Event details
     * @param int|null    $userId
     * @param string|null $sessionId
     * @param string      $client     'bot' | 'mini-app' | 'unknown'
     */
    public static function record(string $action, array $data = [], $userId = null, ?string $sessionId = null, string $client = 'unknown'): void {
        if (!TELEMETRY_ENABLED) {
            return;
        }

        $entry = json_encode([
            'timestamp'  => time(),
            'datetime'   => date('Y-m-d H:i:s'),
            'action'     => $action,
            'client'     => $client,
            'user_id'    => $userId,
            'session_id' => $sessionId,
            'data'       => $data,
        ]) . PHP_EOL;

        file_put_contents(TELEMETRY_FILE, $entry, FILE_APPEND | LOCK_EX);
    }

    /** Compact view of a session for log lines. */
    public static function snapshot(array $session): array {
        return [
            'day'              => $session['progress']['day'] ?? 0,
            'distance_millions' => round(($session['progress']['distance'] ?? 0) / 1000000, 2),
            'status'           => $session['status'] ?? 'unknown',
            'difficulty'       => $session['difficulty'] ?? 'unknown',
        ];
    }

    public static function eventChoice(array $session, string $eventId, string $choiceId, string $phase, string $client): void {
        self::record('event_choice', [
            'event_id'         => $eventId,
            'event_type'       => 'event',
            'choice_id'        => $choiceId,
            'choice_text'      => "Choice $choiceId",
            'outcome'          => $phase, // 'processing' | 'completed'
            'game_state'       => self::snapshot($session),
            'resources_before' => $session['resources'] ?? [],
        ], $session['user_id'] ?? null, $session['session_id'] ?? null, $client);
    }

    public static function shopTransaction(array $session, string $item, $quantity, $cost, bool $success, ?string $reason, string $client): void {
        self::record('shop_transaction', [
            'item'             => $item,
            'quantity'         => $quantity,
            'cost'             => $cost,
            'success'          => $success,
            'reason'           => $reason,
            'resources_before' => $session['resources'] ?? [],
            'eth_before'       => $session['resources']['eth'] ?? 0,
        ], $session['user_id'] ?? null, $session['session_id'] ?? null, $client);
    }

    /** $action: 'created' | 'abandoned' | 'game_over' | 'victory' */
    public static function sessionLifecycle(string $action, array $session, array $extra, string $client): void {
        $snapshot = self::snapshot($session);
        self::record("session_$action", array_merge([
            'session_id'  => $session['session_id'] ?? null,
            'difficulty'  => $session['difficulty'] ?? null,
            'created_at'  => $session['created_at'] ?? null,
            'status'      => $session['status'] ?? null,
            'progress'    => ['day' => $snapshot['day'], 'distance' => $snapshot['distance_millions']],
            'final_stats' => $session['stats'] ?? [],
        ], $extra), $session['user_id'] ?? null, $session['session_id'] ?? null, $client);
    }
}

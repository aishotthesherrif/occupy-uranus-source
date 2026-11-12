<?php
ini_set('memory_limit', '-1');
/**
 * Compare two golden-run dumps. Usage: php tools/golden_compare.php before.json after.json
 * Compares the saved game after every move. Outcome codes are intentionally NOT compared
 * (they are renamed by the refactor); only game state is.
 */
$a = json_decode(file_get_contents($argv[1] ?? ''), true);
$b = json_decode(file_get_contents($argv[2] ?? ''), true);
if (!$a || !$b) { fwrite(STDERR, "Usage: php tools/golden_compare.php before.json after.json\n"); exit(2); }
$diffs = 0;
foreach ($a as $game => $logA) {
    $logB = $b[$game] ?? [];
    if (count($logA) !== count($logB)) { echo "$game: length " . count($logA) . " vs " . count($logB) . "\n"; $diffs++; }
    foreach ($logA as $i => $entryA) {
        $entryB = $logB[$i] ?? null;
        if ($entryB === null) { break; }
        if (($entryA['session'] ?? null) !== ($entryB['session'] ?? null) || ($entryA['exception'] ?? null) !== ($entryB['exception'] ?? null)) {
            echo "$game move $i ({$entryA['move']}): state differs\n"; $diffs++; break;
        }
    }
}
echo $diffs === 0 ? "IDENTICAL: all games match move for move.\n" : "$diffs difference(s).\n";
exit($diffs === 0 ? 0 : 1);

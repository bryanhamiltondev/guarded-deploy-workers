<?php
/**
 * Example: bump a versioned asset ref when a SECOND worker may already have bumped it.
 *
 * WHY THIS RECIPE EXISTS
 * Two one-shot workers patch the same file the same day and both bump the same
 * asset tag (player.js?v=3). Worker A ran -> now v4. Worker B, written against
 * v3, aborts on its needle - or worse, stomps v4 back.
 *
 * Order-independent pattern: accept BOTH pre-states and map them to ONE
 * target, then report which path was taken. The feature patch above the sweep
 * is a normal guarded apply; the sweep is the reusable part.
 *
 * Production lesson (Nov 2026): the metadata worker bumped bh-eq.js v3->v4;
 * the album-art worker accepted v3 OR v4, both mapping to v5. Shipped in
 * either order, no re-run needed.
 */

require __DIR__ . '/../src/GuardedWorker.php';

$worker = new GuardedWorker('CHANGE-ME');
$worker->authorize();

// ... your normal guarded feature patch on assets/js/player.js goes here ...

// Version sweep: two valid pre-states, one target. Never match the worker itself.
$bumped = array();
$candidates = array_merge(
    glob(__DIR__ . '/*.html'),
    glob(__DIR__ . '/projects/*/index.html')
);
foreach ($candidates as $pf) {
    if (realpath($pf) === realpath(__FILE__)) { continue; }
    $ps = file_get_contents($pf);
    if (strpos($ps, 'player.js?v=4') !== false) {
        file_put_contents($pf, str_replace('player.js?v=4', 'player.js?v=5', $ps));
        $bumped[] = basename($pf) . ' (v4)';
    } elseif (strpos($ps, 'player.js?v=3') !== false) {
        file_put_contents($pf, str_replace('player.js?v=3', 'player.js?v=5', $ps));
        $bumped[] = basename($pf) . ' (v3)';
    }
}
echo 'version bump -> v5 in ' . count($bumped) . ' file(s): ' . implode(', ', $bumped) . "\n";

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
 * Production lessons (Nov 2026):
 * 1. The metadata worker bumped bh-eq.js v3->v4; the album-art worker accepted
 *    v3 OR v4, both mapping to v5. Shipped in either order, no re-run needed.
 * 2. THE SWEEP MUST COVER WHERE THE REF ACTUALLY LIVES, NOT WHERE YOU ASSUME.
 *    Two same-day workers swept only *.html - but the asset's loader ref sat
 *    inside another JS file (rack.js loading bh-eq.js). Result: patched code
 *    shipped behind a stale ?v=3 ref and cached browsers kept the old player
 *    forever. Rule: grep the LIVE served bytes to locate every reference to
 *    the asset before writing the bump, and include .js (and any other
 *    template type) in the candidate glob when refs can be nested in scripts.
 */

require __DIR__ . '/../src/GuardedWorker.php';

$worker = new GuardedWorker('CHANGE-ME');
$worker->authorize();

// ... your normal guarded feature patch on assets/js/player.js goes here ...

// Version sweep: two valid pre-states, one target. Never match the worker itself.
// Candidates cover every file TYPE that can hold a ref: HTML templates AND JS
// (a script can lazy-load another script with a versioned URL).
$bumped = array();
$candidates = array_merge(
    glob(__DIR__ . '/*.html'),
    glob(__DIR__ . '/projects/*/index.html'),
    glob(__DIR__ . '/assets/js/*.js')
);
foreach ($candidates as $pf) {
    if (realpath($pf) === realpath(__FILE__)) { continue; }
    $ps = file_get_contents($pf);
    $changed = false;
    if (strpos($ps, 'player.js?v=4') !== false) {
        $ps = str_replace('player.js?v=4', 'player.js?v=5', $ps);
        $changed = true;
        $tag = 'v4';
    } elseif (strpos($ps, 'player.js?v=3') !== false) {
        $ps = str_replace('player.js?v=3', 'player.js?v=5', $ps);
        $changed = true;
        $tag = 'v3';
    }
    if ($changed) {
        file_put_contents($pf, $ps);
        $bumped[] = basename($pf) . ' (' . $tag . ')';
    }
}
echo 'version bump -> v5 in ' . count($bumped) . ' file(s): ' . implode(', ', $bumped) . "\n";

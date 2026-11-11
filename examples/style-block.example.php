<?php
/**
 * Example: replace an inline <style id="..."> block inside a big HTML page.
 *
 * WHY THIS RECIPE EXISTS
 * Byte-exact needles are perfect for one-line swaps and terrible for inline
 * CSS blocks: the block is long, whitespace-fragile, and has usually been
 * reformatted by a minifier at least once. One trailing space you never saw
 * and the needle never matches.
 *
 * The reliable pattern is marker-to-close:
 *   1. guard() on the opening tag (must appear exactly once)
 *   2. find the matching close tag with strpos()
 *   3. splice out the whole block
 *   4. hand apply() ONE fully-known needle/replacement pair, so the write
 *      stays byte-exact and self-verifying
 *
 * Production lesson that motivated this recipe (Nov 2026): a byte needle
 * across a 40-line inline block failed to match over a single invisible
 * difference. Marker-to-close matched first try.
 */

require __DIR__ . '/../src/GuardedWorker.php';

$worker = new GuardedWorker('CHANGE-ME');
$worker->authorize();                    // 404s unless ?t=CHANGE-ME

$page   = __DIR__ . '/index.html';
$src    = $worker->read($page);
$marker = 'styleblock-v1';

if (strpos($src, $marker) !== false) { echo 'ALREADY PATCHED'; exit; }

// 1. Guard: exactly one block with this id.
$openTag = '<style id="hero-theme">';
$worker->guard($src, array($openTag => 'style block'));

// 2. Find the whole block, opening tag through closing tag.
$start = strpos($src, $openTag);
$close = strpos($src, '</style>', $start);
if ($close === false) { echo 'NO CLOSE TAG - ABORT'; exit; }
$close   += strlen('</style>');
$oldBlock = substr($src, $start, $close - $start);

// 3. Build the replacement. Carry the marker INSIDE the new block so the
//    marker check in apply() proves THIS version landed.
$newBlock = $openTag . "\n"
    . "  /* {$marker} */\n"
    . "  .hero-title { color: #22d3ee; letter-spacing: .02em; }\n"
    . "</style>";

$backup = $worker->backup($page, $marker);
$worker->apply(
    $page,
    $backup,
    $src,
    array($oldBlock => $newBlock),   // needle is the block we actually read
    array($marker)
);

$worker->done(__FILE__, $backup, 'OK: hero-theme block replaced');

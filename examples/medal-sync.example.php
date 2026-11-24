<?php
/**
 * EXAMPLE: medal-sync - updating a JS count-up widget's target safely.
 *
 * Extracted from a live production deploy (Nov 2026). The scenario:
 * a medal strip renders three count-up badges. Each badge is driven by
 * data-to (the JS animation target) but still contains static inner text
 * (the no-JS / pre-animation fallback). Over several deploys they drifted:
 *
 *   <span class="medal bronze" data-to="50">49</span>
 *
 * A previous patch had bumped data-to but left the stale inner text. A
 * naive patch that only touches data-to would ship a badge animating
 * 49 -> 51 instead of 50 -> 51 - wrong for every visitor for a frame,
 * wrong forever for no-JS visitors and crawlers.
 *
 * LESSON: data-to and its inner text are ONE logical unit. Patch both,
 * with ONE needle, and treat the old value as residue to verify away.
 */

$TOKEN = 'example-token';            // alphanumeric only (punctuation tokens
                                     // have bounced on some hosts - see docs)
$NEEDLES = array(
    // old -> new, name (for the abort log)
    array('data-to="50">49<', 'data-to="51">51<', 'bronze count-up unit'),
    array('aria-label="50 bronze"', 'aria-label="51 bronze"', 'a11y label'),
    array('title="50 bronze medals"', 'title="51 bronze medals"', 'tooltip'),
);

$src = file_get_contents(__DIR__ . '/index.html');
if ($src === false) { exit("ABORT: source unreadable\n"); }

// Guard: every needle must match EXACTLY once before anything is written.
foreach ($NEEDLES as $i => $n) {
    $c = substr_count($src, $n[0]);
    if ($c !== 1) {
        exit("ABORT: needle " . ($i + 1) . " ({$n[2]}) count=$c (need exact-1)\n");
    }
}

$out = $src;
foreach ($NEEDLES as $n) {
    $out = str_replace($n[0], $n[1], $out);
}

// Verify: the OLD value must be GONE (count 0), not just the new one present.
// 'new value exists' alone is weak - a partial patch can satisfy it.
if (strpos($out, '50 bronze') !== false) {
    exit("ABORT: residue '50 bronze' remains after patch\n");
}
foreach ($NEEDLES as $n) {
    if (strpos($out, $n[1]) === false) {
        exit("ABORT: verify failed for {$n[2]}\n");
    }
}

// Backup, write, re-read, verify again - then self-delete.
copy(__DIR__ . '/index.html', __DIR__ . '/index.html.bak');
file_put_contents(__DIR__ . '/index.html', $out, LOCK_EX);
$chk = file_get_contents(__DIR__ . '/index.html');
if (strpos($chk, '50 bronze') !== false) {
    exit("ABORT: post-write verify failed (backup restored manually)\n");
}
echo "PATCHED OK\n";
@unlink(__FILE__);

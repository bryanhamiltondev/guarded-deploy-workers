<?php
/**
 * Example: inject Media Session metadata into an existing preview player.
 *
 * WHY THIS RECIPE EXISTS
 * When a page plays audio without declaring MediaMetadata, macOS/iOS show the
 * PAGE TITLE in the system media popover. One guarded inject right after the
 * player assigns audio.src fixes it: title, artist, artwork upgraded from
 * 100x100 to 600x600, all fail-safe (try/catch, API-gated).
 *
 * Production lessons (Nov 2026): the same five lines shipped to two sites in
 * one day - both players played iTunes previews and both popped over the page
 * title. And needles copied from the browser-rendered page never matched the
 * disk file: the browser decodes &middot; entities, so raw bytes differ.
 * Always cut needles from the raw served bytes.
 */

require __DIR__ . '/../src/GuardedWorker.php';

$worker = new GuardedWorker('CHANGE-ME');
$worker->authorize();

$player = __DIR__ . '/assets/js/player.js';
$src    = $worker->read($player);
$marker = 'mediasession-v1';

if (strpos($src, $marker) !== false) { echo 'ALREADY PATCHED'; exit; }

// Guard: the play-start line must exist exactly once.
$needle = 'audio.src = track.previewUrl;';
$worker->guard($src, array($needle => 'audio.src assignment'));

$inject = $needle . "\n"
    . "if ('mediaSession' in navigator) {\n"
    . "    try {\n"
    . "        navigator.mediaSession.metadata = new MediaMetadata({\n"
    . "            title: track.trackName || 'Preview',\n"
    . "            artist: track.artistName || '',\n"
    . "            album: location.hostname,\n"
    . "            artwork: track.artworkUrl100\n"
    . "                ? [{ src: String(track.artworkUrl100).replace('100x100', '600x600'),\n"
    . "                     sizes: '600x600', type: 'image/jpeg' }]\n"
    . "                : []\n"
    . "        });\n"
    . "    } catch (eMS) {}\n"
    . "} /* {$marker} */";

// Delta-based balance check: braces in == braces out.
if (substr_count($inject, '{') !== substr_count($inject, '}')) {
    echo 'BRACE DELTA - ABORT'; exit;
}

$backup = $worker->backup($player, $marker);
$worker->apply($player, $backup, $src, array($needle => $inject), array($marker));

$worker->done(__FILE__, $backup, 'OK: Media Session metadata wired (add a matching clear on the stop path)');

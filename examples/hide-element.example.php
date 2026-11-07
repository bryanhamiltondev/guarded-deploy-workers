<?php
/**
 * EXAMPLE: a one-shot worker that hides .legacy-banner on the example.com homepage.
 *
 * Deploy: upload GuardedWorker.php and this file to the web root, rename this file
 * to fix-banner.php, visit https://example.com/fix-banner.php?t=CHANGE-ME once.
 * The worker patches index.html, reports, cleans up, and deletes itself.
 * Re-running is impossible (the file is gone); a patched file aborts via the marker check.
 */

require __DIR__ . '/../src/GuardedWorker.php';

$worker = new GuardedWorker('CHANGE-ME');
$worker->authorize();

$self      = __FILE__;
$target    = __DIR__ . '/index.html';
$tag       = 'fixbanner1';
$marker    = 'djc-fixbanner1';

$src = $worker->read($target);
if (strpos($src, $marker) !== false) {
    echo 'ALREADY PATCHED - ABORT (nothing to do)';
    exit;
}

$needle = '<div class="legacy-banner">';
$worker->guard($src, array($needle));

$replacement = '<div class="legacy-banner" style="display:none" data-' . $marker . '>';
$backup = $worker->backup($target, $tag);
$worker->apply($target, $backup, $src, array(
    $needle => $replacement,
), array($marker));

$worker->done($self, $backup, "OK fixbanner1: .legacy-banner hidden\nbytes: " . strlen($src) . ' -> ' . strlen(@file_get_contents($target)));

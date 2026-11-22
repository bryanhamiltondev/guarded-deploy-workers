<?php
/**
 * version-fingerprint.example.php
 *
 * DRY-by-default gate plus a build fingerprint on every response.
 *
 * The problem: "disk is new, behaviour is old" has two causes on PHP -
 * stale HTML/asset caches (fix with a cache-busting query param) and
 * opcache serving old bytecode (a query param cannot fix that, because
 * opcache is not keyed on the URL). A fingerprint line that only exists
 * in the current revision catches both: if the served output does not
 * carry it, you are talking to a stale copy, not to this file.
 *
 * And when a stale copy will not die no matter what: re-upload under a
 * NEW filename with a NEW token. Old copies 404 on a token that never
 * existed in them, so the version proves itself instead of being guessed.
 *
 * Usage:
 *   worker.php?t=CHANGE-ME           -> DRY run, prints fingerprint + plan
 *   worker.php?t=CHANGE-ME&go=go     -> writes, verifies, self-deletes
 */

require __DIR__ . '/../src/GuardedWorker.php';

define('BUILD', 'v2-2026-11-21');   // bump on EVERY edit; this is the fingerprint
define('TOKEN', 'CHANGE-ME');

$worker = new GuardedWorker(TOKEN);
$worker->authorize();

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$GO = (isset($_GET['go']) && $_GET['go'] === 'go');

echo 'worker: ' . basename(__FILE__) . ' build ' . BUILD . PHP_EOL;
echo 'mode: ' . ($GO ? 'GO (writes enabled)' : 'DRY (nothing written)') . PHP_EOL;

$target = __DIR__ . '/index.html';
$src = $worker->read($target);

// Fingerprint check against the target, not just the response:
// prove the runtime is executing the revision you think it is.
if (strpos($src, 'data-build="' . BUILD . '"') !== false) {
    echo 'target: already carries build ' . BUILD . ' - nothing to do' . PHP_EOL;
    exit;
}

$needle = '<div class="hero">';
$marker = 'data-build="' . BUILD . '"';
$worker->guard($src, array($needle => 'replacement'));

$plan = array(
    'target' => basename($target),
    'bytes'  => (string) strlen($src),
    'needle' => $needle . ' (1 occurrence confirmed)',
    'patch'  => 'inject ' . $marker,
    'backup' => basename($target) . '.bak',
);
foreach ($plan as $k => $v) {
    echo '  ' . $k . ': ' . $v . PHP_EOL;
}

if (!$GO) {
    echo 'DRY OK. Re-run with &go=go to write.' . PHP_EOL;
    exit;
}

$backup = $worker->backup($target, 'fp');
$worker->apply($target, $backup, $src, array(
    $needle => '<div class="hero" ' . $marker . '>',
), array($marker));

$worker->done(__FILE__, $backup, 'OK: ' . BUILD . ' live; re-request the page and confirm the marker before trusting the report.');

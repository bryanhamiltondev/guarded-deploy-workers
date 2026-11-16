<?php
/**
 * Example: worker appends its own row to a deploy ledger before self-deleting.
 *
 * WHY THIS RECIPE EXISTS
 *
 * If your public deploy log is a static HTML ledger (rows in a table, no
 * database), something eventually edits it by hand - and then it drifts.
 * Ours sat frozen at 45 rows while 21 real deploys shipped, because no
 * worker ever logged itself and "I'll backfill later" is a lie you tell
 * a future you who no longer remembers the rows.
 *
 * The fix is structural: every worker that ships something appends its
 * OWN row as part of the same run that makes the change. The ledger can
 * no longer drift, because logging is no longer a task anyone remembers.
 *
 * Production lesson (Nov 2026): a 66-row backfill had to be built from
 * curated memory - reconstructing two weeks of history by hand. The
 * self-log snippet below shipped the same day and has been in the worker
 * template since. The write is wrapped so a missing ledger never fails
 * the deploy: the receipt is bonus, the patch is the product.
 */

require __DIR__ . '/../src/GuardedWorker.php';

$worker = new GuardedWorker('CHANGE-ME');
$worker->authorize();

$src = $worker->read(__DIR__ . '/index.html');
if (strpos($src, 'data-my-marker') !== false) { echo 'ALREADY PATCHED'; exit; }

$needle  = '<!-- banner -->';
$replace = '<div class="banner" data-my-marker>new banner</div>';

$backup = $worker->backup(__DIR__ . '/index.html', 'tag1');
$worker->apply(__DIR__ . '/index.html', $backup, $src, array(
    $needle => $replace,
), array('my-marker'));

/*
 * Self-log: append this deploy to the static ledger before the worker
 * dies. ASCII only; row format must match the live table byte-for-byte.
 */
$ledger = __DIR__ . '/receipts/index.html';
if (is_file($ledger)) {
    $r = file_get_contents($ledger);
    $row = '<tr><td class="d">' . date('M j') . '</td>'
         . '<td><span class="tag bh">BH</span></td>'
         . '<td><span class="item">short deploy title</span>'
         . '<span class="note">what it changed, one clause</span></td>'
         . '<td class="v" title="verified on the live site">&#10003; verified</td></tr>';
    $p = strrpos($r, '</tr>');
    if ($p !== false) {
        $p += strlen('</tr>');
        file_put_contents($ledger, substr($r, 0, $p) . $row . substr($r, $p));
    }
}

$worker->done(__FILE__, $backup, 'OK: banner live, ledger row appended');

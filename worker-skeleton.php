<?php
/**
 * WORKER SKELETON - the current production pattern (rules 19-22 applied).
 *
 * Copy this file, rename it, fill the NEEDLES and ROW, rehearse on real
 * live bytes, then upload to public_html root.
 *
 * Usage:
 *   DRY:  GET  worker.php?t=<token>            (reads, reports, writes nothing)
 *   GO:   POST worker.php  body: token=<token> (patches, backs up, self-deletes)
 *
 * See docs/checklist.md for the rules this encodes.
 */

header('Cache-Control: no-store');                 // rule 22: nothing caches this
$TOKEN = 'exampleToken1';                          // rule 21: alphanumeric only

$token = $_POST['token'] ?? $_GET['t'] ?? '';
if ($token !== $TOKEN) {
    http_response_code(404);
    header('Content-Type: text/plain');
    exit('Not Found');                             // identical 404 for no token + bad token
}

$go = ($_SERVER['REQUEST_METHOD'] === 'POST');     // rule 22: GO is POST-only

// ---- 1. NEEDLES: old -> new, name (for the abort log). Every needle must
// ----    match EXACTLY once before anything is written.
$NEEDLES = array(
    array('old exact substring', 'new exact substring', 'surface name'),
);

// ---- 2. LOAD
$idx = __DIR__ . '/index.html';
$src = is_file($idx) ? file_get_contents($idx) : false;
if ($src === false) { exit("ABORT: index.html not readable\n"); }

// ---- 3. GUARD: exact-1 for every needle
foreach ($NEEDLES as $i => $n) {
    $c = substr_count($src, $n[0]);
    if ($c !== 1) {
        exit("ABORT: needle " . ($i + 1) . " ({$n[2]}) count=$c (need exact-1)\n");
    }
}

// ---- 4. PATCH + VERIFY: old value GONE (count 0), not just new value present
$out = $src;
foreach ($NEEDLES as $n) { $out = str_replace($n[0], $n[1], $out); }
foreach ($NEEDLES as $n) {
    $oldCore = preg_replace('/\s+/', ' ', $n[0]);
    if (strpos($out, $n[0]) !== false) { exit("ABORT: residue of needle {$n[2]}\n"); }
    if (strpos($out, $n[1]) === false) { exit("ABORT: verify failed {$n[2]}\n"); }
}

// ---- 5. DRY exits here (GET): report, write nothing
if (!$go) {
    echo "DRY OK - " . count($NEEDLES) . " needles exact-1, patch valid. POST to execute.\n";
    exit(0);
}

// ---- 6. BACKUP, WRITE, RE-VERIFY, SELF-DELETE
copy($idx, $idx . '.bak');
if (file_put_contents($idx, $out, LOCK_EX) === false) { exit("ABORT: write failed\n"); }
$chk = file_get_contents($idx);
if (strpos($chk, $NEEDLES[0][1]) === false) { exit("ABORT: post-write verify failed\n"); }
echo "PATCHED OK\n";
@unlink(__FILE__);

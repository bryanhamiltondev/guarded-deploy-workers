<?php
/**
 * static-fallback-sync.example.php
 *
 * PATTERN: a page element that recounts itself at runtime (fetch -> JS) still
 * ships with static fallback text in the HTML (no-JS first paint, tooltips,
 * aria-labels). The statics drift invisibly - the runtime count masks them
 * until the script fails or the markup is read raw. This worker re-syncs
 * every static surface to the runtime's source of truth, in one pass, across
 * every HTML file that carries the element.
 *
 * Real deploy: medal counts whose live proxy said 1/9/44 while the HTML
 * still painted 43 bronze, 8 silver, and an aria-label two revisions older.
 *
 * Guards specific to this pattern:
 *  - exact-once per needle, per file (a count-up span must appear once)
 *  - an already-correct file is left untouched (idempotent re-runs)
 *  - "MULTI" aborts the file without writing it (other files still get tried)
 */

if (!isset($_GET['t']) || !hash_equals('CHANGE-ME', (string)$_GET['t'])) { http_response_code(404); exit; }
header('Content-Type: text/plain; charset=utf-8');
$GO = (isset($_GET['go']) && $_GET['go'] === 'go');

/** @return string|false patched source, false when nothing to change or a guard failed */
function patchStatics(string $s, string &$report) {
    $report = '';
    $ok = true;

    // 1. tooltip: full attribute is unique per element
    $s2 = preg_replace('/title="43 bronze medals"/', 'title="44 bronze medals"', $s, 1, $c1);
    if ($c1 === 1) { $report .= 'tooltip:1 '; } elseif ($c1 > 1) { $report .= 'tooltip:MULTI '; $ok = false; } else { $report .= 'tooltip:0 '; }

    // 2-3. count-up spans: needle includes the class so data-to="8" elsewhere on
    // the page can never match; verify old string appears exactly once, then
    // plain str_replace (preg backreference math on digits is where typos live)
    $pairs = array(
        array('bronze', 'cl-num-b" data-to="43">43<', 'cl-num-b" data-to="44">44<'),
        array('silver', 'cl-num-s" data-to="8">8<',   'cl-num-s" data-to="9">9<'),
    );
    foreach ($pairs as $p) {
        $c = substr_count($s2, $p[1]);
        if ($c === 1) { $s2 = str_replace($p[1], $p[2], $s2); $report .= $p[0] . ':1 '; }
        elseif ($c > 1) { $report .= $p[0] . ':MULTI '; $ok = false; }
        else { $report .= $p[0] . ':0 '; }
    }

    // 4. free-text attribute: match the whole attribute, not remembered values -
    // stale values drift and a hand-typed old string never matches
    $s2 = preg_replace('/aria-label="Coder Legion medals: [^"]*"/',
        'aria-label="Coder Legion medals: 1 gold, 9 silver, 44 bronze"', $s2, 1, $c4);
    if ($c4 === 1) { $report .= 'aria:1 '; } elseif ($c4 > 1) { $report .= 'aria:MULTI '; $ok = false; } else { $report .= 'aria:0 '; }

    return ($s2 !== $s && $ok) ? $s2 : false;
}

$files = array();
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->isFile() && strtolower($f->getExtension()) === 'html') { $files[] = $f->getPathname(); }
}
$done = 0;
foreach ($files as $f) {
    if (strpos($f, '.bak') !== false) { continue; }
    $s = @file_get_contents($f);
    if ($s === false || strpos($s, 'cl-num-b') === false) { continue; }  // marker gates the scan
    $rep = ''; $r = patchStatics($s, $rep);
    if ($r === false) { echo basename($f) . ": skip ($rep)\n"; continue; }
    if (!$GO) { echo basename($f) . ": would patch ($rep)\n"; continue; }
    copy($f, $f . '.bak-fallbacksync.bak');
    if (file_put_contents($f, $r, LOCK_EX) !== strlen($r) || file_get_contents($f) !== $r) {
        copy($f . '.bak-fallbacksync.bak', $f); echo basename($f) . ": WRITE FAIL; restored\n"; continue;
    }
    $done++; echo basename($f) . ": patched ($rep)\n";
}
echo $done . " file(s) patched" . ($GO ? ".\n" : " if you add &go=go.\n");
if ($GO) { @unlink(__FILE__); }

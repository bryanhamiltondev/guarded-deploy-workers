<?php
/**
 * audio-singleton.example.php
 *
 * PATTERN: two JS engines on one page each own an <audio> and play previews
 * (a hover player and a card player). Neither knows the other exists, so two
 * tracks can overlap - and a fetch that resolves after the pointer leaves
 * starts a track "for nobody". This worker patches BOTH engines with a
 * window-level bridge so any engine can stop any other before it starts.
 *
 * Real deploy: ticker hover previews on a calendar site. Bug: roll over an
 * artist, leave fast, the track still played; hover a second artist and two
 * tracks ran over each other.
 *
 * Guards specific to this pattern:
 *  - the dead-guard autopsy: app-main referenced window.stopPreview and
 *    window.djcStopCardPreview but never ASSIGNED them - grep for the
 *    assignment, not just the reference, before trusting a cross-file call
 *  - every needle must match exactly once in its file; files that are already
 *    patched abort before any write
 *  - expose the stopper from INSIDE the other engine's own stop function, so
 *    it always sees that engine's current state
 */

if (!isset($_GET['t']) || !hash_equals('CHANGE-ME', (string)$_GET['t'])) { http_response_code(404); exit; }
header('Content-Type: text/plain; charset=utf-8');
$GO = (isset($_GET['go']) && $_GET['go'] === 'go');
$SHP = __DIR__ . '/search-hover-player.js';   // engine A: hover player
$AM  = __DIR__ . '/app-main.js';              // engine B: card player

function applyFile(string $src, array $pairs, string &$msg) {
    foreach ($pairs as $p) {
        $n = substr_count($src, $p[0]);
        if ($n !== 1) { $msg = "needle not unique ($n x): " . substr($p[0], 0, 50); return false; }
    }
    foreach ($pairs as $p) { $src = str_replace($p[0], $p[1], $src); }
    return $src;
}

// --- engine A (hover player) ---
$s = @file_get_contents($SHP); if ($s === false) { echo "READ FAIL shp\n"; exit; }
if (strpos($s, 'audio-singleton-v1') !== false) { echo "shp already patched\n"; exit; }
$pairs = array(
    // stopAudio() also stops the other engine
    array("    function stopAudio() {\n        if (audio) {",
          "    function stopAudio() {\n        try { if (typeof window.djcStopCardEngineAudio === 'function') { window.djcStopCardEngineAudio(); } } catch (e) {}\n        if (audio) {"),
    // register the live audio on the bridge
    array("        audio = new Audio(track.previewUrl);",
          "        audio = new Audio(track.previewUrl);\n        window.__djcShpAudio = audio;"),
    // orphan guard: the iTunes fetch resolving after mouseout must NOT play -
    // the resolve requires the hover target to still be active
    array("if (token !== fetchToken || audio) { return; }",
          "if (token !== fetchToken || audio || !activeItem) { return; }"),
);
$m = ''; $out = applyFile($s, $pairs, $m);
if ($out === false) { echo "shp ABORT: $m\n"; exit; }

// --- engine B (card player) ---
$a = @file_get_contents($AM); if ($a === false) { echo "READ FAIL app-main\n"; exit; }
if (strpos($a, 'audio-singleton-v1') !== false) { echo "app-main already patched\n"; exit; }
$pairs2 = array(
    // starting a card preview pauses the hover audio first
    array("    window.activeAudioCard = card;\n    card._previewPending = true;",
          "    try { if (window.__djcShpAudio) { window.__djcShpAudio.pause(); } window.__djcShpAudio = null; } catch (e) {}\n    window.activeAudioCard = card;\n    card._previewPending = true;"),
    // expose this engine's stopper from inside its own stop function
    array("function stopPreview(card) {",
          "function stopPreview(card) {\n    window.djcStopCardEngineAudio = function () { try { if (window.activeAudioCard) { stopPreview(window.activeAudioCard); } } catch (e) {} };"),
);
$m = ''; $out2 = applyFile($a, $pairs2, $m);
if ($out2 === false) { echo "app-main ABORT: $m\n"; exit; }

if (!$GO) { echo "DRY OK\n"; exit; }
foreach (array(array($SHP, $out), array($AM, $out2)) as $w) {
    copy($w[0], $w[0] . '.bak-singleton.bak');
    if (file_put_contents($w[0], $w[1], LOCK_EX) !== strlen($w[1]) || file_get_contents($w[0]) !== $w[1]) {
        copy($w[0] . '.bak-singleton.bak', $w[0]); echo basename($w[0]) . ": WRITE FAIL; restored\n"; exit;
    }
}
echo "patched. Verify live: hover fast in and out (no orphan), two artists back to back (one track).\n";
@unlink(__FILE__);

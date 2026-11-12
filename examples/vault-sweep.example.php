<?php
/**
 * EXAMPLE - backup vault sweep (verify by request, not by layout)
 *
 * Companion to the guarded-deploy-workers backup policy: workers keep .bak
 * files while a patch is pending review. This sweep is the weekly cron that
 * catches the ones that ever become web-reachable again.
 *
 * The model:
 *   1. Every path a deploy creates is recorded in a manifest OUTSIDE the docroot.
 *   2. The sweep requests every backup-looking file it finds over HTTP.
 *   3. Anything answering 200 gets moved to the vault and logged.
 *
 * A control is only real if requesting it fails. Layout lies; requests don't.
 * (Managed hosts can rewrite or drop .htaccess silently, and a plan change
 * can put nginx between the world and your FilesMatch rules.)
 *
 * CLI:
 *   php vault-sweep.php                # dry run: report only
 *   php vault-sweep.php --move         # move live backups into the vault
 *
 * Cron (weekly, Sundays 06:10):
 *   10 6 * * 0 php /home/YOU/vault/vault-sweep.php --move >> /home/YOU/vault/sweep.log 2>&1
 */

$docroot = '/home/you/public_html';
$vault   = '/home/you/bh-vault';          // one level ABOVE the docroot: unreachable by HTTP
$manifest = $vault . '/manifest.json';
$base    = 'https://example.com';         // how the world reaches you
$patterns = ['*.bak', '*.bak-*', '*.old', '*.orig', '*~', '*.save'];

// --- gather backup-looking files under the docroot ---
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($docroot, FilesystemIterator::SKIP_DOTS));
$candidates = [];
foreach ($files as $f) {
    $p = $f->getPathname();
    foreach ($patterns as $pat) {
        if (fnmatch($pat, basename($p))) { $candidates[] = $p; break; }
    }
}

// --- the ground truth: request each one the way a stranger would ---
$live = [];
foreach ($candidates as $path) {
    $rel  = ltrim(str_replace($docroot, '', $path), '/');
    $code = http_status($base . '/' . rawurlencode($rel));
    if ($code === 200) { $live[] = $path; }
    echo ($code === 200 ? 'LIVE  ' : " $code  ") . $rel . PHP_EOL;
}

if (!$live) { echo "sweep clean: " . count($candidates) . " backups checked, none reachable\n"; exit(0); }

if (in_array('--move', $argv, true)) {
    @mkdir($vault, 0750, true);
    $manifestData = file_exists($manifest) ? json_decode(file_get_contents($manifest), true) : ['moves' => []];
    foreach ($live as $path) {
        $dest = $vault . '/' . ltrim(str_replace($docroot, '', $path), '/');
        @mkdir(dirname($dest), 0750, true);
        if (rename($path, $dest)) {   // rename() is atomic on the same filesystem
            $manifestData['moves'][] = ['from' => $path, 'to' => $dest, 'at' => date('c')];
            echo "MOVED  $path -> $dest\n";
        }
    }
    file_put_contents($manifest, json_encode($manifestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function http_status(string $url): int {
    $ctx = stream_context_create(['http' => ['method' => 'HEAD', 'timeout' => 8, 'ignore_errors' => true]]);
    @file_get_contents($url, false, $ctx);
    foreach ($http_response_header ?? [] as $h) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) return (int) $m[1];
    }
    return 0;
}

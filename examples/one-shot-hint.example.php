<?php
/**
 * Example: inject a self-contained one-shot UI hint before </body>.
 *
 * WHY THIS RECIPE EXISTS
 * The page's main script is one big IIFE. Any injected code that reaches
 * INTO that IIFE - its variables, its helpers - fails SILENTLY: the IIFE
 * closed lines ago and nothing it owns is reachable. Appended blocks must
 * be self-contained: their own querySelectors, their own listeners, their
 * own cleanup.
 *
 * This worker injects a hint bar that shows ONCE per session (~6s), gated
 * by sessionStorage, then never again. No library, no polling.
 *
 * Production lesson that motivated this recipe (Nov 2026): an injected
 * block kept no-oping because it queried elements the page re-parented
 * asynchronously. Making the block own everything - including an
 * appendChild it controls end to end - fixed it in one pass.
 */

require __DIR__ . '/../src/GuardedWorker.php';

$worker = new GuardedWorker('CHANGE-ME');
$worker->authorize();

$page   = __DIR__ . '/index.html';
$src    = $worker->read($page);
$marker = 'hint-v1';

if (strpos($src, $marker) !== false) { echo 'ALREADY PATCHED'; exit; }

$needle = '</body>';
$worker->guard($src, array($needle => 'body close'));

$inject = <<<HTML
<script data-{$marker}>
(function () {
  var KEY = 'myHintShown';
  if (sessionStorage.getItem(KEY)) { return; }
  sessionStorage.setItem(KEY, '1');

  var bar = document.createElement('div');
  bar.setAttribute('data-{$marker}', '');
  bar.textContent = 'Tip: hover a card to preview it';
  bar.style.cssText = 'position:fixed;left:50%;bottom:52px;transform:translateX(-50%);'
    + 'background:#0e7490;color:#fff;font:13px/1.4 system-ui,sans-serif;'
    + 'padding:8px 14px;border-radius:6px;z-index:2147483000;opacity:0;'
    + 'transition:opacity .4s';
  document.body.appendChild(bar);

  requestAnimationFrame(function () { bar.style.opacity = '1'; });
  setTimeout(function () { bar.style.opacity = '0'; }, 6000);
  setTimeout(function () { if (bar.parentNode) { bar.parentNode.removeChild(bar); } }, 6600);
})();
</script>
HTML;

$backup = $worker->backup($page, $marker);
$worker->apply(
    $page,
    $backup,
    $src,
    array($needle => $inject . "\n" . $needle),
    array($marker)
);

$worker->done(__FILE__, $backup, 'OK: one-shot hint injected');

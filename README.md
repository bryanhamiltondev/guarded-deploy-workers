# Guarded Deploy Workers

Token-gated one-shot PHP patch workers for shared hosting. No shell, no SSH, no
deployment pipeline - just a file you upload to a cPanel box, hit once with a URL,
and it applies a guarded patch, verifies it, and deletes itself.

Built and battle-tested across months of live deploys on a 1,000-page production site
with LiteSpeed caching and opcache lag - where every naive patch script eventually
double-patches, aborts halfway, or leaves backups web-accessible.

## The problem it solves

Pasting a code change into a production file over a browser-based file manager is
how sites die. This pattern wraps the change in guards:

- **Token gate** - wrong or missing token renders the site's real 404
- **Marker check** - already-patched files abort; double-patching is impossible
- **Needle guards** - every anchor string must appear exactly once, or nothing is written
- **Backup + auto-restore** - backup before write, restore on any verify failure
- **Verify the patch, not the intent** - replacements must land byte-exact or roll back
- **Self-cleaning** - worker deletes itself after reporting; backup lifecycle is explicit
- **Real 404 behavior** - failed token gates never leak that the worker exists

## Quick start

```php
require __DIR__ . '/../src/GuardedWorker.php';

$worker = new GuardedWorker('CHANGE-ME');
$worker->authorize();                 // 404s unless ?t=CHANGE-ME

$src = $worker->read(__DIR__ . '/index.html');
if (strpos($src, 'my-marker') !== false) { echo 'ALREADY PATCHED'; exit; }

$needle = '<div class="legacy-banner">';
$worker->guard($src, array($needle => 'replacement'));   // count must be 1

$backup = $worker->backup(__DIR__ . '/index.html', 'tag1');
$worker->apply(__DIR__ . '/index.html', $backup, $src, array(
    $needle => '<div class="legacy-banner" style="display:none" data-my-marker>',
), array('my-marker'));

$worker->done(__FILE__, $backup, 'OK: banner hidden');
```

Upload `GuardedWorker.php` + your worker, hit the URL once, done. Worked examples:

- [examples/hide-element.example.php](examples/hide-element.example.php) - the basic guarded patch
- [examples/style-block.example.php](examples/style-block.example.php) - replacing an inline `<style>` block without byte needles across it
- [examples/one-shot-hint.example.php](examples/one-shot-hint.example.php) - self-contained one-shot UI hint after a closed IIFE

## The checklist

[docs/checklist.md](docs/checklist.md) is the pre-flight list - 18 rules learned the
hard way: probe before patching, byte-exact needles, delta-based balance checks,
cache-busting live verification, three-layer backslash escaping, and why stale
opcache is the default explanation for "the patch didn't work".

[docs/recipes.md](docs/recipes.md) goes deeper on the four patterns that earn
their own page: marker-to-close splicing, the version-bump pass for versioned
assets, the mock-render gate (php -l is not a render test), and self-contained
appended scripts.

## When to use this

- Shared hosting (cPanel) with no shell access
- Production files you cannot risk pasting into a web editor
- Changes that must be reviewable, reversible, and one-shot
- Teams where the deploy log is the chat thread

## When not to

- Anything with a real deployment pipeline - use it instead
- Storing secrets in the worker - the token gates access, the file still lives in
  your web root; rotate tokens and keep payloads free of credentials

## License

MIT

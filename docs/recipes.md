# Recipes

Patterns extracted from live deploys. Each one exists because the naive version
failed in production first.

## 1. Marker-to-close for inline blocks

Byte-exact needles are ideal for one-line swaps. For inline `<style>` or
`<script>` blocks - long, whitespace-fragile, minifier-reformatted - they are
fragile: a single invisible difference and the needle never matches.

Instead: `guard()` on the opening tag, find the matching close tag with
`strpos()`, splice the whole block with `substr()`, and pass the block you
actually read as the needle. See [examples/style-block.example.php](../examples/style-block.example.php).

## 2. The version-bump pass

Patching a versioned asset (`rack.css?v=4`, `app.js?v=30`) is only half the
deploy. Every page that references the asset still points at the OLD version
string - and with LiteSpeed-style HTML caching, visitors can keep receiving the
stale referencer for minutes after your write.

The rule: **patching a versioned asset REQUIRES a version-bump pass in the same
deploy.** Bump `?v=N` in every referencing page (or the partial that emits it),
then cache-bust and verify the SERVED HTML shows the new version - not just the
file on disk.

## 3. The mock-render gate

`php -l` proves the file parses. It does not prove the page renders.

A real deploy once shipped with an orphaned `fclose()` after a refactor:
lint passed, first request 500'd. Runtime errors - undefined variables, orphaned
resource calls, requires that only fire on a code path - are invisible to a
syntax check.

The rule: **after a PHP patch, mock-render the patched page** (or hit it with a
HEAD request) and assert a 200 plus the marker string before `done()` reports
success.

## 4. Self-contained appended scripts

If the page's main script is one big IIFE, injected code that reaches into it
fails silently - the closure closed lines ago. Every appended block must own its
querySelectors, listeners, and cleanup. See
[examples/one-shot-hint.example.php](../examples/one-shot-hint.example.php).

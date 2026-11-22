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

## 5. The runtime recount masks static fallbacks

A value your page recounts at runtime (fetch -> JS -> paint) still ships with
static fallbacks: the no-JS first paint, the count-up's `data-to`, tooltips,
`aria-label`s. When the truth changes on the server, the runtime block updates
itself on the next load - and silently MASKS the stale statics. The drift is
invisible in the browser and obvious only in the raw HTML, which is what
crawlers and screen readers get first.

Real deploy: a medal strip whose live proxy said 1 gold / 9 silver / 44 bronze
while the HTML painted 43 bronze, 8 silver, and an aria-label two revisions
older - three stale surfaces on one element, invisible until read raw.

The rule: **when a deploy changes a value the runtime recounts, sync every
static surface of that element in the SAME worker** - and sweep every file that
carries the markup, not just the page you're looking at. See
[examples/static-fallback-sync.example.php](../examples/static-fallback-sync.example.php).

## 6. The dead guard: referenced is not assigned

A cross-file hook only works if someone ASSIGNS it. A page can reference
`window.stopPreview(...)` from dawn to dusk - if no engine ever does
`window.stopPreview = function ...`, the guard is dead code and the fallback
branch always runs.

Real deploy: two audio engines, each unaware of the other. One referenced a
window-level stopper that was never assigned anywhere, so its cross-engine
safety branch silently never fired - and two tracks played over each other.

The rule: **before trusting a `window.*` contract, grep for the ASSIGNMENT,
not the reference** - and expose stoppers from INSIDE the owning engine's own
stop function, so they always see that engine's current state. See
[examples/audio-singleton.example.php](../examples/audio-singleton.example.php).

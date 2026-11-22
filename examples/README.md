# Example workers

Real guarded workers from production deploys, cleaned of site-specific paths.
Each one targets `src/GuardedWorker.php` and follows the same discipline:
needle guards before any write, backup first, marker-based verification,
self-cleanup after success.

- `style-block.example.php` - inject a `<style>` block via an `id` marker + strpos slice (no byte-needles for inline CSS)
- `rack-append.example.php` - append a self-contained IIFE block to a closed main script (own querySelectors, own cleanup)
- `replay-needle.example.php` - replay a prior worker's needles onto the cached pre-patch source (byte-size must match live)
- `receipts-selflog.example.php` - every worker appends its own ledger row before self-deleting
- `media-session.example.php` - Media Session metadata inject so the OS media popover shows the track + artwork instead of the page title (needles cut from raw served bytes - the browser decodes HTML entities and lies)
- `dual-version-bump.example.php` - asset version sweep that accepts two pre-states (v3 OR v4 -> v5) so same-day workers patch in any order
- `static-fallback-sync.example.php` - re-sync static HTML fallbacks (no-JS paint, tooltips, aria-labels) with values a runtime script recounts - the drift the runtime masks, swept across every HTML file carrying the element
- `audio-singleton.example.php` - cross-engine audio stop bridge: two JS players that each own an <audio> learn to stop each other, plus an orphan guard so an in-flight fetch resolving after mouseout never starts a track (includes the dead-guard autopsy: a window.* stopper that is referenced but never assigned)

See `../docs/recipes.md` for the full recipe list.

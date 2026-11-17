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

See `../docs/recipes.md` for the full recipe list.

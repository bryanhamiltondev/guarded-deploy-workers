# Worker pre-flight checklist

Rules learned the hard way across months of one-shot deploys on live, cache-layered production sites.

## Before writing the patch

1. **Probe before patching.** If you have not seen the live bytes, ship a read-only probe first. Guessing needles burns a deploy round-trip and sometimes a production file.
2. **Needles must be byte-exact.** Copy them from live source, never from memory or a template. Watch for em dashes, `&rarr;` entities, and single- vs double-quoted attributes.
3. **Map the control flow around the inject point.** Code that creates a value must run BEFORE the branch that tests it. An override often lives in two places: the stylesheet rule AND the inline style the JS stamps at boot.
4. **One change per needle, one needle per change.** If a needle matches twice, it will patch twice. Count == 1 or abort.

## In the worker

5. **Token-gate everything**, and render the site's real 404 when the token is missing.
6. **Marker check first**: if the patch marker is already present, abort - never double-patch.
7. **Guard every needle** (count must be exactly 1), abort with no write otherwise.
8. **Backup before write.** Name it `.bak-<tag>.bak`, keep it until the result is confirmed, restore on any verify failure.
9. **Verify what the patch actually contains**, not a guess: if it injects exactly one marker, require exactly one marker.
10. **Lint-gate any PHP write** (`php -l`); on failure, restore before anything else runs.
11. **Self-delete the worker** after reporting. A worker that scans directories must exclude itself from candidate lists.
12. **Balance checks must be delta-based**, never absolute zero - string literals in big files break naive brace counting.

## After the deploy

13. **Cache-bust every live check** (`?cb=<timestamp>`). LiteSpeed caches HTML for minutes; opcache lags behind disk; stale bytecode is the default explanation for "the patch didn't work".
14. **Live-verify the NEW behavior**, not just that the old path still works.
15. **Front-end bugs reproduce clean first.** A fresh headless browser with no cache and no extensions. Self-resolving glitches are stale cache, not code.
16. **For risky patches, deliberately keep the backup** until the result is confirmed, and clean it up in the next worker.
17. **When the user names a specific suspect, act on exactly that.** Do not expand scope mid-patch.

## Known trap

18. **Escaping through three layers** (python -> PHP single-quoted -> JS) eats backslashes differently at each hop. Build needle and replacement byte strings explicitly and verify them char-by-char; never hand-count backslashes.

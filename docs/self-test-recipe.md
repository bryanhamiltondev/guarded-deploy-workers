# The Four Gates: a fixture self-test for one-shot deploy workers

*A recipe from the guarded-deploy-workers repo. Written for anyone who ships PHP patch workers to a shared host over FTP and has been burned by a "successful" deploy that did nothing.*

---

A one-shot deploy worker is a PHP file you upload to `public_html`, hit with a token-gated URL, and never touch again. It patches a live file, writes a backup, logs a receipt, and self-deletes. The dangerous part isn't the patching - it's that everything about the failure is silent. The worker can run clean, write a receipt row, delete itself, and the patched file can still be wrong. Or right and you can't prove it.

So every worker I ship passes four gates before it touches production, and the same four asserts run against the real worker file - not a paraphrase of it.

## Gate 1 - Lint

No host has Node, no host has a CI runner. The lint gate is `php -l` plus a brace/paren balance check on any embedded JS payload. It catches less than you'd hope, but it's free, and the things it catches are humiliating to ship.

## Gate 2 - Needle guards

Every patch targets a needle: an exact string in the source file. Before any write, assert every needle's count on the real target bytes:

- count **== 0** -> drift or wrong file. Abort.
- count **> 1** -> ambiguous. Abort.
- count **== 1** -> proceed.

Two asserts hide inside that rule. First, the **already-shipped assert**: if your own marker string is present in the target, abort as `ALREADY PATCHED` - a worker that can't detect its own past will corrupt the file on a second run. Second, the **needle-fitness assert**: a needle that's formatted loosely ("find some `<tr>`") will pass on your fixture and match the wrong place in production. Needles must be byte-exact against **served bytes** - fetched raw from the server, not read from a browser DOM, which normalizes and rewrites what the server actually sends.

## Gate 3 - The fixture self-test

This is the gate that catches everything else, and the one people skip. The rule: **the test runs the real worker file**, via `php -r '$_GET["t"]="..."; $_GET["go"]="1"; chdir($fixture); include "worker.php";'` - not a re-implementation of what the worker "basically does."

The fixture directory is a copy of the real target file plus the worker. Four asserts run against it:

1. **All abort paths abort.** Bad token -> 403. Missing needle -> abort, no write. Already-patched marker -> abort, no write. Double-run -> second run detects its own marker and refuses. A worker with four exit paths gets four test runs; three passing and one untested is the one that fires in production.
2. **The happy path writes the exact byte delta.** Expected size change computed by hand from the patch block, then asserted. (Early versions asserted "file shrank" - wrong guard for inject patches, which grow. A size assertion must match the actual operation.)
3. **The verify-markers are present and the forbidden ones are gone.** Every worker plants a marker (`# workerid1`) in what it writes. Assert the marker exists post-run; assert whatever the patch *removed* is at zero occurrences. And make sure forbidden-word verifies can't match the worker's own comments - the payload is inside the file too.
4. **The backup and self-delete behave.** Backup exists with expected size, receipts row appended exactly once, and in the fixture run the self-delete wipes the copy. (Do fixture runs in a scratch directory - a rehearsal that passes really does delete everything around it.)

One more lesson that cost me a production scare: mocks and fixtures must be built from **real dumped bytes**. Hand-typed fixtures pass tests against markup that doesn't exist.

## Gate 4 - Verify the surface, never the receipt

After the real run: cache-bust the live URL (`?zcb=<random>` - LiteSpeed and friends will happily serve you stale proof), fetch the patched surface, and grep for the marker. **A receipts row is evidence a worker ran, not evidence the deploy worked.** I have a worker that logged its receipt, then failed to persist its file writes - the receipt said done and the page said no. The receipt is claim #1 of two. Check both.

---

The full pattern - token gate, dry-run/apply split, self-cleaning backups, receipts - lives in [this repo](https://github.com/bryanhamiltondev/guarded-deploy-workers). The short version: a deploy worker should be incapable of succeeding quietly, and the four gates are how you prove a negative.

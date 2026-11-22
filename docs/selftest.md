# The fixture self-test rule

A DRY run cannot prove its own failure paths.

The failure this rule came from: a dry run reported the region, the needle
count, and the byte delta - three green lines. But all three were reads of
the same in-memory string, so it was one observation wearing three hats.
Byte-verify covers the write path; nothing covered the report path. The
first time the failure path actually ran in production, it took the wrong
branch and reported success.

## The rule

Every guarded worker ships with a fixture self-test: a trimmed copy of the
target (or a synthetic fixture with the same needles) kept next to the
worker, plus a mode that runs the worker's logic against the fixture and
asserts that the abort paths actually fire:

1. **Already-patched abort** - a fixture with the marker present must abort
   with zero writes.
2. **Needle-count abort** - a fixture with the needle appearing twice must
   abort with zero writes.
3. **Verify-restore** - a forced verify failure must restore the backup
   byte-exact.
4. **Fingerprint presence** - the self-test report itself must carry the
   current build fingerprint, proving which revision you just tested.

Run it before every GO. If the self-test cannot fail its fixture on
purpose, the worker is not guarded - it is lucky.

## What it costs

A fixture file and one extra query flag. Roughly twenty lines per worker.
Compare that with discovering in production that an abort path returned
the success string.

## Related

- [checklist.md](checklist.md) - the pre-flight list (this is rule 19)
- [../examples/version-fingerprint.example.php](../examples/version-fingerprint.example.php) -
  the fingerprint this rule verifies against

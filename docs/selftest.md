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

## The test itself can be the bug

First live run of a fixture self-test, it flagged a failure. The patcher
was fine - the test was wrong. The abort-path fixture was built as
"full needle plus trailing junk", but the patcher's contract is count==1,
so needle-plus-junk is a legitimate patch, not an abort. The test asserted
the wrong expectation and the self-test correctly reported it. The self-test
was the only thing on the box that knew something was wrong, and it was
reporting on itself.

Two rules fall out of that:

1. **Validate the fixture, not just the code.** An abort-path fixture must
   provably lack the complete needle - the cheapest way is a truncated
   needle (drop tail text), never needle-plus-junk. If your abort fixture
   contains the full needle, you are testing a patch and calling it an
   abort.
2. **Patch-path-plus-junk is its own test.** Needle plus trailing junk must
   patch exactly once - assert it explicitly, so the count==1 contract is
   pinned from both sides and the two fixtures can never be confused.

When a self-test fails, triage in this order: is the fixture what you think
it is, is the expectation what the contract says, and only then is the code
wrong. A self-test that can catch its own author is not a failure of the
pattern - it is the pattern working.

## What it costs

A fixture file and one extra query flag. Roughly twenty lines per worker.
Compare that with discovering in production that an abort path returned
the success string.

## Related

- [checklist.md](checklist.md) - the pre-flight list (this is rule 19)
- [../examples/version-fingerprint.example.php](../examples/version-fingerprint.example.php) -
  the fingerprint this rule verifies against

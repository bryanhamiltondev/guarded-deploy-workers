# The checklist, annotated

Every rule here was paid for in production. Numbering continues from the original 18 rules.

## Rule 19: A count-up widget's `data-to` and its inner text are one needle

JS count-up badges render a target (`data-to="51"`) over a static fallback
(the element's inner text). The two drift independently: a prior deploy bumps
one and not the other, and the next patch inherits the drift.

So patch them as a single needle - `data-to="50">49<` -> `data-to="51">51<` -
and verify the old value is ABSENT (count 0) after the patch, not merely that
the new value is present. "New value exists" passes on a half-patched page.

## Rule 20: A receipts self-log row is not proof of a deploy

Workers that append their own success row to a ledger can mislead you: the
row lands even when a dedup guard silently swallowed the actual change.
Always cache-bust-fetch the patched surface (`?zcb=<random>`) and grep the
real bytes before declaring victory. The ledger says what the worker did;
the bytes say what the page is.

## Rule 21: Punctuation in tokens can bounce on some hosts

A worker gate token containing `!` kept rejecting re-runs on one host while
the identical code with an alphanumeric token ran clean. Default to
punctuation-free alphanumeric tokens (`bhClnum5x51`), never `bh-clnum!5`.

## Rule 22: GO behind POST, token in body, no-store always

The original worker pattern took the gate token as a GET parameter, which
made every execution URL a link: cacheable by shared caches, fetchable by
link-preview bots, and one accidental paste away from being triggered by
something that only wanted an og:image. A replayed DRY run is harmless. A
replayed GO run is a second patch.

The fix is three lines at the top of every worker:

```php
header('Cache-Control: no-store');          // nothing stores this response
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); exit('POST required');   // GET = dry info only
}
$token = $_POST['token'] ?? '';             // token moves out of the URL
```

Notes from production:

- Self-deleting workers blunt the replay risk after success (the file is
gone), but the live window is real: a bot firing the GO URL between "file
exists" and "self-delete" gets a second patch attempt. Exact-1 needle guards
will abort it loudly - safe, but noisy. Guards doing their job by accident
is not a security design.
- DRY runs may stay GET (they change nothing); GO must be POST.
- CLI/cron keepers read the token from argv or an env var instead.
- Cache-busting query params become a backup for the VERIFY step, not the
mechanism that keeps a deploy from double-firing.

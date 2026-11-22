# Changelog

All notable changes to guarded-deploy-workers.

## Unreleased

- **Added:** `examples/version-fingerprint.example.php` - DRY-by-default
  gate with a build fingerprint on every response; stale opcache/CDN
  copies get detected, not guessed at, and a stale copy that will not die
  is beaten by a new filename plus a new token
- **Added:** `docs/selftest.md` - the fixture self-test rule: a DRY run
  cannot prove its own failure paths, so every worker ships with
  assertions that its abort paths actually fire
- **Added:** `docs/recipes.md` - four production-extracted patterns:
  marker-to-close splicing for inline blocks, the version-bump pass for
  versioned assets, the mock-render gate (php -l is not a render test), and
  self-contained appended scripts after a closed IIFE
- **Added:** `examples/style-block.example.php` - inline `<style id>` block
  replacement without byte needles across the whole block
- **Added:** `examples/one-shot-hint.example.php` - self-contained one-shot
  sessionStorage hint injected before `</body>`

## 1.0.0 - 2026-11-07

- Initial public release: `src/GuardedWorker.php` (token gate with `hash_equals`,
  one-occurrence needle guards, backup/apply/verify/restore, self-deleting
  `done()`), `examples/hide-element.example.php`, and `docs/checklist.md`
  (18 rules from live deploys)
- Wiki published: needle anatomy, cache-aware verification, failure modes, the
  annotated checklist

# Changelog

All notable changes to this project are documented in this file.
The format is based on [Keep a Changelog](https://keepachangelog.com/).

## [Unreleased]

### Added

- `examples/medal-sync.example.php` - count-up widgets: patch `data-to` and
  inner fallback text as ONE needle; verify old value is gone, not just that
  the new value exists. Extracted from a live production deploy (Nov 2026)
  where a badge had drifted to `data-to="50">49<`.

### Changed

- `docs/checklist.md`: rules 19-21 added.
  - Rule 19: count-up `data-to` + inner text are one needle unit.
  - Rule 20: a receipts self-log row is not proof of a deploy - always
    cache-bust-verify the patched surface.
  - Rule 21: punctuation in gate tokens can bounce on some hosts; default to
    alphanumeric tokens.

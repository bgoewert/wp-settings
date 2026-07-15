## Context

`bgoewert/wp-settings` is vendored (each with its own locked version) inside three separate
WordPress plugin repos: `wp-seiler-locations` (pinned to v1.5.2, bundling wp-settings 2.27.3),
and `wp-seiler-forms` / `wp-mu-basic-auth` (both on 2.28.2). Site logs on a consumer project
(wp-site-seiler-public-safety, local DDEV + staging) show three distinct null/undefined-index
issues sourced from this library, spread across the two vendored versions:

1. `WP_Setting.php` — `str_replace()` passed `null` for `$subject` (seen at 2.27.3). Current
   `main` already casts `$setting` to `(string)` before the `str_replace()` call sites in
   `set()`/`delete()`, so this looks fixed on `main` already — just never released/bumped
   downstream.
2. `WP_Setting_Encryption.php` — `mb_strlen()` passed `null` (seen at 2.28.2, i.e. newer than #1's
   fixed version — a regression introduced between 2.27.3 and 2.28.2, not yet fixed on `main`).
3. `WP_Settings.php::init()` — undefined array key `"name"` when reading a settings section
   (seen at 2.28.2, also unfixed on `main`).

So one version bump alone can't give every consumer a clean log: whichever version they land on,
at least one of these three bugs is present. This change fixes all three on `main` and cuts one
release that is clean of all of them.

## Goals / Non-Goals

**Goals:**
- Make all three specific null/undefined-key warnings unreachable via defensive coding at the
  actual point of failure (not a blanket `@` suppression).
- Add regression tests that fail on the old code and pass on the fix, so these can't silently
  regress again across future versions the way #2 and #3 did between 2.27.3 and 2.28.2.
- Cut a release so downstream plugins have one version to bump to that is clean of all three.

**Non-Goals:**
- Auditing the whole library for every possible null-input case beyond these three reported
  sites — scope is the specific bugs observed in production/staging/local logs.
- Making the downstream version bumps in `wp-seiler-locations` / `wp-seiler-forms` /
  `wp-mu-basic-auth` — those are follow-up changes in their own repos once this release exists.

## Decisions

### D1 — Fix at the point of null-acceptance, not the call site
For `WP_Setting_Encryption`, coalesce in `get_default_key()` / `get_default_nonce()` (where the
value can legitimately be unset/null, e.g. the key/nonce constant isn't defined yet) rather than
patching `mb_strlen()` call sites in `check_key_len()`/`check_nonce_len()`. Rationale: those
getters are the actual source of the "not configured" case; fixing there covers every current and
future caller, not just the two that happen to call `mb_strlen()` today. Trade-off: needs a
decision on what an empty-string key/nonce means downstream (see Open Questions).

### D2 — Match the existing sibling pattern for the missing `name` key
`WP_Settings::init()` already has `$slug = $section["slug"] ?? $key;` one line above the buggy
`$section["name"]` read. Use the same `?? ''` (or a sensible label fallback) rather than inventing
a new pattern, for consistency and minimal diff.

### D3 — Confirm, don't just assume, `WP_Setting.php` is fixed on `main`
Rather than skip #1 entirely because current `main` "looks" safe, add a regression test that
exercises `set()`/`delete()` with the historical null-triggering input, so the release notes can
state affirmatively that this bug is fixed and covered — not just believed fixed by inspection.

## Risks / Trade-offs

- [Coalescing a missing key/nonce to `''` could mask a real misconfiguration (encryption running
  with an empty key) instead of surfacing it loudly] → Investigate whether `get_default_key()` /
  `get_default_nonce()` should instead generate/report a missing configuration explicitly; if
  today's behavior already tolerates a weak/empty key gracefully elsewhere, matching that is fine,
  but this needs a quick read of how `encrypt()`/`decrypt()` behave when the key is empty before
  locking in D1's fix. Flagged as an open question below.
- [Releasing a new version doesn't retroactively fix already-deployed sites] → Downstream plugins
  must each still take a follow-up dependency bump; this change only makes that bump possible.

## Open Questions

- Does an empty-string key/nonce (post-fix) degrade safely in `encrypt()`/`decrypt()`, or should
  the constructor instead throw/log when neither a constant nor a stored option value is
  available? Resolve during implementation by reading `encrypt()`/`decrypt()` before finalizing
  the coalesce approach in `get_default_key()`/`get_default_nonce()`.

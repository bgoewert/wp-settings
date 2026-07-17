## Context

`WP_Setting::get( $setting, $default_value, $decrypt )` prefixes `$setting` with the normalized `WP_Setting::$text_domain` (unless already prefixed), then reads that prefixed key via `get_option()`. `WP_Setting::$text_domain` is a public static property, currently set in exactly one place: `WP_Settings::__construct()` (`src/WP_Settings.php`), which every consuming plugin's admin-settings subclass calls, typically via a singleton `instance()` pattern.

The library's own README example calls `new My_Settings();` unconditionally at file scope — safe, because the only side effect of construction beyond setting `$text_domain` is `add_action( 'admin_init', [ $this, 'init' ] )`, and WordPress core only ever fires `admin_init` inside wp-admin regardless of when the callback was registered. So constructing the settings object on every request (frontend included) costs nothing beyond the object allocation and a static property write.

`seiler-locations` (a real consumer) deviated from this by gating construction behind `if ( is_admin() )` — plausibly an attempt to "optimize" a frontend-visible request, not realizing `WP_Setting::get()` itself depends on that same construction having happened. The result: on the frontend, in REST handlers, and in WP-CLI, `self::$text_domain` is `NULL`, the prefixing branch in `get()` is skipped entirely, and every settings lookup silently reads a nonexistent unprefixed option key, always returning the caller's hardcoded default. No exception, no warning, no log line — this was only found by manually comparing `WP_Setting::$text_domain` and `get_option()` results side-by-side across contexts.

This design fixes what the *library* controls: making the misuse loud instead of silent, and giving consumers an explicit, documented alternative to full `WP_Settings` construction when they specifically need `$text_domain` set without the admin-page machinery attached.

## Goals / Non-Goals

**Goals:**
- Turn "the domain was never set" from a silent wrong-default into a visible, actionable signal in debug environments.
- Give consumers a minimal, explicit, documented way to set `WP_Setting::$text_domain` without depending on `WP_Settings` construction at all.
- Correct the library's own documentation so the safe, already-working pattern (unconditional construction) is stated as a rule, not just implied by one example.

**Non-Goals:**
- Not changing `WP_Settings::__construct()`'s behavior or hook registration — it already behaves correctly when called unconditionally.
- Not auditing or patching `seiler-locations` (or any other consumer) from this repo — that is separate, per-project follow-up work once this ships.
- Not adding any new persistent option or settings-storage mechanism — this is purely a safety/documentation change around the existing `$text_domain` mechanism.
- Not changing the return value or default-fallback behavior of `get()`/`set()` when the domain genuinely isn't set — only adding a notice alongside the existing (unchanged) fallback behavior. Debug-mode consumers see a warning **and** still get the same default-fallback value; nothing breaks in production.

## Decisions

**1. Detect misuse via `_doing_it_wrong()`, not an exception.**
`_doing_it_wrong( $function, $message, $version )` is WordPress core's standard, well-understood mechanism for signaling "you're using this correctly-shaped API in a way that will misbehave" — it logs/triggers a warning only when `WP_DEBUG` (or `SCRIPT_DEBUG`) is enabled, is silent in production, and integrates with query-monitor-style debug tooling developers already have running. Rejected alternative: throwing an exception — would be a hard break for any consumer currently relying on the (buggy but non-fatal) fallback-to-default behavior; too aggressive for a library used across multiple live Seiler sites.

**2. Check happens inside `get()` and `set()`, once per call, no caching of "already warned."**
Every call with `self::$text_domain` still empty re-triggers the notice. This is intentionally noisy in a broken environment — a consumer with this misconfiguration will see it on essentially every settings read, which is the point (impossible to miss during development). `_doing_it_wrong()` itself doesn't deduplicate either, matching how core's own usages of it behave.

**3. Add `WP_Setting::set_text_domain( string $domain ): void` as the documented escape hatch.**
The property's own docblock already says "or manually via `$text_domain` assignment" — this formalizes that into a real method: `WP_Setting::set_text_domain( 'my-plugin' )` internally does the same `normalize_text_domain()` call `WP_Settings::__construct()` does, so a consumer gets identical prefixing behavior without needing to instantiate a `WP_Settings` subclass. This matters for any consumer that has a legitimate reason to avoid constructing the full settings-page object outside admin (e.g. a future subclass that adds real non-admin-safe side effects in its constructor) — they now have a supported way to keep `WP_Setting::get()` working correctly regardless.

**4. Documentation gets an explicit "don't do this" callout, not just a corrected example.**
The existing usage example was already correct (unconditional construction) — the bug happened because a consumer didn't infer "this must always run" from an example that simply doesn't show the alternative. Adding an explicit sentence stating the constraint directly (why `admin_init`-gating is unnecessary and what breaks if you add your own `is_admin()` gate) closes that inference gap.

## Risks / Trade-offs

- **[Risk]** `_doing_it_wrong()` calls could be noisy for any other existing consumer that happens to share this misconfiguration, surfacing in their debug logs immediately on upgrade → **Mitigation**: this is the intended outcome — it's surfacing a pre-existing silent bug, not introducing a new one. Debug-mode-only scope means production behavior is completely unaffected either way.
- **[Trade-off]** Adding `set_text_domain()` creates two ways to establish the domain (full `WP_Settings` construction, or the new minimal setter) → acceptable; the docblock/README will state the minimal setter is for consumers who specifically don't want to construct a full settings-page object, and the existing example remains the primary recommended path for anyone building an admin UI anyway.

## Migration Plan

No breaking changes, no data migration. Ships as a normal library release (minor version bump — new public API + behavior addition, no removals):
1. Release with updated README and the two `src/WP_Setting.php` changes.
2. Consuming projects update their `composer.json` constraint when ready; no code changes required to keep current (buggy) behavior working exactly as before in production. Debug-mode users will start seeing the new notice immediately after upgrading if they have the misconfiguration.
3. Rollback is a normal version pin/revert; no persisted state is affected.

## Open Questions

- Whether to also audit other known Seiler consumers of this library (beyond `seiler-locations`) for the same `is_admin()`-gated-construction pattern, once this ships — tracked as follow-up, not blocking this change.

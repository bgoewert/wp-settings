## Why

`WP_Setting::get()`/`::set()` prefix every option key with `WP_Setting::$text_domain`, but that static property is only ever set as a side effect of a `WP_Settings` subclass being constructed. Discovered via `seiler-locations` (a consumer plugin): it gates that construction behind `if ( is_admin() )`, so on the frontend, in REST requests, and in WP-CLI, `WP_Setting::$text_domain` stays `NULL` and every `WP_Setting::get()` call there reads an unprefixed, nonexistent option key — silently returning the hardcoded default instead of the admin-configured value. Every admin-configured setting in that plugin was effectively inert outside wp-admin, with no error, warning, or other signal.

The library's own README usage example already avoids this (`new My_Settings();` is called unconditionally, not gated by `is_admin()`), and that pattern is safe today because the class's own registered hook (`admin_init`) only ever fires in an admin context regardless of when the object is constructed. But nothing in the library stops a consumer from gating construction anyway, and when they do, the failure is completely silent — this is a footgun other consumers of this library could just as easily hit, and won't notice until someone manually diffs frontend vs. admin behavior.

## What Changes

- Add a runtime safeguard: when `WP_Setting::get()` (and `WP_Setting::set()`) is called while `self::$text_domain` is still unset, trigger `_doing_it_wrong()` (WP core's standard misuse-signaling mechanism — visible in `WP_DEBUG` environments, silent in production) naming the likely cause and fix, instead of silently falling through to an unprefixed option lookup.
- Add a formal, documented `WP_Setting::set_text_domain( string $domain ): void` static method — making the "manually via `$text_domain` assignment" escape hatch already mentioned in the property's docblock an explicit, discoverable API instead of an undocumented direct static-property write.
- Update the README to add an explicit callout: do not gate `WP_Settings` subclass construction behind `is_admin()` — construct it unconditionally (as the existing usage example already does); the class's own `admin_init` hook is already safely no-op outside wp-admin, so gating it yourself only breaks `WP_Setting::get()`/`::set()` everywhere else.

## Capabilities

### New Capabilities

- `settings-text-domain-safety`: Runtime misuse detection and a documented API for establishing `WP_Setting::$text_domain` outside of `WP_Settings` admin-page construction.

### Modified Capabilities

_None._ This is additive: existing behavior for correctly-configured consumers (constructing their `WP_Settings` subclass unconditionally, per the README's own example) is unchanged. Only the previously-silent misuse case gains a visible signal.

## Impact

- **Affected code**: `src/WP_Setting.php` (`get()`, `set()`, new `set_text_domain()`), `README.md`.
- **Consumers**: no breaking change for any consumer already following the documented unconditional-construction pattern. Consumers currently gating construction behind `is_admin()` (e.g. `seiler-locations`, and potentially other Seiler plugins/themes using this library — not yet audited) will start seeing a `_doing_it_wrong()` notice in debug environments, surfacing a bug they already have today rather than introducing a new one.
- **Out of scope**: auditing or fixing other consuming projects (`seiler-locations` and any others) that may have this same gating pattern — tracked separately, per project, once this library change ships and makes the misuse visible.

## 1. Explicit Text Domain Setter

- [x] 1.1 Add `WP_Setting::set_text_domain( string $domain ): void` to `src/WP_Setting.php`, reusing the same normalization `WP_Settings::__construct()` performs (extract a shared private/protected normalization step if that avoids duplicating logic, or call `WP_Setting::normalize_text_domain()` directly since it's already `public static`)
- [x] 1.2 Update the `$text_domain` property docblock to reference the new method instead of "manually via `$text_domain` assignment"

## 2. Misuse Detection

- [x] 2.1 In `WP_Setting::get()`, trigger `_doing_it_wrong( 'WP_Setting::get', <message explaining the cause and fix>, <library version> )` when `self::$text_domain` is empty, before/alongside the existing fallback behavior — return value unchanged
- [x] 2.2 In `WP_Setting::set()`, add the equivalent check
- [x] 2.3 PHPUnit coverage: `get()`/`set()` trigger the notice when domain unset (assert via `setExpectedIncorrectUsage()` or equivalent WP test-suite assertion) and do NOT trigger it once `set_text_domain()` or a `WP_Settings` construction has run; confirm return values are unchanged in both cases

## 3. Documentation

- [x] 3.1 Add a README callout: construct your `WP_Settings` subclass unconditionally (not gated by `is_admin()`) — its own `admin_init` hook is already safely inert outside wp-admin, and `WP_Setting::get()`/`::set()` need the domain set on every request, not just admin ones
- [x] 3.2 Document `WP_Setting::set_text_domain()` in the README as the alternative for consumers who don't want to construct a full `WP_Settings` object
- [x] 3.3 Add a CHANGELOG entry (if this project maintains one) describing the new notice and setter, and noting the misuse pattern it detects — deferred to the release-cut commit per project convention (CHANGELOG entries ship alongside the version bump/tag, not during feature implementation)

## 4. Validation

- [x] 4.1 Run the existing PHPUnit suite (`composer test`) and confirm no regressions
- [x] 4.2 Manually reproduce the original bug pattern (construct nothing, call `WP_Setting::get()` with `WP_DEBUG` on) and confirm the new notice fires with a clear, actionable message

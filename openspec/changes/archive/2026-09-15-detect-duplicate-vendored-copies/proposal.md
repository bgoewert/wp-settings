## Why

Every file guards itself with `class_exists()`, so a site running two plugins that each vendor this library unscoped ends up with one copy and one `WP_Setting::$text_domain`. `get()`, `set()`, `register_setting()` and `add_settings_field()` all read that static at call time, so whichever plugin constructed its `WP_Settings` subclass most recently owns all of them — one plugin's fields register under the other's prefix, and its reads resolve the other's options ([#28](https://github.com/bgoewert/wp-settings/issues/28)). Nothing errors. The site owner finds out months later, looking at options under a prefix that isn't theirs.

## What Changes

- The library detects when more than one unscoped copy is registered on the site and reports it with `_doing_it_wrong()`, naming the path and version of the copy in use and of each copy that lost. The check runs on `admin_init`, so the owner sees it on the first admin page load.
- README: a consumer vendoring this library must scope it, and two unscoped consumers on one site are unsupported — the same requirement Guzzle and the AWS SDK carry.

## Capabilities

### New Capabilities

- `duplicate-copy-detection`: how the library notices a second vendored copy on the same site and what it reports.

### Modified Capabilities
<!-- None. -->

## Impact

- `src/WP_Settings.php` — the detection, hooked from the constructor.
- No API change and no change to the static. Scoping remains the fix; this only makes the collision visible.

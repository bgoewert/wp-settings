## Why

`admin_menu()` built the parent slug and the capability from locals — `options-general.php` and `manage_options` — so a consumer whose page does not belong under Settings had to override the whole method ([#30](https://github.com/bgoewert/wp-settings/issues/30)). The override is a trap: the line it replaces is what assigns `$submenu_page_hook`, and that hook is what `enqueue_admin()` and the two screen checks compare against. Drop it and the page still renders and still saves, with no stylesheet, no scripts and no screen checks — the password field's Show button renders because its markup is unconditional and does nothing because its handler was never enqueued. Nothing warns. We shipped it that way and noticed when somebody pressed Show.

## What Changes

- `Parent` and `Capability` in the constructor's plugin data place the page and gate it. Registration stays the library's, so the hook, the `load-` action and the screen checks keep working.
- The capability also gates the page render, which was hardcoded to `manage_options` and would have refused the users a narrower capability was chosen for.
- A page whose submenu hook is empty at `admin_enqueue_scripts` is reported with `_doing_it_wrong()`, naming the override as the cause. Silence is what made this expensive.

## Capabilities

### New Capabilities

- `admin-page-placement`: where the settings page is registered, what capability it requires, and what is reported when a consumer registers it itself.

### Modified Capabilities
<!-- None. -->

## Impact

- `src/WP_Settings.php` — the two properties, the constructor keys, `admin_menu()`, `menu_page_callback()` and the notice.
- Defaults are unchanged: a consumer passing neither key keeps Settings and `manage_options`.

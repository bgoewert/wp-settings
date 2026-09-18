## Context

See proposal.md — Why. The override is the only lever the library offered, and it silently disables three features that read `$submenu_page_hook`.

## Goals / Non-Goals

**Goals:**
- Placement and capability are data a consumer passes, not a method it reimplements.
- A consumer who overrides anyway hears about it on the first admin page load.

**Non-Goals:**
- A top-level menu page. `add_menu_page()` is a different registration with an icon and a position; this is about which parent the submenu hangs from.
- Per-tab or per-field capabilities. The page is one capability; a table already carries its own.

## Decisions

**Constructor keys, not a filter pair.** `Name` and `TextDomain` already arrive as plugin data, and placement is the same kind of fact. A filter would have to run late enough to matter and would put a consumer's own page behind a hook any other plugin can answer.

**Properties with defaults, so a subclass can set them directly.** `text_domain` already works that way — a child assigns the property before calling the parent constructor. Defaults live on the properties, and a non-empty string in the plugin data overrides them; an empty string or a non-string is ignored rather than registering an unreachable page.

**The capability gates the render too.** `menu_page_callback()` checked `manage_options` outright, so a page registered under a narrower capability would show its menu entry and then return nothing. Log viewing and clearing stay at `manage_options`: clearing the log is not the same privilege as editing the fields.

**Report from `enqueue_admin()`, not from `admin_menu()`.** The condition is "registration did not go through the library", which is only knowable after `admin_menu` has run; `admin_enqueue_scripts` is the first hook after it that the library is already on, and it is exactly where the loss shows up. The slug-already-registered branch sets a flag so the intended duplicate skip stays silent.

## Risks / Trade-offs

- **A consumer already overriding `admin_menu()` gets a new notice** → that is the point, and it is `_doing_it_wrong()`, so it is visible under `WP_DEBUG` only.
- **The notice cannot tell an override from a consumer who registers the page under a different slug entirely** → both lose the same three features, so both deserve it.

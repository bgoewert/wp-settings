# WordPress Settings Library

Simple, reusable WordPress settings library with support for collapsible field groups.

## Installation

Add the GitHub repository to your project's `composer.json`:

```json
{
  "repositories": [
    { "name": "bgoewert/wp-settings", "type": "vcs", "url": "https://github.com/bgoewert/wp-settings.git" }
  ]
}
```

Then require the package:

```bash
composer require bgoewert/wp-settings
```

## Usage

```php
use BGoewert\WP_Settings\WP_Settings;
use BGoewert\WP_Settings\WP_Setting;

class My_Settings extends WP_Settings
{
    public function __construct()
    {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_data = get_plugin_data(MY_PLUGIN_FILE, false, false);

        $this->sections = array(
            array(
                'name'      => 'General Settings',
                'slug'      => 'general_settings',
                'tab'       => 'general',
                'tab_name'  => 'General',
                'callback'  => '__return_false',
            ),
        );

        // Alternatively, use array keys as slugs (v2.7.0+):
        // $this->sections = array(
        //     'general_settings' => array(
        //         'name'      => 'General Settings',
        //         'tab'       => 'general',
        //         'tab_name'  => 'General',
        //         'callback'  => '__return_false',
        //     ),
        // );

        $this->settings = array(
            'my_option' => new WP_Setting(
                'my_option',       // option slug
                'My Option',       // title
                'select',          // type
                'general',         // page/tab
                'general_settings',// section
                '400px',           // width
                'Choose a value.', // description
                false,             // required
                'default',         // default value
                null,              // custom render callback
                array(
                    'sanitize_callback' => 'sanitize_text_field',
                    'options' => array(
                        'option_a' => 'Option A',
                        'option_b' => 'Option B',
                    ),
                )
            ),
        );

        parent::__construct($plugin_data);
    }
}

new My_Settings();
```

Tab labels default to `ucwords(tab)` but you can override the display label per tab with `tab_name`.

### Construct Unconditionally — Do Not Gate Behind `is_admin()`

Always construct your `WP_Settings` subclass unconditionally, as in the example above (`new My_Settings();` at file scope) — never gate it behind `if ( is_admin() )`. The class's own `admin_init` hook is already inert outside wp-admin regardless of when it's registered, so gating construction yourself buys nothing there. What it *does* break: `WP_Setting::$text_domain` is only ever set as a side effect of that construction, and `WP_Setting::get()`/`::set()` need it set on **every** request — frontend, REST, WP-CLI — not just admin ones. Gate the construction and those calls silently fall through to reading/writing an unprefixed, nonexistent option key, always returning your hardcoded default instead of the admin-configured value, with no error or warning to indicate why.

If you have a genuine reason to avoid constructing the full `WP_Settings` object outside of wp-admin (e.g. a subclass constructor with its own non-admin-safe side effects), call `WP_Setting::set_text_domain( $domain )` instead — see below.

### `WP_Setting::set_text_domain()`

`WP_Setting::set_text_domain( string $domain ): void` sets `WP_Setting::$text_domain` directly, normalizing hyphens to underscores exactly as `WP_Settings::__construct()` does — without requiring a `WP_Settings` subclass at all:

```php
use BGoewert\WP_Settings\WP_Setting;

WP_Setting::set_text_domain('my-plugin');

$value = WP_Setting::get('my_option', 'default');
```

Calling `WP_Setting::get()` or `::set()` while `$text_domain` is still unset triggers a `_doing_it_wrong()` notice (visible under `WP_DEBUG`, silent in production) naming the likely cause and fix; the call still returns its existing fallback value either way.

## Built-In Logging

You can opt into a built-in `Logging` tab with plugin log files, retention settings, and an admin log viewer.

```php
class My_Settings extends WP_Settings
{
    public function __construct()
    {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $plugin_data = get_plugin_data(MY_PLUGIN_FILE, false, false);

        $this->sections = array(
            'general_settings' => array(
                'name' => 'General Settings',
                'tab' => 'general',
                'callback' => '__return_false',
            ),
        );

        $this->settings = array(
            'my_option' => new WP_Setting('my_option', 'My Option', 'text', 'general', 'general_settings'),
        );

        $this->logging(array(
            'plugin_dir_path' => plugin_dir_path(MY_PLUGIN_FILE),
            'retention_days_default' => 14,
            'default_level' => 'error',
        ));

        parent::__construct($plugin_data);
    }
}
```

What it adds:

- A `Logging` tab with settings for enable/disable, destination, level, retention days, and auto-refresh
- Plugin log files in `<plugin-dir>/logs`
- Daily file rotation using `<text-domain>-YYYY-MM-DD.log`
- A built-in viewer for plugin log files with refresh and clear actions

Notes:

- Logging is disabled by default until the `Enable Logging` setting is saved
- `log_destination` can write to the plugin log file or WordPress `debug.log`
- The built-in viewer only displays plugin log files, not WordPress `debug.log`
- Crypto failure logging records only generic operation metadata, not encrypted or decrypted values

## Field Types

Standard: `text`, `email`, `url`, `number`, `color`, `textarea`, `checkbox`, `select`, `radio`, `password`, `hidden`, `sortable`, `table`, `field_map`

**Color**: Renders `<input type="color">`. The default sanitize callback accepts only what that control submits — `#rgb` or `#rrggbb` — and rejects anything else (including `rgb()`, `hsl()` and named colors) by storing `false`, the same way `url` and `email` reject invalid input. Pass your own `sanitize_callback` if you need to accept other CSS color syntaxes; `WP_Setting::sanitize_color()` and `WP_Setting::is_valid_hex_color()` are public if you want to build on them.

**Advanced**: Collapsible `<details>` section containing child settings.

**Fieldset**: Visual grouping of child settings with `<fieldset>` element.

Both container types (`advanced` and `fieldset`) render each child through that child's own field renderer — the same path a top-level field uses — so a child can be **any** supported type, including `repeater`, `field_map`, `radio`, `richtext`, `sortable`, or a nested `advanced`/`fieldset`. A child's custom `callback` (if provided) is invoked with its `args`, exactly as at top level. Top-level containers span the full width of the settings table.

**Table**: Embeds a `WP_Settings_Table` instance within a section alongside other settings.

**Field Map**: Dynamic add/remove rows for mapping source fields to destination fields.

### Input Attributes

Text-like fields (`text`, `email`, `url`, `number`, `password`) render these `$args` keys directly onto the `<input>`: `min`, `max`, `step`, `pattern`, `minlength`, `maxlength`, `size`, `autocomplete`.

```php
new WP_Setting(
    'cache_duration', 'Cache Duration (seconds)', 'number', 'settings', 'section',
    '10ch', 'How long to cache API responses.', false, 3600, null,
    array('min' => 60, 'step' => 60)
);
```

Empty strings, `null` and non-scalar values are skipped; `0` is rendered (`min="0"`).

These are browser-side validation hints only. Saving still goes through the field's sanitize callback, and the default `number` callback rejects non-numerics but does **not** enforce `min`/`max` — supply a custom `sanitize_callback` if you need a hard bound.

`textarea` fields accept `rows`, `class` and `placeholder` the same way.

#### Boolean Attributes

`readonly` is a boolean attribute, so it is rendered by **presence**: pass anything truthy and the bare attribute is emitted, pass anything falsy (`false`, `0`, `'0'`, `''`, `null`) or omit the key and nothing is emitted. It is never rendered as `readonly="…"`, because a browser treats `readonly="0"` as read-only just like `readonly`.

```php
new WP_Setting(
    'quote_email', 'Quote Email', 'text', 'settings', 'section',
    '30ch', 'Managed by the active quote plugin.', false, null, null,
    array('readonly' => $quote_plugin_active)
);
```

Both text-like fields and `textarea` accept `readonly`.

`disabled` is **not** supported. Browsers omit disabled inputs from form submission and `wp-admin/options.php` writes every registered option from `$_POST`, so a disabled field would blank its own stored option on the next save. Use `readonly`: the input stays uneditable but still submits its current value.

A read-only field is uneditable in the browser only. A crafted POST can still change the value, so keep validating in `sanitize_callback` if the value must not change.

### Row Labels

WordPress renders each settings row as `<tr><th>title</th><td>field</td></tr>`, and `do_settings_fields()` wraps that `<th>` text in `<label for="…">` only when the field declares `label_for` in its `$args`. Fields set it for you, pointing at the control's own id (the field slug), so the visible title is the control's accessible name — without it every control on the screen has a heading assistive technology cannot associate with its input (WCAG 1.3.1, 4.1.2; axe `label`, and `select-name` for selects).

This applies to every type rendered as one control carrying the slug: the text-like inputs, `textarea`, `select`, `checkbox`, and any custom input type. It is skipped for the types listed in `WP_Setting::UNLABELABLE_TYPES`, where no single control answers to the slug and a label would be an orphan — `radio` (one wrapped label per option), `sortable`, `table`, `field_map`, `repeater` (a control per row), `advanced`, `fieldset` (children label themselves), `hidden`, and `richtext` (TinyMCE hides the textarea the id belongs to).

To point the row label somewhere else, pass your own `label_for`; to suppress it, pass an empty string:

```php
new WP_Setting(
    'api_mode', 'API Mode', 'text', 'settings', 'section',
    null, null, false, null, null,
    array('label_for' => '') // opt out of the generated <label for>
);
```

Container children follow the same rule: `advanced` and `fieldset` bind each child's heading to that child's control when the child is labelable, and render a plain heading when it is not. Use `WP_Setting::renders_labelable_control()` if you build headings yourself.

### Advanced Field Example

```php
use BGoewert\WP_Settings\WP_Setting;

$child1 = new WP_Setting('sync_prices', 'Sync Prices', 'checkbox', 'settings', 'section', '500px', 'Enable price sync.', false, 'yes');
$child2 = new WP_Setting('filter_field', 'Filter Field', 'text', 'settings', 'section', '500px', 'Field name for filtering.', false, '');

$advanced = new WP_Setting(
    'advanced_settings', 'Advanced Settings', 'advanced', 'settings', 'section',
    '500px', 'Configure advanced options.', false, '', null, array('children' => array($child1, $child2))
);

// Or expanded by default:
$expanded = new WP_Setting(
    'field_mapping', 'Field Mapping', 'advanced', 'settings', 'section',
    null, 'Map source fields to destination fields.', false, '', null,
    array('children' => array($child1, $child2), 'collapsed' => false)
);
```

Renders as collapsible `<details>` section. Set `'collapsed' => false` to expand by default (defaults to `true` if not specified).

### Fieldset Field Example

```php
use BGoewert\WP_Settings\WP_Setting;

// A repeater grouped under a fieldset legend.
$filters = new WP_Setting(
    'sync_filters', 'Sync Filters', 'repeater', 'settings', 'section',
    null, 'One condition per row.', false, null, null,
    array('children' => array(
        array('name' => 'field', 'label' => 'Salesforce Field', 'type' => 'text'),
        array('name' => 'values', 'label' => 'Values', 'type' => 'textarea'),
    ))
);

$fieldset = new WP_Setting(
    'filter_group', 'Sync Filters', 'fieldset', 'settings', 'section',
    null, 'Filter which products sync.', false, null, null,
    array(
        'children'          => array($filters),
        'hide_child_labels' => true,
    )
);
```

Set `'hide_child_labels' => true` to drop each child's label column and let the control span the full width — useful when a child's title merely repeats the fieldset legend (e.g. a lone repeater). The `<legend>` then serves as the group's label. Omit it (the default) to keep per-child labels.

**Hidden fields**: Store values without rendering table rows.

```php
$hidden = new WP_Setting('internal_setting', '', 'hidden', 'settings', 'section', '', '', false, 'value');
```

### Sortable Field Example

```php
new WP_Setting(
    'featured_order',
    'Featured Order',
    'sortable',
    'general',
    'general_settings',
    null,
    'Drag or enter a number to reorder items.',
    false,
    array('item_b', 'item_a'),
    null,
    array(
        'options' => array(
            'item_a' => 'Item A',
            'item_b' => 'Item B',
            'item_c' => 'Item C',
        ),
    )
);
```

**With badges and custom classes:**

```php
new WP_Setting(
    'field_order',
    'Field Order',
    'sortable',
    'general',
    'general_settings',
    null,
    'Drag or enter a number to reorder fields.',
    false,
    array('first_name', 'last_name', 'custom_field'),
    null,
    array(
        'options' => array(
            'first_name'   => 'First Name',
            'last_name'    => 'Last Name',
            'custom_field' => 'Custom Field',
        ),
        'item_meta' => array(
            'first_name' => array(
                'badge'       => 'Default',
                'badge_class' => 'default',
                'class'       => 'default-field',
            ),
            'last_name' => array(
                'badge'       => 'Default',
                'badge_class' => 'default',
                'class'       => 'default-field',
            ),
            'custom_field' => array(
                'badge'       => 'Custom',
                'badge_class' => 'custom',
                'class'       => 'custom-field',
            ),
        ),
    )
);
```

### Table Field Example

```php
use BGoewert\WP_Settings\WP_Setting;
use BGoewert\WP_Settings\WP_Settings_Table;

// Create a table instance
$my_table = new WP_Settings_Table(array(
    'id'          => 'items',
    'tab'         => 'general',
    'option'      => 'items',
    'title'       => 'Item Management',
    'description' => 'Manage your items.',
    'columns'     => array(
        array('key' => 'name', 'label' => 'Name', 'field' => 'name'),
        array('key' => 'value', 'label' => 'Value', 'field' => 'value'),
    ),
    'fields'      => array(
        new WP_Setting('name', 'Item Name', 'text', 'general', 'items_section'),
        new WP_Setting('value', 'Item Value', 'number', 'general', 'items_section'),
    ),
));

// Embed the table in a section alongside other settings
new WP_Setting(
    'items_table',
    'Items',
    'table',
    'general',
    'general_settings',
    null,
    'Configure your items below.',
    false,
    null,
    null,
    array(
        'table' => $my_table,
    )
);
```

This allows you to place tables within sections, rendered alongside regular settings fields.

### Field Map Example

```php
use BGoewert\WP_Settings\WP_Setting;

new WP_Setting(
    'field_mapping',
    'Field Mapping',
    'field_map',
    'settings',
    'section',
    null,
    'Map source fields to destination fields.',
    false,
    null,
    null,
    array(
        'options' => array(
            'first_name' => 'First Name',
            'last_name'  => 'Last Name',
            'email'      => 'Email Address',
            'phone'      => 'Phone Number',
        ),
    )
);
```

The `field_map` type provides dynamic add/remove rows where users can:
- Select a source field from dropdown (left side)
- Enter a destination field name in text input (right side)
- Add/remove mapping rows as needed
- Map multiple source fields to different destinations (useful for combining values)

Stored as array: `[['key' => 'first_name', 'value' => 'FirstName'], ['key' => 'email', 'value' => 'Email'], ...]`

## Settings Tables

Use `WP_Settings_Table` to create a reusable table + modal editor stored as a single option array.

```php
use BGoewert\WP_Settings\WP_Settings_Table;

$this->tables = array(
    new WP_Settings_Table(
        array(
            'id'          => 'fees',
            'tab'         => 'fees',
            'option'      => 'fees',
            'title'       => 'Fee Management',
            'description' => 'Create and manage fees.',
            'status_key'  => 'enabled',
            'statuses'    => array(
                'enabled'  => array('label' => 'Enabled'),
                'disabled' => array('label' => 'Disabled'),
            ),
            'columns'     => array(
                array('key' => 'status', 'label' => 'Status', 'type' => 'status'),
                array('key' => 'name', 'label' => 'Name', 'field' => 'name'),
                array('key' => 'type', 'label' => 'Type', 'field' => 'type'),
                array('key' => 'amount', 'label' => 'Amount', 'field' => 'amount'),
            ),
            'fields'      => array(
                new WP_Setting('name', 'Fee Name', 'text', 'fees', 'fees_section'),
                new WP_Setting('type', 'Fee Type', 'select', 'fees', 'fees_section', null, null, false, null, null, array(
                    'options' => array(
                        'percentage' => 'Percentage',
                        'fixed' => 'Fixed Amount',
                    ),
                )),
                new WP_Setting('amount', 'Amount', 'number', 'fees', 'fees_section'),
                new WP_Setting('enabled', 'Enabled', 'checkbox', 'fees', 'fees_section'),
            ),
        )
    ),
);
```

Tables render in the specified tab, support AJAX CRUD, bulk actions, inline status toggles, and a non-JS fallback form.

## Conditional Visibility

Fields can be conditionally shown/hidden based on other field values using the `conditions` key in the args array. This works for both regular settings forms and `WP_Settings_Table` modals.

```php
new WP_Setting(
    'salesforce_oid',
    'Organization ID',
    'text',
    'feeds',
    'feeds_section',
    null,
    'Your Salesforce Organization ID',
    false,
    null,
    null,
    array(
        'conditions' => array(
            array(
                'field'    => 'connection_type',
                'operator' => 'in',
                'value'    => array('salesforce_lead', 'salesforce_case'),
            ),
        ),
    )
)
```

**Supported operators:**

| Operator | Description |
|----------|-------------|
| `equals` | Field value equals the target value |
| `not_equals` | Field value does not equal the target value |
| `in` | Field value is one of the values in the target array |
| `not_in` | Field value is not in the target array |
| `empty` | Field value is empty |
| `not_empty` | Field value is not empty |

Multiple conditions are combined with AND logic (all must be true for the field to be visible).

## Autoloading

WordPress stores options in the `wp_options` table, which has an `autoload` column. Options marked for autoloading are fetched in a single query on every page load. Autoloading too many options — especially large ones — degrades site performance.

Set `autoload` via the constructor's 12th argument or the `autoload` key in `$args`:

```php
// Via dedicated param (recommended for clarity)
new WP_Setting(
    'license_key', 'License Key', 'text', 'general', 'general_settings',
    null, null, false, null, null, array(), true  // autoload = true
);

// Via args key
new WP_Setting(
    'sync_log', 'Sync Log', 'textarea', 'general', 'general_settings',
    null, null, false, null, null, array('autoload' => false)
);
```

**When to autoload (`true`):**
- Options read on the **frontend** (e.g. license status, global feature flags, API base URLs)
- Options accessed on **every admin page** (e.g. plugin-wide preferences)

**When NOT to autoload (`false`):**
- Options only read on **specific admin pages** (e.g. per-page settings, API credentials, logs)
- **Large values** like serialized arrays, HTML blobs, or cached remote data
- Options accessed via `WP_Setting::get()` in a targeted context

When `null` (default), WordPress decides — which defaults to autoloading in most WP versions, so prefer explicitly setting `false` for admin-only options.

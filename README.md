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

        parent::__construct($plugin_data);

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
    }
}

new My_Settings();
```

Tab labels default to `ucwords(tab)` but you can override the display label per tab with `tab_name`.

## Field Types

Standard: `text`, `email`, `url`, `number`, `textarea`, `checkbox`, `select`, `radio`, `password`, `hidden`, `sortable`, `table`

**Advanced**: Collapsible `<details>` section containing child settings.

**Table**: Embeds a `WP_Settings_Table` instance within a section alongside other settings.

### Advanced Field Example

```php
use BGoewert\WP_Settings\WP_Setting;

$child1 = new WP_Setting('sync_prices', 'Sync Prices', 'checkbox', 'settings', 'section', '500px', 'Enable price sync.', false, 'yes');
$child2 = new WP_Setting('filter_field', 'Filter Field', 'text', 'settings', 'section', '500px', 'Field name for filtering.', false, '');

$advanced = new WP_Setting(
    'advanced_settings', 'Advanced Settings', 'advanced', 'settings', 'section',
    '500px', 'Configure advanced options.', false, '', null, array('children' => array($child1, $child2))
);
```

Renders as collapsible section with hidden inputs for value storage and visible controls inside the collapsed area.

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

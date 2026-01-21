# WordPress Settings Library

Simple, reusable WordPress settings library with support for collapsible field groups.

## Installation

Add the GitHub repository to your project's `composer.json`:

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/bgoewert/wp-settings.git" }
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

Standard: `text`, `email`, `url`, `number`, `textarea`, `checkbox`, `select`, `radio`, `password`, `hidden`

**Advanced**: Collapsible `<details>` section containing child settings.

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

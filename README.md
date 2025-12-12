# WordPress Settings Library

Simple, reusable WordPress settings library with support for collapsible field groups.

## Installation

```bash
composer require bgoewert/wp-settings
```

**Requirements:** PHP 7.2+ or 8.0+

## Usage

```php
use BGoewert\WP_Settings\WP_Settings;
use BGoewert\WP_Settings\WP_Setting;

// Create settings instance
$settings = new WP_Settings('my-plugin', 'My Plugin Settings');

// Add a setting
$setting = new WP_Setting(
    'my-plugin',           // text domain
    'my_option',           // option slug
    'My Option',           // title
    'text',                // type
    'general',             // page
    'main',                // section
    '400px',               // width
    'Enter a value.',      // description
    false,                 // required
    'default'              // default value
);

$settings->add_setting($setting);
$settings->init();
```

## Field Types

Standard: `text`, `textarea`, `checkbox`, `select`, `radio`, `number`, `hidden`

**Advanced**: Collapsible `<details>` section containing child settings.

### Advanced Field Example

```php
use BGoewert\WP_Settings\WP_Setting;

$child1 = new WP_Setting('my-plugin', 'sync_prices', 'Sync Prices', 'checkbox', 'settings', 'section', '500px', 'Enable price sync.', false, 'yes');
$child2 = new WP_Setting('my-plugin', 'filter_field', 'Filter Field', 'text', 'settings', 'section', '500px', 'Field name for filtering.', false, '');

$advanced = new WP_Setting(
    'my-plugin', 'advanced_settings', 'Advanced Settings', 'advanced', 'settings', 'section',
    '500px', 'Configure advanced options.', false, '', null, ['children' => [$child1, $child2]]
);
```

Renders as collapsible section with hidden inputs for value storage and visible controls inside the collapsed area.

**Hidden fields**: Store values without rendering table rows.

```php
$hidden = new WP_Setting('my-plugin', 'internal_setting', '', 'hidden', 'settings', 'section', '', '', false, 'value');
```

## Why

A field that is inert without a dependency cannot be shown disabled ([#34](https://github.com/bgoewert/wp-settings/issues/34)). Browsers omit a disabled control from the POST and options.php writes every registered option from what is left, so a disabled field blanks its own stored value on the next save. `readonly` does nothing on a checkbox. Seiler Products' "Hide Customer Quote Screens" stays clickable while YITH Request a Quote is inactive and can only say so in its description.

## What Changes

- A `disabled` arg renders the field's controls inside a disabled fieldset.
- The field's registered sanitizer answers with the stored value, and the library's own save skips the field.

## Capabilities

### New Capabilities

- `disabled-fields`: a field shown disabled whose stored value survives every save.

### Modified Capabilities
<!-- None. -->

## Impact

- `src/WP_Setting.php` — the bound render path, `register_option()` and `save()`.

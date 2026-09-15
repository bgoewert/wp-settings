## Why

Encryption is a per-call flag: `WP_Setting::set($name, $value, true)` to write, `WP_Setting::get($name, false, true)` to read. Every caller has to remember it in both directions, and neither mistake fails loudly — a `set()` missing the flag writes the secret in plaintext, and a `get()` missing it renders base64 into the input. The library also has no way to know a field is encrypted, so it cannot tell an admin that a value stopped decrypting.

## What Changes

- A field can declare `'encrypted' => true`. Its value is sanitized, then encrypted on save, and decrypted when the field renders.
- A declared field whose stored value will not decrypt renders empty with the key-change notice rather than the ciphertext.
- The per-call flags on `get()`/`set()` stay, for values that are not fields.

## Capabilities

### New Capabilities
<!-- None. -->

### Modified Capabilities

- `credential-encryption`: encryption becomes a property of a field declaration, not only of an individual call, and the reporting requirement gains the renderer that acts on it.

## Impact

- `src/WP_Setting.php` — the `encrypted` arg, a single read path for rendered values, and the sanitizer wrapper that stores ciphertext.
- Nothing stored changes shape; the declaration lives in the field definition, not the database.

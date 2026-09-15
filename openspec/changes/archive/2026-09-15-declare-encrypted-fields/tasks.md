## 1. Declaration

- [x] 1.1 Accept `encrypted` in the field args and expose it on the setting
- [x] 1.2 Wrap the registered sanitizer so a declared field stores ciphertext, skipping a value already under the current key

## 2. Rendering

- [x] 2.1 Read every rendered value through one path that decrypts a declared field
- [x] 2.2 Render the key-change message and an empty control when a declared value will not decrypt

## 3. Tests and documentation

- [x] 3.1 Integration tests for the save path: stored ciphered, no double encrypt, empty stays empty, undeclared untouched
- [x] 3.2 Unit tests for the render path: plaintext rendered, key change reported, undeclared field untouched
- [x] 3.3 README and CHANGELOG

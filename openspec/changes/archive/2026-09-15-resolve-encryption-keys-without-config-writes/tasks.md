## 1. Key resolution

- [x] 1.1 Replace `get_default_key()`/`get_default_nonce()` with a single resolver returning key, source (`constant`/`env`/`salt`), and fingerprint
- [x] 1.2 Resolve in order: `defined()`, `getenv()`, then `LOGGED_IN_KEY`/`NONCE_KEY`, keeping the string guarantee under every branch
- [x] 1.3 Delete `write_config_constant()`, the `wp-config.php` grep, and the `is_writable()` provisioning branches
- [x] 1.4 Accept explicit key and nonce values in the constructor so a legacy key can be instantiated alongside the current one
- [x] 1.5 Unit tests: each resolution source wins in order, absent sources still yield strings, a writable config file is never modified

## 2. Fingerprinted payloads

- [x] 2.1 Add the `wps.aesgcm.v2:<fp>:` prefix, writing the truncated key fingerprint on encrypt
- [x] 2.2 Dispatch v2, v1, and bare sodium payloads in `decrypt()`
- [x] 2.3 Expose a check that reports whether a payload was written under the current key, without decrypting it
- [x] 2.4 Unit tests: v1 and sodium values still round trip, a v2 value written under another key is identified as such, a collision-free fingerprint is stable across instances

## 3. Reporting a changed key

- [x] 3.1 Have `try_decrypt()` distinguish a key mismatch from a corrupt payload and carry that in the thrown exception
- [x] 3.2 Expose the "the encryption key changed, re-enter this value" message for a mismatch, keeping the existing wording for corruption — the library has no encrypted-field renderer of its own, so the consuming plugin decides where it shows
- [x] 3.3 Unit test: a value encrypted under one key reports a key change rather than a credential failure

## 4. Rewrap routine

- [x] 4.1 Add the public rewrap entry point taking legacy key material, legacy nonce, and setting names, returning a per-name result
- [x] 4.2 Short-circuit values already carrying the current fingerprint, and leave a value untouched when the legacy key cannot read it
- [x] 4.3 Unit tests: a full pass migrates, a second pass is a no-op, one unreadable setting does not stop the rest, nothing is double-encrypted

## 5. Documentation

- [x] 5.1 README: the two constants, `openssl rand -base64 32`, `.env` and constant examples, and the salt fallback as the default
- [x] 5.2 README: the upgrade-hook snippet for the rewrap, and the note that downgrading after a rewrap is not supported
- [x] 5.3 CHANGELOG entry covering the removed config-file write and the new rewrap routine

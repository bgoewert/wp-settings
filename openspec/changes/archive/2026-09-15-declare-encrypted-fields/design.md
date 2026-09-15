## Context

See proposal.md — Why. `register_setting()` already hangs the field's sanitizer on `sanitize_option_{$option}`, which every writer goes through, and the render paths all read the stored value through one call.

## Goals / Non-Goals

**Goals:**
- One declaration that both the save path and the render path obey.
- No change to what is stored, beyond the value being ciphertext.

**Non-Goals:**
- Teaching `WP_Setting::get()` to consult field declarations. A runtime read outside the renderer still passes the decrypt flag.
- Encrypting anything a field did not declare.

## Decisions

**Encryption wraps the sanitizer rather than replacing it.** Sanitizing first keeps the field's declared rules meaningful — an email field still rejects a non-email — and ciphertext is opaque to every sanitizer anyway. Hanging it on `register_setting()` means any writer is covered, including one that never calls this library.

**The wrapper skips a value already under the current key.** Otherwise a resave, or a migration writing ciphertext back through `set()`, would encrypt it twice. The fingerprint makes that a string comparison rather than a trial decrypt.

**One read path.** The render entry points share a `current_value()` that decrypts when the field declares it and records the failure message for the renderer. A per-renderer decrypt would have to be repeated in every field type.

## Risks / Trade-offs

- **Foreign ciphertext written through the filter is wrapped rather than recognized** → nothing can tell it from a plaintext that happens to look encrypted, and wrapping is the safe default; a value from an old key is migrated with the rewrap routine, which reads it before storing.
- **A runtime read that forgets the decrypt flag still returns ciphertext** → unchanged from today, and named as a non-goal.

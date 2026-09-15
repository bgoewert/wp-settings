## Context

See proposal.md — Why. Two constraints shape the approach. There is no registry of which settings are encrypted: `WP_Setting::set()` takes `$encrypt` per call, so nothing in the library can enumerate encrypted values. And the current ciphertext carries no key identity — `wps.aesgcm.v1:` marks the cipher, not the key — so a failed decrypt is indistinguishable from a wrong key.

## Goals / Non-Goals

**Goals:**
- One resolution path that covers `.env`, Docker, hosting panels, and plain constants.
- A stored value that can say which key wrote it.
- A migration a consuming plugin can run from `upgrader_process_complete` or its own version-compare hook, idempotently.

**Non-Goals:**
- Key generation, storage, or rotation scheduling — those belong to the deploy tool.
- Automatic discovery of encrypted settings. The caller names them.
- Re-encrypting sodium payloads to openssl as a side effect; format preference stays where it is.

## Decisions

**Fingerprint lives in the payload, not in an option.** A short truncated hash of the resolved key is written into the ciphertext prefix (`wps.aesgcm.v2:<fp>:<payload>`). A per-value marker survives a partial rewrap, needs no extra option row, and makes idempotency a string comparison. An option holding "the current key fingerprint" was the alternative; it goes stale the moment one value is rewrapped and another is not. Truncation is deliberate — the fingerprint identifies a key, it is not a verifier, and a full hash of secret material stored next to the ciphertext is a gift to an offline attacker.

**v1 and bare sodium payloads stay readable.** `decrypt()` already dispatches on the prefix. v2 adds a branch; nothing else moves. A v1 value that decrypts successfully is left alone — rewriting it on read would mean a write on every page load for sites that never migrate.

**Resolution returns a value object, not a string.** The key, the source it came from (`constant`, `env`, `salt`), and its fingerprint travel together, because the "the key changed" message needs to say which source is in play to be actionable.

**The rewrap routine takes explicit legacy material and an explicit setting list.** Signature shape: the legacy key and nonce as raw values, the setting names as an array, returning a per-name result. Nothing is inferred. A routine that guessed the legacy key would silently do nothing on the one site where it mattered.

## Risks / Trade-offs

- **The caller passes the wrong legacy key and every setting fails** → per-setting results report failure and nothing is written; the pass is repeatable with the right key.
- **A plugin runs the rewrap on every request instead of on upgrade** → values already carrying the current fingerprint short-circuit before any crypto, so the cost is a string compare. Documented as an upgrade hook regardless.
- **Dropping the config-file write is breaking for a site that relied on auto-provisioning** → those sites already have an executed `define()`, so they keep resolving through `defined()`; only a site whose write never succeeded changes behavior, and that site was already on the salt fallback.
- **Truncated fingerprints collide** → a collision means one misreported diagnostic message, never a wrong decrypt; the ciphertext still authenticates.

## Migration Plan

1. Ship the resolution change and v2 payloads. Existing values keep decrypting.
2. Consuming plugins that carry a custom key constant need no action.
3. A plugin moving off a legacy constant calls the rewrap routine from its upgrade hook with the old value, then removes the constant in a later release.
4. Rollback: an older library version still reads v1 but not v2, so a downgrade after a rewrap is not safe. Called out in the README.

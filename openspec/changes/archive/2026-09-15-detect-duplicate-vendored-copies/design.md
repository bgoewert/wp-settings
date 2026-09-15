## Context

See proposal.md — Why. The `class_exists()` guards cannot detect this themselves: under PSR-4 autoloading the second copy's file is never included, because PHP only calls an autoloader for a class that is not already declared. The guards only fire when files are required eagerly.

## Goals / Non-Goals

**Goals:**
- Name both copies, with versions, on the first admin page load.
- Nothing changes about which copy wins or how the static behaves.

**Non-Goals:**
- Making two unscoped copies work. They cannot; scoping is the fix.
- Detecting copies that are vendored but never registered with an autoloader.

## Decisions

**Ask the registered autoloaders, not the filesystem.** Every Composer `ClassLoader` exposes `getPrefixesPsr4()`, so the set of directories claiming this namespace is already enumerable in memory. Scanning `wp-content` for the package directory would find copies nobody loaded and miss copies outside it.

**Compare canonical directories.** One directory registered by two autoloaders is still one copy; `realpath()` collapses that, and also collapses a symlinked path onto its target.

**Read the version from each copy's `composer.json`, on the unhappy path only.** A `VERSION` constant in the source drifts from the released tag and would have to be a second thing to remember at release. Reading the manifest of the *other* copy is the only way to name its version at all, since its classes are never loaded. Unreadable or absent manifest reports as unknown rather than failing.

**Run on `admin_init`, from the constructor.** Autoload time is too early — the other plugin's autoloader may not be registered yet. The constructor already runs unconditionally on every request, and the check itself is one pass over the autoloader list.

## Risks / Trade-offs

- **A consumer using a classmap or a plain `require` registers no PSR-4 prefix** → that copy is invisible to the check, and the collision is as silent as it is today; the README requirement is what covers it.
- **The notice is only visible with `WP_DEBUG`** → same as every other `_doing_it_wrong()` in the library, and the audience is the developer who vendored it.

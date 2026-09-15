## 1. Detection

- [x] 1.1 Enumerate the directories registering the library namespace from the registered Composer autoloaders, canonicalized and deduplicated
- [x] 1.2 Read a copy's version from its `composer.json`, reporting unknown when it cannot be read
- [x] 1.3 Report through `_doing_it_wrong()` naming the copy in use and each copy that lost, once per request
- [x] 1.4 Hook the check from the `WP_Settings` constructor on `admin_init`

## 2. Tests and documentation

- [x] 2.1 Unit tests: two copies report, one copy is silent, a repeated registration of one directory is silent, an unreadable version reports unknown
- [x] 2.2 README: vendoring this library requires scoping it, and two unscoped consumers on one site are unsupported

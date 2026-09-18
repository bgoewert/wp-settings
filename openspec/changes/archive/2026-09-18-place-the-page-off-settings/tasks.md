## 1. Placement

- [x] 1.1 Add the parent slug and capability as properties with the current values as defaults
- [x] 1.2 Read `Parent` and `Capability` from the plugin data, ignoring an empty or non-string value
- [x] 1.3 Register the page and the duplicate check against the configured parent and capability
- [x] 1.4 Gate the page render on the configured capability

## 2. Reporting

- [x] 2.1 Report an empty submenu page hook at `admin_enqueue_scripts` with `_doing_it_wrong()`, once per request
- [x] 2.2 Stay silent when the library skipped registration because the slug was already there

## 3. Tests and documentation

- [x] 3.1 Unit tests: the defaults, a custom parent and capability, an empty value, the duplicate skip under a custom parent, the notice and its two silent cases
- [x] 3.2 README: where the page lives, and why overriding `admin_menu()` is what the keys replace

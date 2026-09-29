## 1. Field actions

- [x] 1.1 Read and validate the `actions` arg
- [x] 1.2 Render a button per permitted action, named after the field, and queue its form
- [x] 1.3 Place the buttons beside a text-like input unless `actions_position` is `below`
- [x] 1.4 Print the queued forms on `admin_footer` with the action, the slug and a nonce
- [x] 1.5 Bracket `admin_post_{action}` with the nonce and capability check and the redirect

## 2. Tests and documentation

- [x] 2.1 Unit tests: rendering, capability gating, validation, footer forms, hooks
- [x] 2.2 Integration tests against real kses and nonces; e2e through a real press, Enter-to-save, a forged request and axe
- [x] 2.3 README: the arg, the handler contract and the default capability

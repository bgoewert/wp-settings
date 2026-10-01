## Context

See proposal.md — Why.

## Goals / Non-Goals

**Goals:**

- A control that answers in place sits in the same row and the same button group as the `admin-post` actions.

**Non-Goals:**

- An Ajax transport. The consumer already has its endpoint, nonce and script; the library carrying a second request path would duplicate them.

## Decisions

**A `render` entry rather than only widening kses.** Both were offered; the entry keeps the library owning the row's layout, so a rendered control lines up with Test connection and follows `actions_position`, where markup in `value` would sit wherever the consumer put it. `data-*` is still needed on `button` for the entry's markup to survive kses, so `value` gains the same ability for free.

**The callable gets the field.** The control usually targets the row's own reading, and the slug is the id it renders under.

**No form, nonce or hook.** The library cannot check a request it does not route; the consumer's Ajax handler checks its own. `capability` still hides the control, as it does a button that can only fail.

## Risks / Trade-offs

- **Markup is the consumer's** → it still passes through `$allowed_html`, so a control outside that list is stripped rather than printed raw.

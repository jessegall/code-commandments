---
name: commandments-vue-server-calls
description: "You are about to call `fetch(` in a component, write a server path like `'/plans/' + id` or `` `/api/tasks/${id}` ``, or set request headers by hand. Read this BEFORE you do: the server already declares its routes, and the frontend reaches them through the generated route helpers and one client module."
---

# One way to the server: generated routes and one client

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> The server declares every address it answers and every header it expects. A component that writes a path by
> hand, or calls `fetch` with its own headers, keeps a **second copy** of those facts. The copies drift: a
> route is renamed and a link breaks without an error, a header is added and the hand-rolled calls forget it.
> 

## The principle

### The rule

- **Every server address comes from the generated routes.** `route('plans.show', { plan: card.number })` or
  the generated action's `.url()`, never `'/plans/' + card.id`. The generated helper knows the path and the
  parameter it takes; a hand-written string knows neither, which is how one view builds a link from `card.id`
  while another uses `card.number`.
- **Components never call `fetch`.** One client module owns how the frontend talks to the server: the base
  path, the headers (CSRF, `Accept`, an `Idempotency-Key`), the error handling, the JSON parsing. A component
  calls that module, or the router (`router.post`, `useForm().submit`), and stays about what it shows.

### What is NOT this sin

- An external URL (`https://…`), a `mailto:` link, an asset path the bundler resolves.
- The one client module itself, which is where `fetch` belongs.
- A path the server does not declare as a route.

### The tell

You are typing a `/` at the start of a string in a component, or the word `fetch(`. Stop and import the
generated route or the client instead.

## Rules

- [ ] Every server address comes from the generated routes, never a path written by hand.
      _Build it with the generated route helper (`route('plans.show', { plan: card.number })`) or the generated action's `.url()`._
- [ ] Components never talk to the server with `fetch`; one client module owns the headers, the errors and the parsing.
      _Call the project's client module (or `router` / `useForm`) and move any headers the component sets into that module._

## Worked example

### hard-coded-server-url — in Vue

A string written out as the path of a route the server declares by name

```vue
----------[ Bad ]----------

<a :href="'/plans/' + card.id">{{ card.title }}</a>

----------[ Good ]----------

const planAddress = route('plans.show', { plan: props.card.number })
```

The other 2 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `{{binary}} judge --skill=vue/server-calls` — find every one of these in the codebase.
- `{{binary}} info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `hard-coded-server-url`, `raw-fetch-in-component`.
- `{{binary}} report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 3 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`vue/mirrored-server-type`](../mirrored-server-type/SKILL.md) — the same rule for the shapes the server sends: generate them, never copy them.

---
name: commandments-frontend-vue-control-flow
description: "Dispatch on a single value with the published <SwitchCase :value> component (a slot per case), never a v-if / v-else-if chain that re-tests the SAME subject against a different literal. A chain of `x === 'a'` / `x === 'b'` is one decision wearing many conditionals. Read this BEFORE writing a v-if/v-else-if chain in a Vue template."
---

# Vue control flow — dispatch on a value, don't chain conditionals

> 🔱 **Load `fix-at-the-source` first — the rule above all.** Every sin is a symptom; trace the value to where it is BORN and fix it there, never where it surfaces. This skill serves that one.

> A `v-if="status === 'paid'"` / `v-else-if="status === 'pending'"` / `v-else` chain
> is a `switch` in disguise: one subject (`status`), tested case by case. Each
> `v-else-if` restates the subject and reads as a fresh, independent decision when
> there is really only one — *which case is this value?*

## The principle

### What is and isn't this sin

- **Is:** two or more branches that EQUALITY-test the same subject against a literal
  (`x === 'a'`, `x === 'b'`, …). The `scribe` command auto-rewrites these.
- **Is NOT** (leave it a conditional): range/predicate guards (`stock > 10`), a
  compound branch (`role === 'editor' || role === 'author'`), branches over different
  subjects, or a lone `v-if`. These aren't a single-value dispatch.

### Control flow goes on `<template>`, never on a real element

A `v-if` / `v-else-if` / `v-else` / `v-for` is STRUCTURE — which DOM renders — and it
belongs on a `<template>` wrapper, not bolted onto the element it happens to guard.
The element reads as one thing (content + styling), the `<template>` as another
(when / how-many). `repent` auto-wraps these — a `v-for` takes its `:key` along.
(`v-show` is exempt: it toggles `display` on a real node and can't live on a
`<template>`, which renders nothing.)

## Rules

- [ ] Put `v-if`/`v-for`/`v-else`/`v-else-if` on a `<template>`, never directly on an HTML or component tag.
- [ ] Key a `v-for` by a STABLE identity (`:key="item.id"`), never the loop index.
- [ ] Never put `v-if` on a `v-for` element; filter in a computed, or wrap the `v-for` in a `<template>` and put the `v-if` on the child.
- [ ] Dispatch on a value with `<SwitchCase :value>` (a slot per case); never a `v-if`/`v-else-if` chain re-testing the same subject.
      _the `<SwitchCase :value>` component: `commandments scaffold --sin=switch-case`._

## Worked example

### control-flow-on-element

`v-if`/`v-for`/`v-else`/`v-else-if` on an HTML/component tag instead of a `<template>`

```vue
----------[ Bad ]----------

<span v-if="status === 'paid'" class="badge badge-green">Paid</span>

----------[ Good ]----------

<template v-if="status === 'paid'">
  <span class="badge badge-green">Paid</span>
</template>
```

The other 3 — one per rule — are in [`reference/examples.md`](reference/examples.md).

## Commands

- `vendor/bin/commandments judge --skill=frontend/vue-control-flow` — find every one of these in the codebase.
- `vendor/bin/commandments info <sin>` — what one rule flags, why it is a sin, and the fix. The sins here: `control-flow-on-element`, `index-as-key`, `loop-with-condition`, `switch-case`.
- `vendor/bin/commandments repent --sin=<sin>` — auto-fix, for `control-flow-on-element`, `switch-case`. Review it with `--dry-run` first.
- `vendor/bin/commandments scaffold --sin=<sin>` — generate the helper the fix reaches for, for `switch-case`.
- `vendor/bin/commandments report --detector=<Detector> --reason="…" --ref=path:line` — the flagged code is CORRECT under the architecture and the rule is wrong. That is the only thing a report claims: a finding you agree with is yours to fix, however far the fix cascades.

## Reference

- [Worked examples](reference/examples.md) — every rule's bad → good, 4 of them.
- [What fires, and why](reference/detectors.md) — the symptom each detector flags, for when you are holding a finding.

## Related skills

- [`frontend/vue-components`](../vue-components/SKILL.md) — a `<SwitchCase>` IS a component — the same extract-don't-inline instinct.

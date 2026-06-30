---
name: vue-control-flow
description: Dispatch on a single value with the published <SwitchCase :value> component (a slot per case), never a v-if / v-else-if chain that re-tests the SAME subject against a different literal. A chain of `x === 'a'` / `x === 'b'` is one decision wearing many conditionals. Read this BEFORE writing a v-if/v-else-if chain in a Vue template.
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

- Put `v-if`/`v-for`/`v-else`/`v-else-if` on a `<template>`, never directly on an HTML or component tag.
- Dispatch on a value with `<SwitchCase :value>` (a slot per case); never a `v-if`/`v-else-if` chain re-testing the same subject.

## Bad → good

```vue
// Bad
<span v-if="status === 'paid'" class="badge badge-green">Paid</span>

// Good
<template v-if="status === 'paid'">
  <span class="badge badge-green">Paid</span>
</template>
```

```vue
// Bad
<span v-if="status === 'paid'" class="badge badge-green">Paid</span>

// Good
<SwitchCase :value="status">
  <template #paid><span class="badge badge-green">Paid</span></template>
  <template #pending><span class="badge badge-amber">Pending</span></template>
  <template #refunded><span class="badge badge-grey">Refunded</span></template>
  <template #default><span class="badge">Unknown</span></template>
</SwitchCase>
```

## When it fires

- `v-if`/`v-for`/`v-else`/`v-else-if` on an HTML/component tag instead of a `<template>` — `ControlFlowOnElementDetector`
- A `v-if`/`v-else-if` chain re-testing the same subject (should be `<SwitchCase :value>`) — `SwitchCaseDetector`

## Checklist

- [ ] Put `v-if`/`v-for`/`v-else`/`v-else-if` on a `<template>`, never directly on an HTML or component tag.
- [ ] Dispatch on a value with `<SwitchCase :value>` (a slot per case); never a `v-if`/`v-else-if` chain re-testing the same subject.

## Related skills

- [`frontend/vue-components`](../vue-components/SKILL.md) — a `<SwitchCase>` IS a component — the same extract-don't-inline instinct.

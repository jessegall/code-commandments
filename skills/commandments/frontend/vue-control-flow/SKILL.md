---
name: commandments-frontend-vue-control-flow
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
- Key a `v-for` by a STABLE identity (`:key="item.id"`), never the loop index.
- Never put `v-if` on a `v-for` element; filter in a computed, or wrap the `v-for` in a `<template>` and put the `v-if` on the child.
- Dispatch on a value with `<SwitchCase :value>` (a slot per case); never a `v-if`/`v-else-if` chain re-testing the same subject.
  _the `<SwitchCase :value>` component: `commandments scaffold --sin=switch-case`._

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
<template v-for="(order, index) in orders" :key="index">
  <li class="account__order">{{ order.reference }}</li>
</template>

// Good
<template v-for="(address, index) in customer.addresses" :key="address.id">
  <li class="account__address">{{ address.line }}</li>
</template>
```

```vue
// Bad
<li v-for="tag in tags" v-if="tag.visible" :key="tag.id" class="review-tag">{{ tag.label }}</li>

// Good
<template v-for="tag in tags" :key="tag.id">
  <template v-if="tag.visible">
    <li class="review-tag">{{ tag.label }}</li>
  </template>
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
- `:key` bound to the `v-for` index — a positional key corrupts state when the list reorders or an item is inserted — `IndexAsKeyDetector`
- `v-for` and `v-if`/`v-else-if` on the SAME element — the condition is re-evaluated every iteration — `LoopWithConditionDetector`
- A `v-if`/`v-else-if` chain re-testing the same subject (should be `<SwitchCase :value>`) — `SwitchCaseDetector`

## Checklist

- [ ] Put `v-if`/`v-for`/`v-else`/`v-else-if` on a `<template>`, never directly on an HTML or component tag.
- [ ] Key a `v-for` by a STABLE identity (`:key="item.id"`), never the loop index.
- [ ] Never put `v-if` on a `v-for` element; filter in a computed, or wrap the `v-for` in a `<template>` and put the `v-if` on the child.
- [ ] Dispatch on a value with `<SwitchCase :value>` (a slot per case); never a `v-if`/`v-else-if` chain re-testing the same subject.

## Related skills

- [`frontend/vue-components`](../vue-components/SKILL.md) — a `<SwitchCase>` IS a component — the same extract-don't-inline instinct.

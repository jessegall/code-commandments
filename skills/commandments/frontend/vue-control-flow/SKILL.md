---
name: vue-control-flow
description: Dispatch on a single value with the published <SwitchCase :value> component (a slot per case), never a v-if / v-else-if chain that re-tests the SAME subject against a different literal. A chain of `x === 'a'` / `x === 'b'` is one decision wearing many conditionals. Read this BEFORE writing a v-if/v-else-if chain in a Vue template.
---

# Vue control flow — dispatch on a value, don't chain conditionals

> A `v-if="status === 'paid'"` / `v-else-if="status === 'pending'"` / `v-else` chain
> is a `switch` in disguise: one subject (`status`), tested case by case. Each
> `v-else-if` restates the subject and reads as a fresh, independent decision when
> there is really only one — *which case is this value?*

## The sin

```vue
<template>
  <Badge v-if="order.status === 'paid'" tone="green">Paid</Badge>
  <Badge v-else-if="order.status === 'pending'" tone="amber">Pending</Badge>
  <Badge v-else tone="grey">Unknown</Badge>
</template>
```

The reader has to scan every branch to learn it's a dispatch on `order.status`; adding
a case means another near-identical line; the cases aren't named, they're buried in
`=== '…'`.

## The fix — `<SwitchCase :value>` with a slot per case

```vue
<template>
  <SwitchCase :value="order.status">
    <template #paid><Badge tone="green">Paid</Badge></template>
    <template #pending><Badge tone="amber">Pending</Badge></template>
    <template #default><Badge tone="grey">Unknown</Badge></template>
  </SwitchCase>
</template>
```

The subject is stated once (`:value`), each case is a NAMED slot, `v-else` becomes
`#default`, and a new case is one new `<template #…>`. `<SwitchCase>` is a published
component (synced into the project) — pass `value`, provide a slot per case; only the
matching slot renders, falling back to `#default`.

## What is and isn't this sin

- **Is:** two or more branches that EQUALITY-test the same subject against a literal
  (`x === 'a'`, `x === 'b'`, …). The `scribe` command auto-rewrites these.
- **Is NOT** (leave it a conditional): range/predicate guards (`stock > 10`), a
  compound branch (`role === 'editor' || role === 'author'`), branches over different
  subjects, or a lone `v-if`. These aren't a single-value dispatch.

---
name: vue-components
description: Extract a component when template markup REPEATS identically, or when an element in a large template reaches DEEP into nested data (data.user.firstName). Repeated markup is one component waiting to be born; a deep reach is a child that knows too much about the data shape and wants the mid-object as a prop. Read this BEFORE copy-pasting a block of template or reaching `a.b.c` in a sizeable component.
---

# Vue components — extract repetition and deep reaches

> Two forms of one rule: **a chunk of template that repeats, or that reaches deep into
> data, is a component trying to get out.** Pull it into its own file, pass it what it
> needs as props, and use it by name.

## Form 1 — identical markup, copy-pasted

The same block appears two-or-more times (same tags, attributes, children — formatting
and line numbers ignored):

```vue
<!-- used in DialogContent.vue AND SheetContent.vue, byte-for-byte -->
<DialogClose class="absolute right-4 top-4 rounded-sm …">
  <X class="h-4 w-4" />
  <span class="sr-only">Close</span>
</DialogClose>
```

Extract it once (`<DialogCloseButton />`) and use it in each place. The block's free
variables become its props. The `scribe` command drafts the component for you,
inferring props from the markup's expression roots.

## Form 2 — an element reaching deep into nested data

In a sizeable template, an element binds or interpolates a chain `≥ 3` deep:

```vue
<!-- this element knows the whole shape of `order` -->
<p>{{ order.customer.fullName }}</p>
<p>{{ order.customer.email }}</p>
```

That's Law of Demeter in the markup. Lift it into a component that takes the
MID-OBJECT as a prop, so it reaches one level, not three:

```vue
<!-- OrderCustomer.vue -->
<script setup lang="ts">defineProps<{ customer: Customer }>();</script>
<template>
  <p>{{ customer.fullName }}</p>
  <p>{{ customer.email }}</p>
</template>
```

`<OrderCustomer :customer="order.customer" />` — the parent passes `order.customer`,
the child reaches `customer.fullName`. The component no longer depends on `order`'s
whole shape, just the slice it renders.

## When it fires

- **Duplicate markup:** a block of ≥3 elements repeated; only the largest repeated
  block is flagged (its inner pieces are repeated too — extract the whole).
- **Deep reach:** in a template past ~50 lines, an element whose binding/interpolation
  walks two+ property hops past the root (ref `.value` and `.length` don't count, nor
  does a method call or a dotted string literal).

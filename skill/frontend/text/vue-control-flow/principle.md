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
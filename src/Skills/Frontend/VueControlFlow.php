<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Frontend;

use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class VueControlFlow extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'frontend/vue-control-flow',
            title: "Vue control flow — dispatch on a value, don't chain conditionals",
            description: "Dispatch on a single value with the published <SwitchCase :value> component (a slot per case), never a v-if / v-else-if chain that re-tests the SAME subject against a different literal. A chain of `x === 'a'` / `x === 'b'` is one decision wearing many conditionals. Read this BEFORE writing a v-if/v-else-if chain in a Vue template.",
            tagline: "A `v-if=\"status === 'paid'\"` / `v-else-if=\"status === 'pending'\"` / `v-else` chain
is a `switch` in disguise: one subject (`status`), tested case by case. Each
`v-else-if` restates the subject and reads as a fresh, independent decision when
there is really only one — *which case is this value?*",
            summary: "dispatch on a value with `<SwitchCase :value>` (a slot per case), never a `v-if`/`v-else-if` chain re-testing the same subject.",
            tier: Tier::KeepInMind,
            order: 16,
        );
    }

    public function body(): string
    {
        return <<<'BODY'
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

## Control flow goes on `<template>`, never on a real element

A `v-if` / `v-else-if` / `v-else` / `v-for` is STRUCTURE — which DOM renders — and it
belongs on a `<template>` wrapper, not bolted onto the element it happens to guard:

```vue
<!-- ✗ structure tangled into the element -->
<div v-if="open" class="panel">…</div>
<li v-for="item in items" :key="item.id" class="row">{{ item.name }}</li>

<!-- ✓ structure on the template, the element stays pure content -->
<template v-if="open"><div class="panel">…</div></template>
<template v-for="item in items" :key="item.id"><li class="row">{{ item.name }}</li></template>
```

The element reads as one thing (content + styling), the `<template>` as another
(when / how-many). `repent` auto-wraps these — a `v-for` takes its `:key` along.
(`v-show` is exempt: it toggles `display` on a real node and can't live on a
`<template>`, which renders nothing.)
BODY;
    }
}

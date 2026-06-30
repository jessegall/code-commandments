<?php

declare(strict_types=1);

namespace JesseGall\CodeCommandments\Skills\Frontend;

use JesseGall\CodeCommandments\Skills\Skill;
use JesseGall\CodeCommandments\Skills\Tier;

final class VueComponents extends Skill
{
    public function __construct()
    {
        parent::__construct(
            slug: 'frontend/vue-components',
            title: "Vue components — extract repetition and deep reaches",
            description: "Extract a component when template markup REPEATS identically, or when an element in a large template reaches DEEP into nested data (data.user.firstName). Repeated markup is one component waiting to be born; a deep reach is a child that knows too much about the data shape and wants the mid-object as a prop. Read this BEFORE copy-pasting a block of template or reaching `a.b.c` in a sizeable component.",
            tagline: "Two forms of one rule: **a chunk of template that repeats, or that reaches deep into
data, is a component trying to get out.** Pull it into its own file, pass it what it
needs as props, and use it by name.",
            summary: "extract a component when template markup REPEATS, or when an element reaches DEEP into nested data — pass it the mid-object as a prop.",
            tier: Tier::KeepInMind,
            order: 15,
        );
    }

    public function body(): string
    {
        return <<<'BODY'
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

## Form 3 — markup nested far too deep

When the DOM nests past ~8 levels with several more still below, the template is
unreadable and a whole sub-tree wants out. Don't extract a random node mid-chain:
look back UP to the natural boundary (the top of the wrapper stack, or the `<li>` of a
list) and lift THAT — `{Item}List` / `{Item}ListItem` for a list, `{Object}Section`
for a panel.
BODY;
    }
}

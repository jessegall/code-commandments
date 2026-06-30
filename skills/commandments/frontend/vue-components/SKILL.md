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

## Form 3 — markup nested far too deep

When the DOM nests past ~8 levels with several more still below, the template is
unreadable and a whole sub-tree wants out. Don't extract a random node mid-chain:
look back UP to the natural boundary (the top of the wrapper stack, or the `<li>` of a
list) and lift THAT — `{Item}List` / `{Item}ListItem` for a list, `{Object}Section`
for a panel.

## Bad → good

```vue
// Bad
<Dialog v-model:open="open">
  <DialogContent class="sm:max-w-md">
    <DialogHeader>
      <DialogTitle>Pair Reader</DialogTitle>
      <DialogDescription>Enter the device name and reader model to pair.</DialogDescription>
    </DialogHeader>
    <form class="space-y-4" @submit.prevent="submit">
      <div class="field">
        <Label>Device name</Label>
        <Input v-model="form.name" type="text" placeholder="Front counter" />
      </div>
      <div class="select-row">
        <Label>Reader model</Label>
        <select v-model="form.model" class="select">
          <option value="s1">SumUp Solo</option>
          <option value="s2">SumUp Air</option>
        </select>
      </div>
      <DialogFooter>
        <Button variant="outline" @click="open = false">Cancel</Button>
        <Button type="submit">Pair reader</Button>
      </DialogFooter>
    </form>
  </DialogContent>
</Dialog>

// Good
<ReaderPairingDialog v-model:open="open" :form="form" @submit="submit" />
```

```vue
// Bad
<section class="order-detail__customer">
  <h2 class="section-title">Customer</h2>
  <p class="customer-name">{{ order.customer.fullName }}</p>
  <p class="customer-email">{{ order.customer.email }}</p>
  <p class="customer-phone">{{ order.customer.phone }}</p>
</section>

// Good
<OrderCustomer :customer="order.customer" />
```

```vue
// Bad
<div class="settings-card__body">
  <div class="accordion">
    <div class="accordion__item">
      <div class="accordion__panel">
        <div class="field-group">
          <div class="field-grid">
            <div class="field-grid__row">
              <div class="field">
                <div class="field__control">
                  <div class="field__input-wrap">
                    <div class="field__inner">
                      <label class="field__label">{{ settings.profile.displayName }}</label>
                      <input class="field__input" :value="settings.profile.handle" />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

// Good
<SettingsCardBody :settings="settings" />
```

```vue
// Bad
<article class="review-card">
  <header class="review-head">
    <Avatar class="size-8" />
    <strong class="review-author">Verified buyer</strong>
  </header>
  <p class="review-body">Exactly as described, shipped fast.</p>
</article>

// Good
<template v-for="review in reviews" :key="review.id">
  <article class="review-card">
    <header class="review-head">
      <Avatar class="size-8" />
      <strong class="review-author">{{ review.author }}</strong>
    </header>
    <p class="review-body">{{ review.body }}</p>
  </article>
</template>
```

## When it fires

- A compound primitive (`Dialog`/`Card`/`Sheet`/`Tabs`…) assembled INLINE with a substantial body — extract it into its own named component — `CompoundInlineComponentDetector`
- An element reaching DEEP into nested data — pass it the mid-object as a prop — `DeepDataReachDetector`
- Template markup nested far too deep — extract a subtree as its own component — `DeepNestedDetector`
- Identical markup repeated across the template — extract one component — `DuplicateElementDetector`

# Vue control flow — dispatch on a value, don't chain conditionals — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

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

### index-as-key

`:key` bound to the `v-for` index — a positional key corrupts state when the list reorders or an item is inserted

```vue
----------[ Bad ]----------

<template v-for="(order, index) in orders" :key="index">
  <li class="account__order">{{ order.reference }}</li>
</template>

----------[ Good ]----------

<template v-for="(address, index) in customer.addresses" :key="address.id">
  <li class="account__address">{{ address.line }}</li>
</template>
```

### loop-with-condition

`v-for` and `v-if`/`v-else-if` on the SAME element — the condition is re-evaluated every iteration

```vue
----------[ Bad ]----------

<li v-for="tag in tags" v-if="tag.visible" :key="tag.id" class="review-tag">{{ tag.label }}</li>

----------[ Good ]----------

<template v-for="tag in tags" :key="tag.id">
  <template v-if="tag.visible">
    <li class="review-tag">{{ tag.label }}</li>
  </template>
</template>
```

### switch-case

A `v-if`/`v-else-if` chain re-testing the same subject (should be `<SwitchCase :value>`)

```vue
----------[ Bad ]----------

<span v-if="status === 'paid'" class="badge badge-green">Paid</span>

----------[ Good ]----------

<!-- in OrderStatusBadge.vue -->
<SwitchCase :value="status">
  <template #paid><span class="badge badge-green">Paid</span></template>
  <template #pending><span class="badge badge-amber">Pending</span></template>
  <template #refunded><span class="badge badge-grey">Refunded</span></template>
  <template #default><span class="badge">Unknown</span></template>
</SwitchCase>

<!-- in SwitchCase.vue -->
<slot :name="$slots[value] ? value : 'default'" />
```

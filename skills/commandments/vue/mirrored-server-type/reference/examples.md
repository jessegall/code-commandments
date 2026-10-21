# One source of truth for a server contract — generate the type, don't hand-copy it — worked examples

One bad → good per rule this skill teaches, taken from the fixture that proves the detector, so every pair is code that really fires and really passes.

### mirrored-server-type — in TypeScript

A hand-written TypeScript type mirrors a backend `Data` class one-to-one — two sources of truth for one contract that drift the moment the server shape changes

```ts
----------[ Bad ]----------

export interface OrderData {
  id: string
  total: number
  placedAt: string
  status: string
}

----------[ Good ]----------

// The FIX: the server owns the shape, so the frontend takes the GENERATED type rather than
// restating it. Mark the Data class `#[TypeScript]`, generate, and import what came out.
export type { OrderData } from '@/types/generated'
```

### mirrored-server-type — in Vue

A hand-written TypeScript type mirrors a backend `Data` class one-to-one — two sources of truth for one contract that drift the moment the server shape changes

```vue
----------[ Bad ]----------

<!-- in components/CustomerProfileCard.vue -->
<script setup lang="ts">
// This restates the server `CustomerData` payload in snake_case — the same contract,
// hand-maintained. Generate it from the `Data` class instead.
interface CustomerData {
  first_name: string
  last_name: string
  email_address: string
  phone_number: string
}

defineProps<{ customer: CustomerData }>()
</script>

<template>
  <article class="profile-card">
    <h2 class="profile-card__name">{{ customer.first_name }} {{ customer.last_name }}</h2>
    <a class="profile-card__email" :href="`mailto:${customer.email_address}`">{{ customer.email_address }}</a>
    <a class="profile-card__phone" :href="`tel:${customer.phone_number}`">{{ customer.phone_number }}</a>
  </article>
</template>

----------[ Good ]----------

<!-- in components/CustomerContactCard.vue -->
<script setup lang="ts">
// The FIX: the server owns the `CustomerData` shape, so the card imports the type generated from
// its `Data` class instead of restating it.
import type { CustomerData } from '@/types/generated'

defineProps<{ customer: CustomerData }>()
</script>

<template>
  <address class="contact-card">
    <a :href="`mailto:${customer.emailAddress}`">{{ customer.emailAddress }}</a>
  </address>
</template>
```

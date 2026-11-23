<script setup lang="ts">
// This restates the server `CustomerData` payload in snake_case — the same contract,
// hand-maintained. Generate it from the `Data` class instead.
// @sin MirroredServerType
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

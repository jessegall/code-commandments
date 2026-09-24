<!-- @example MirroredServerType good -->
<script setup lang="ts">
// The FIX: the server owns the `CustomerData` shape, so the card imports the type generated from
// its `Data` class instead of restating it.
// @fixed MirroredServerType
import type { CustomerData } from '@/types/generated'

defineProps<{ customer: CustomerData }>()
</script>

<template>
  <address class="contact-card">
    <a :href="`mailto:${customer.emailAddress}`">{{ customer.emailAddress }}</a>
  </address>
</template>

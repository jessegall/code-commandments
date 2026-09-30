<script setup lang="ts">
import type { Customer } from '@/types';

defineProps<{ billing: Customer['billing'] }>();
</script>

<template>
  <!-- @fixed OversizedComponent -->
  <aside class="plan">
    <strong>{{ billing.plan }}</strong>
    <small>{{ billing.amount }}, renews {{ billing.renewsAt }}</small>
  </aside>
</template>

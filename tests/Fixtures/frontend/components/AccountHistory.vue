<script setup lang="ts">
import type { CustomerOrder } from '@/types';

defineProps<{ orders: CustomerOrder[] }>();
</script>

<template>
  <!-- @fixed OversizedComponent -->
  <ol class="history">
    <template v-for="order in orders" :key="order.reference">
      <li>{{ order.reference }}</li>
    </template>
  </ol>
</template>

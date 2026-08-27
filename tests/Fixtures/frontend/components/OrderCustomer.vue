<script setup lang="ts">
import type { Customer } from '@/types';

defineProps<{ customer: Customer }>();
</script>

<template>
  <!-- @fixed DeepDataReach -->
  <section class="order-customer">
    <h2 class="section-title">Customer</h2>
    <p class="customer-name">{{ customer.fullName }}</p>
    <p class="customer-email">{{ customer.email }}</p>
    <p class="customer-phone">{{ customer.phone }}</p>
  </section>
</template>

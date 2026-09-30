<script setup lang="ts">
import type { Customer, CustomerOrder } from '@/types';

defineProps<{ customer: Customer; orders: CustomerOrder[] }>();
</script>

<template>
  <!-- @fixed OversizedComponent -->
  <section class="account">
    <h1 class="account__title">My account</h1>
    <AccountIdentity :customer="customer" />
    <AccountPlan :billing="customer.billing" />
    <AccountHistory :orders="orders" />
  </section>
</template>

<script setup lang="ts">
import type { Customer } from '@/types';

defineProps<{ customer: Customer }>();
</script>

<template>
  <!-- @fixed OversizedComponent -->
  <dl class="identity">
    <dt>Name</dt>
    <dd>{{ customer.handle }}</dd>
    <dt>Email</dt>
    <dd><a :href="`mailto:${customer.email}`">{{ customer.email }}</a></dd>
  </dl>
</template>

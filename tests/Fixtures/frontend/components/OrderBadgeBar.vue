<script setup lang="ts">
import type { OrderBadge } from '@/types';

defineProps<{ badge: OrderBadge }>();
</script>

<template>
  <BadgePill :badge="badge" />
</template>

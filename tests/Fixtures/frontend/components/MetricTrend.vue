<script setup lang="ts">
import type { TrendData } from '@/types';

defineProps<{ trend: TrendData }>();
</script>

<template>
  <!-- A pipe: `trend` is forwarded straight on, never read here. -->
  <TrendSparkline :series="trend" />
</template>

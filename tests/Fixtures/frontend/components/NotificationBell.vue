<script setup lang="ts">
import type { Notification } from '@/types';

defineProps<{ items: Notification[] }>();
</script>

<template>
  <BellDropdown :items="items" />
</template>

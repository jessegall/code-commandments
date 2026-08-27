<script setup lang="ts">
defineProps<{ src: string }>();
</script>

<template>
  <!-- Consumes `src` (renders it) — NOT a pipe, so forwarding to it is composition. -->
  <!-- @fixed PropDrilling -->
  <img :src="src" alt="" />
</template>

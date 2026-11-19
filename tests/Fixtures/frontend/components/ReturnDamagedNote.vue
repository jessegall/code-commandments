<script setup lang="ts">
defineProps<{ photos: number }>();
</script>

<template>
  <h3>Damaged on arrival</h3>
  <p>{{ photos }} photos attached</p>
</template>

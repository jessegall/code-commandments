<script setup lang="ts">
const doubled = (
  total: number, // @label total binding
): number => total * 2 // @contract promise/read-links-binding -> total
</script>

<template>
    <p>{{ doubled(2) }}</p>
</template>

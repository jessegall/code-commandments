<script setup lang="ts">
const count = 1;
if (count > 0) {
  console.log("positive");
}
</script>

<template>
  <p v-if="count > 0">positive</p>
  <p v-else>none</p>
</template>

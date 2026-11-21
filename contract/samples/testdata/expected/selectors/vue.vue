<script setup lang="ts">
import { ref } from "vue";

const count = ref(0);
</script>

<template>
  <button v-if="count.value > 0 && count.value < 10" @click="count.value++">{{ count.value }}</button>
</template>

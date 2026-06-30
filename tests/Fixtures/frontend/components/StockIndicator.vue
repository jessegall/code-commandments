<script setup lang="ts">
defineProps<{ stock: number }>();
</script>

<template>
  <!-- Range checks, not equality on one value — a genuine conditional, NOT a switch. -->
  <!-- @sin ControlFlowOnElement -->
  <span v-if="stock > 10" class="text-green-600">In stock</span>
  <!-- @sin ControlFlowOnElement -->
  <span v-else-if="stock > 0" class="text-amber-600">Only a few left</span>
  <!-- @sin ControlFlowOnElement -->
  <span v-else class="text-red-600">Sold out</span>
</template>

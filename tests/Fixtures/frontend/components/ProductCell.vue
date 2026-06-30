<script setup lang="ts">
defineProps<{ column: string }>();
</script>

<template>
  <td class="cell">
    <!-- @sin SwitchCaseDetector -->
    <template v-if="column === 'price'">
      <strong>{{ '$' }}</strong>
      <PriceTag />
    </template>
    <template v-else-if="column === 'stock'">
      <StockLevel />
    </template>
    <template v-else>
      <span class="muted">—</span>
    </template>
  </td>
</template>

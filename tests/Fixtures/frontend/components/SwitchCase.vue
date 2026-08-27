<script setup lang="ts">
defineProps<{ value: string }>();
</script>

<template>
  <!-- @fixed SwitchCase -->
  <slot :name="$slots[value] ? value : 'default'" />
</template>

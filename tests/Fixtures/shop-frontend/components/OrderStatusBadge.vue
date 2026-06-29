<script setup lang="ts">
defineProps<{ status: string }>();
</script>

<template>
  <!-- @sin SwitchCaseDetector -->
  <span v-if="status === 'paid'" class="badge badge-green">Paid</span>
  <span v-else-if="status === 'pending'" class="badge badge-amber">Pending</span>
  <span v-else-if="status === 'refunded'" class="badge badge-grey">Refunded</span>
  <span v-else class="badge">Unknown</span>
</template>

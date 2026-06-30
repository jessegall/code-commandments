<script setup lang="ts">
defineProps<{ status: string }>();
</script>

<template>
  <!-- @sin SwitchCaseDetector -->
  <!-- @sin ControlFlowOnElementDetector -->
  <span v-if="status === 'paid'" class="badge badge-green">Paid</span>
  <!-- @sin ControlFlowOnElementDetector -->
  <span v-else-if="status === 'pending'" class="badge badge-amber">Pending</span>
  <!-- @sin ControlFlowOnElementDetector -->
  <span v-else-if="status === 'refunded'" class="badge badge-grey">Refunded</span>
  <!-- @sin ControlFlowOnElementDetector -->
  <span v-else class="badge">Unknown</span>
</template>

<script setup lang="ts">
defineProps<{ status: string }>();
</script>

<template>
  <!-- @sin SwitchCase -->
  <!-- @sin ControlFlowOnElement -->
  <span v-if="status === 'paid'" class="badge badge-green">Paid</span>
  <!-- @sin ControlFlowOnElement -->
  <span v-else-if="status === 'pending'" class="badge badge-amber">Pending</span>
  <!-- @sin ControlFlowOnElement -->
  <span v-else-if="status === 'refunded'" class="badge badge-grey">Refunded</span>
  <!-- @sin ControlFlowOnElement -->
  <span v-else class="badge">Unknown</span>

  <!-- @righteous SwitchCase -->
  <SwitchCase :value="status">
    <template #paid><span class="badge badge-green">Paid</span></template>
    <template #pending><span class="badge badge-amber">Pending</span></template>
    <template #refunded><span class="badge badge-grey">Refunded</span></template>
    <template #default><span class="badge">Unknown</span></template>
  </SwitchCase>

  <!-- @righteous ControlFlowOnElement -->
  <template v-if="status === 'paid'">
    <span class="badge badge-green">Paid</span>
  </template>
</template>

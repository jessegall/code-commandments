<script setup lang="ts">
defineProps<{ stage: string; courier: string; eta: string }>();
</script>

<template>
  <!-- @righteous RetestedSwitchCase -->
  <SwitchCase :value="stage">
    <template #packing>
      <p>Being packed</p>
      <template v-if="courier === 'dhl'"><p>DHL collects after four</p></template>
    </template>
    <template #shipped>
      <!-- @sin RetestedSwitchCase -->
      <p v-show="stage === 'shipped'">On its way, arriving {{ eta }}</p>
    </template>
  </SwitchCase>
</template>

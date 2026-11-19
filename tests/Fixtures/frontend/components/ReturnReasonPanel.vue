<script setup lang="ts">
import ReturnDamagedNote from './ReturnDamagedNote.vue';

defineProps<{ request: { reason: string; photos: number; note: string } }>();
</script>

<template>
  <section>
    <SwitchCase :value="request.reason">
      <template #damaged>
        <!-- @sin RetestedSwitchCase -->
        <template v-if="request.reason === 'damaged'">
          <h3>Damaged on arrival</h3>
          <p>{{ request.photos }} photos attached</p>
        </template>
      </template>
      <template #unwanted><h3>No longer wanted</h3></template>
    </SwitchCase>

    <!-- @fixed RetestedSwitchCase -->
    <SwitchCase :value="request.reason">
      <template #damaged><ReturnDamagedNote :photos="request.photos" /></template>
      <template #unwanted><h3>No longer wanted</h3></template>
    </SwitchCase>
  </section>
</template>

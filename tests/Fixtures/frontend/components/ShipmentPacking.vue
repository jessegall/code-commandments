<script setup lang="ts">
defineProps<{ packer: string; parcels: { id: number; label: string; kilos: number }[] }>();
</script>

<template>
  <!-- @fixed InlineCaseViews -->
  <section class="packing">
    <h3>Packing</h3>
    <template v-for="parcel in parcels" :key="parcel.id">
      <p class="parcel"><span>{{ parcel.label }}</span> <span>{{ parcel.kilos }} kg</span></p>
    </template>
    <footer>Packed by {{ packer }}</footer>
  </section>
</template>

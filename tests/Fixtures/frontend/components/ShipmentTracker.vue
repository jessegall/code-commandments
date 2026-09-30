<script setup lang="ts">
defineProps<{ shipment: { stage: string; packer: string; parcels: { id: number; label: string; kilos: number }[]; carrier: string; eta: string; stops: string[] } }>();
</script>

<template>
  <!-- @sin InlineCaseViews -->
  <SwitchCase :value="shipment.stage">
    <template #packing>
      <section class="stage">
        <h3>Packing</h3>
        <ul class="parcels">
          <template v-for="parcel in shipment.parcels" :key="parcel.id">
            <li>
              <span class="label">{{ parcel.label }}</span>
              <span class="kilos">{{ parcel.kilos }} kg</span>
            </li>
          </template>
        </ul>
        <p class="note">Packed by {{ shipment.packer }}</p>
      </section>
    </template>
    <template #in_transit>
      <article class="transit">
        <header>
          <strong>{{ shipment.carrier }}</strong>
          <time>{{ shipment.eta }}</time>
        </header>
        <ol class="stops">
          <template v-for="stop in shipment.stops" :key="stop">
            <li><em>{{ stop }}</em></li>
          </template>
        </ol>
      </article>
    </template>
    <template #default>
      <p>Delivered</p>
    </template>
  </SwitchCase>

  <!-- @fixed InlineCaseViews -->
  <SwitchCase :value="shipment.stage">
    <template #packing><ShipmentPacking :parcels="shipment.parcels" :packer="shipment.packer" /></template>
    <template #in_transit><ShipmentInTransit :carrier="shipment.carrier" :eta="shipment.eta" :stops="shipment.stops" /></template>
    <template #default><p>Delivered</p></template>
  </SwitchCase>
</template>

<script setup lang="ts">
defineProps<{ carrier: string; eta: string; stops: string[] }>();
</script>

<template>
  <!-- @fixed InlineCaseViews -->
  <article class="in-transit">
    <h4>{{ carrier }}, arriving <time>{{ eta }}</time></h4>
    <template v-for="stop in stops" :key="stop">
      <span class="stop">{{ stop }}</span>
    </template>
  </article>
</template>

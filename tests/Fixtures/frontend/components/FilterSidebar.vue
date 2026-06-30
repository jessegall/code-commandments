<script setup lang="ts">
defineProps<{ categories: string[]; brands: string[] }>();
</script>

<template>
  <aside class="filters">
    <header class="filters__header">
      <h2 class="filters__title">Refine results</h2>
      <button class="filters__clear" type="button">Clear all</button>
    </header>

    <div class="filters__search">
      <label class="filters__search-label" for="q">Search products</label>
      <input id="q" class="filters__search-input" type="search" placeholder="Search" />
    </div>

    <!-- @sin DuplicateElementDetector -->
    <fieldset class="filter-group">
      <legend class="filter-legend">Brand</legend>
      <label class="filter-option"><input type="checkbox" /> Any brand</label>
    </fieldset>

    <!-- @sin DuplicateElementDetector -->
    <fieldset class="filter-group">
      <legend class="filter-legend">Brand</legend>
      <label class="filter-option"><input type="checkbox" /> Any brand</label>
    </fieldset>
  </aside>
</template>

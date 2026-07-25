<script setup lang="ts">
defineProps<{ total: number }>();
</script>

<template>
  <aside class="basket-promo">
    <!-- @sin DuplicateElement -->
    <div class="promo-strip">
      <span class="promo-icon">%</span>
      <strong class="promo-headline">Free shipping this week</strong>
      <small class="promo-terms">On orders over 50.</small>
    </div>
  </aside>
</template>

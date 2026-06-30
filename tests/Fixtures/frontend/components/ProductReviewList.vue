<script setup lang="ts">
defineProps<{ productId: number }>();
</script>

<template>
  <section class="reviews">
    <!-- @sin DuplicateElementDetector -->
    <article class="review-card">
      <header class="review-head">
        <Avatar class="size-8" />
        <strong class="review-author">Verified buyer</strong>
      </header>
      <p class="review-body">Exactly as described, shipped fast.</p>
    </article>

    <!-- @sin DuplicateElementDetector -->
    <article class="review-card">
      <header class="review-head">
        <Avatar class="size-8" />
        <strong class="review-author">Verified buyer</strong>
      </header>
      <p class="review-body">Exactly as described, shipped fast.</p>
    </article>
  </section>
</template>

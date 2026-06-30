<script setup lang="ts">
defineProps<{ productId: number }>();
</script>

<template>
  <section class="reviews">
    <!-- @sin DuplicateElement -->
    <article class="review-card">
      <header class="review-head">
        <Avatar class="size-8" />
        <strong class="review-author">Verified buyer</strong>
      </header>
      <p class="review-body">Exactly as described, shipped fast.</p>
    </article>

    <!-- @sin DuplicateElement -->
    <article class="review-card">
      <header class="review-head">
        <Avatar class="size-8" />
        <strong class="review-author">Verified buyer</strong>
      </header>
      <p class="review-body">Exactly as described, shipped fast.</p>
    </article>

    <!-- @righteous DuplicateElement -->
    <template v-for="review in reviews" :key="review.id">
      <article class="review-card">
        <header class="review-head">
          <Avatar class="size-8" />
          <strong class="review-author">{{ review.author }}</strong>
        </header>
        <p class="review-body">{{ review.body }}</p>
      </article>
    </template>
  </section>
</template>

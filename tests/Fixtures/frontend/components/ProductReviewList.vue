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

    <ul class="review-tags">
      <!-- @sin LoopWithCondition -->
      <!-- @sin ControlFlowOnElement -->
      <li v-for="tag in tags" v-if="tag.visible" :key="tag.id" class="review-tag">{{ tag.label }}</li>
    </ul>

    <!-- @righteous LoopWithCondition -->
    <template v-for="tag in tags" :key="tag.id">
      <template v-if="tag.visible">
        <li class="review-tag">{{ tag.label }}</li>
      </template>
    </template>
  </section>
</template>

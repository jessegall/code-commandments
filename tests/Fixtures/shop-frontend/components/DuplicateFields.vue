<script setup lang="ts">
defineProps<{ name: string; email: string }>();
</script>

<template>
  <form class="checkout-form">
    <!-- @sin DuplicateElementDetector -->
    <div class="field">
      <label class="field-label">Name</label>
      <input class="field-input" name="name" />
    </div>

    <!-- @sin DuplicateElementDetector -->
    <div class="field">
      <label class="field-label">Name</label>
      <input class="field-input" name="name" />
    </div>
  </form>
</template>

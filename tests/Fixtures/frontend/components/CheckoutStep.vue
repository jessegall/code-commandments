<script setup lang="ts">
defineProps<{ step: number }>();
</script>

<template>
  <div class="checkout">
    <!-- @sin SwitchCaseDetector -->
    <CartReview v-if="step === 1" />
    <ShippingForm v-else-if="step === 2" />
    <PaymentForm v-else-if="step === 3" />
  </div>
</template>

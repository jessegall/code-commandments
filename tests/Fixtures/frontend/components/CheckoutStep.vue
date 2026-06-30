<script setup lang="ts">
defineProps<{ step: number }>();
</script>

<template>
  <div class="checkout">
    <nav class="checkout__progress" aria-label="Checkout steps">
      <ol class="checkout__steps">
        <li class="checkout__step checkout__step--cart">Cart</li>
        <li class="checkout__step checkout__step--shipping">Shipping</li>
        <li class="checkout__step checkout__step--payment">Payment</li>
      </ol>
    </nav>

    <section class="checkout__panel">
      <!-- @sin SwitchCaseDetector -->
      <!-- @sin ControlFlowOnElementDetector -->
      <CartReview v-if="step === 1" />
      <!-- @sin ControlFlowOnElementDetector -->
      <ShippingForm v-else-if="step === 2" />
      <!-- @sin ControlFlowOnElementDetector -->
      <PaymentForm v-else-if="step === 3" />
    </section>
  </div>
</template>

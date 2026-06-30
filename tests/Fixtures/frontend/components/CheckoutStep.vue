<script setup lang="ts">
defineProps<{ step: number; dismissed: boolean }>();
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
      <!-- @sin SwitchCase -->
      <!-- @sin ControlFlowOnElement -->
      <CartReview v-if="step === 1" />
      <!-- @sin ControlFlowOnElement -->
      <ShippingForm v-else-if="step === 2" />
      <!-- @sin ControlFlowOnElement -->
      <PaymentForm v-else-if="step === 3" />
    </section>

    <!-- Assigning the `dismissed` PROP in a handler — a silent no-op. -->
    <!-- @sin PropMutation -->
    <button class="checkout__dismiss" @click="dismissed = true">Dismiss</button>
  </div>
</template>

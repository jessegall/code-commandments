<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

// `form` is a useForm() handle — reactive STATE this component owns, two-way bound
// below. Reaching form.errors.* / form.data.* deeply is NOT a Law-of-Demeter sin, so
// DeepDataReachDetector must leave this whole component alone (rule R1, reactive root).
const form = useForm({ name: '', email: '', city: '' });
</script>

<template>
  <form class="address-form" @submit.prevent="form.post('/checkout/address')">
    <header class="address-form__header">
      <h2 class="address-form__title">Shipping address</h2>
    </header>

    <div class="address-form__field">
      <label class="address-form__label">Name</label>
      <input v-model="form.data.name" class="address-form__input" type="text" />
      <p class="address-form__error">{{ form.errors.name }}</p>
    </div>

    <div class="address-form__field">
      <label class="address-form__label">Email</label>
      <input v-model="form.data.email" class="address-form__input" type="email" />
      <p class="address-form__error">{{ form.errors.email }}</p>
    </div>

    <div class="address-form__field">
      <label class="address-form__label">City</label>
      <input v-model="form.data.city" class="address-form__input" type="text" />
      <p class="address-form__error">{{ form.errors.city }}</p>
    </div>

    <div class="address-form__summary">
      <h3 class="address-form__summary-title">Review</h3>
      <p class="address-form__line">Name: {{ form.data.name }}</p>
      <p class="address-form__line">Email: {{ form.data.email }}</p>
      <p class="address-form__line">City: {{ form.data.city }}</p>
    </div>

    <footer class="address-form__footer">
      <button class="address-form__submit" type="submit" :disabled="form.processing">
        Save address
      </button>
      <button class="address-form__reset" type="button" @click="form.reset()">
        Reset
      </button>
    </footer>
  </form>
</template>

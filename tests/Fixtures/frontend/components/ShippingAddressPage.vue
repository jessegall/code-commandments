<script setup lang="ts">
// The shipping and billing address pages are the same page: one skeleton, two sets of labels and
// bindings. Their whole templates are a component waiting to be extracted.
import { reactive } from 'vue'

const shipping = reactive({ street: '', city: '' })
</script>

<template>
    <!-- @sin NearDuplicateElement -->
    <section class="address-page">
        <header class="address-head">
            <h2>Shipping address</h2>
            <p>Where we deliver your order.</p>
        </header>
        <form class="address-form">
            <label class="address-field">Street<input v-model="shipping.street" name="shipping-street" /></label>
            <label class="address-field">City<input v-model="shipping.city" name="shipping-city" /></label>
        </form>
    </section>
</template>

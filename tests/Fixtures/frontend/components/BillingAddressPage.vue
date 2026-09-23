<script setup lang="ts">
// A copy of the shipping address page's template, bound to the billing address.
import { reactive } from 'vue'

const billing = reactive({ street: '', city: '' })
</script>

<template>
    <!-- @sin NearDuplicateElement -->
    <section class="address-page billing">
        <header class="address-head">
            <h2>Billing address</h2>
            <p>Where we send your invoice.</p>
        </header>
        <form class="address-form">
            <label class="address-field">Street<input v-model="billing.street" name="billing-street" /></label>
            <label class="address-field">Town<input v-model="billing.city" name="billing-town" /></label>
        </form>
    </section>
</template>

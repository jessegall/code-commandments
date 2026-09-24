<!-- @example DuplicateFunction good -->
<script setup lang="ts">
// The FIX: subscribing again is the same request, so there is one function and both buttons call it.
import { ref } from 'vue'

const address = ref('')
const rejected = ref(false)

// @fixed DuplicateFunction
async function subscribe(): Promise<void> {
    rejected.value = false
    await fetch('/api/newsletter', { method: 'POST', body: JSON.stringify({ email: address.value }) })
        .catch(() => { rejected.value = true })
}
</script>

<template>
    <form @submit.prevent="subscribe">
        <input v-model="address" type="email" />
        <button type="button" @click="subscribe">Subscribe again</button>
    </form>
</template>

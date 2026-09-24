<!-- @example DuplicateFunction bad -->
<script setup lang="ts">
// Two handlers in one component with one body: subscribing and re-subscribing post the same
// form and recover from the same failure, so the second is a copy of the first.
import { ref } from 'vue'

const email = ref('')
const failed = ref(false)

// @sin DuplicateFunction
async function subscribe(): Promise<void> {
    failed.value = false
    try {
        await fetch('/api/newsletter', { method: 'POST', body: JSON.stringify({ email: email.value }) })
    } catch {
        failed.value = true
    }
}

// @sin DuplicateFunction
async function resubscribe(): Promise<void> {
    failed.value = false
    try {
        await fetch('/api/newsletter', { method: 'POST', body: JSON.stringify({ email: email.value }) })
    } catch {
        failed.value = true
    }
}
</script>

<template>
    <form @submit.prevent="subscribe">
        <input v-model="email" type="email" />
        <button type="button" @click="resubscribe">Subscribe again</button>
    </form>
</template>

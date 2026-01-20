<script setup lang="ts">
import { ref } from 'vue'

const count = ref(0)

// Sinful: arrow function assignment instead of function declaration
const increment = () => {
    count.value++
}

// Also sinful
const decrement = () => count.value--

// Sinful: async arrow function
const loadData = async () => {
    const response = await fetch('/api')
    return response.json()
}
</script>

<template>
    <div>
        <button @click="increment">+</button>
        <button @click="decrement">-</button>
        <p>{{ count }}</p>
    </div>
</template>

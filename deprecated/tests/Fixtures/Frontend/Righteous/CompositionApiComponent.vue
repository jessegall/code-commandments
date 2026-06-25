<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'

// Righteous: Using Composition API with script setup
const count = ref(0)
const title = ref('Hello World')

const doubleCount = computed(() => count.value * 2)

const increment = () => {
  count.value++
}

onMounted(() => {
  console.log('Component mounted')
})
</script>

<template>
  <div>
    <h1>{{ title }}</h1>
    <p>Count: {{ count }}</p>
    <p>Double: {{ doubleCount }}</p>
    <button @click="increment">Increment</button>
  </div>
</template>

<style scoped>
.container {
  padding: 1rem;
}
</style>

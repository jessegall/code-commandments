<template>
  <div>
    <h1>{{ title }}</h1>
    <p>Count: {{ count }}</p>
    <button @click="increment">Increment</button>
  </div>
</template>

<script>
// Sin: Using Options API instead of Composition API
export default {
  name: 'OptionsApiComponent',

  data() {
    return {
      count: 0,
      title: 'Hello World',
    }
  },

  computed: {
    doubleCount() {
      return this.count * 2
    },
  },

  methods: {
    increment() {
      this.count++
    },
  },

  mounted() {
    console.log('Component mounted')
  },
}
</script>

<style scoped>
.container {
  padding: 1rem;
}
</style>

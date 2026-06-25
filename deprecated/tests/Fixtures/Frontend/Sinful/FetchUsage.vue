<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'

interface User {
  id: number
  name: string
}

const users = ref<User[]>([])
const loading = ref(false)

// Sin: Direct fetch() usage
const loadUsersWithFetch = async () => {
  loading.value = true
  const response = await fetch('/api/users')
  users.value = await response.json()
  loading.value = false
}

// Sin: Direct axios usage
const loadUsersWithAxios = async () => {
  loading.value = true
  const response = await axios.get('/api/users')
  users.value = response.data
  loading.value = false
}

// Sin: axios.post
const createUser = async (name: string) => {
  await axios.post('/api/users', { name })
}

onMounted(() => {
  loadUsersWithFetch()
})
</script>

<template>
  <div>
    <p v-if="loading">Loading...</p>
    <ul v-else>
      <li v-for="user in users" :key="user.id">{{ user.name }}</li>
    </ul>
  </div>
</template>

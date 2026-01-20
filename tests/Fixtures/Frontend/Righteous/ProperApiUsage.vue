<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'

interface Props {
  users: User[]
}

interface User {
  id: number
  name: string
}

// Righteous: Using typed props with defineProps
const props = defineProps<Props>()

const loading = ref(false)

// Righteous: Using Inertia router instead of fetch/axios
const loadUsers = () => {
  router.visit(route('users.index'))
}

// Righteous: Using Inertia router for POST
const createUser = (name: string) => {
  router.post(route('users.store'), { name })
}

// Righteous: Using Ziggy route() helper
const editUser = (user: User) => {
  router.visit(route('users.edit', { user: user.id }))
}
</script>

<template>
  <div>
    <p v-if="loading">Loading...</p>
    <ul v-else>
      <li v-for="user in props.users" :key="user.id">
        {{ user.name }}
        <button @click="editUser(user)">Edit</button>
      </li>
    </ul>
    <button @click="loadUsers">Refresh</button>
  </div>
</template>

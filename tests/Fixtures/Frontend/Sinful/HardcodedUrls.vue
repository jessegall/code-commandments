<script setup lang="ts">
import { useRouter } from 'vue-router'

const router = useRouter()

function goToUsers() {
    // Sinful: hardcoded URL
    router.push('/users')
}

function goToUserProfile(id: number) {
    // Sinful: hardcoded URL with interpolation
    router.push(`/users/${id}/profile`)
}

function goBack() {
    // Sinful: hardcoded URL
    router.replace('/dashboard')
}
</script>

<template>
    <div>
        <button @click="goToUsers">Go to Users</button>
        <router-link to="/settings">Settings</router-link>
    </div>
</template>

<template>
    <div>
        <p>{{ message }}</p>
    </div>
</template>

<script setup lang="ts">
// Sinful: template comes before script
// Script should always be first
import { ref } from 'vue'

const message = ref('Hello World')
</script>

<style scoped>
div {
    color: red;
}
</style>

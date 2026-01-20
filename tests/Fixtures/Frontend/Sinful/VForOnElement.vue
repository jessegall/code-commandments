<script setup lang="ts">
import { ref } from 'vue'

const items = ref([
    { id: 1, name: 'Item 1' },
    { id: 2, name: 'Item 2' },
    { id: 3, name: 'Item 3' }
])
</script>

<template>
    <div>
        <!-- Sinful: v-for directly on DOM element -->
        <div v-for="item in items" :key="item.id">
            {{ item.name }}
        </div>

        <!-- Also sinful: on other elements -->
        <li v-for="(item, index) in items" :key="index">
            {{ item.name }}
        </li>
    </div>
</template>

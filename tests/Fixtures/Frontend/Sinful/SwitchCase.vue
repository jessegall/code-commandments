<script setup lang="ts">
import { ref, computed } from 'vue'

const status = ref('pending')

// Sinful: switch statement instead of object map
function getStatusLabel(status: string): string {
    switch (status) {
        case 'pending':
            return 'Pending'
        case 'active':
            return 'Active'
        case 'completed':
            return 'Completed'
        default:
            return 'Unknown'
    }
}

// Also sinful: switch with complex logic
const statusColor = computed(() => {
    switch (status.value) {
        case 'pending':
            return 'yellow'
        case 'active':
            return 'green'
        case 'completed':
            return 'blue'
        default:
            return 'gray'
    }
})
</script>

<template>
    <div :class="statusColor">
        {{ getStatusLabel(status) }}
    </div>
</template>

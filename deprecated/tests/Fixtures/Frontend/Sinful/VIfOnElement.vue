<script setup lang="ts">
import { ref } from 'vue'

const isVisible = ref(true)
const type = ref('a')
</script>

<template>
    <div>
        <!-- Sinful: v-if directly on DOM element -->
        <div v-if="isVisible">
            Visible content
        </div>
        <div v-else>
            Hidden content
        </div>

        <!-- Also sinful: v-else-if on element -->
        <span v-if="type === 'a'">Type A</span>
        <span v-else-if="type === 'b'">Type B</span>
        <span v-else>Other</span>
    </div>
</template>

<script setup>
// Sinful: No TypeScript, no type for props

defineProps({
    title: String,
    count: Number,
    isActive: Boolean
})
</script>

<template>
    <div>
        <h1>{{ title }}</h1>
        <p>Count: {{ count }}</p>
    </div>
</template>

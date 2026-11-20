<script setup lang="ts">
interface Props {
    note: PickupNote | null;
}

defineProps<Props>();
</script>

<template>
    <p v-if="note?.instructions">{{ note.instructions }}</p>
</template>

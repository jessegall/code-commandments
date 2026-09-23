<script setup lang="ts">
// Each action posts the pick list to its own endpoint and closes the dialog on success — the
// handlers differ only in the path they post to.
const props = defineProps<{ pickListId: number | null }>()
const emit = defineEmits<{ close: [] }>()

// @sin NearDuplicateFunction
async function release(): Promise<void> {
    if (props.pickListId === null) {
        return
    }
    const response = await fetch(`/api/pick-lists/${props.pickListId}/release`, { method: 'POST' })
    if (response.ok) {
        emit('close')
    }
}

// @sin NearDuplicateFunction
async function pause(): Promise<void> {
    if (props.pickListId === null) {
        return
    }
    const response = await fetch(`/api/pick-lists/${props.pickListId}/pause`, { method: 'POST' })
    if (response.ok) {
        emit('close')
    }
}
</script>

<template>
    <div class="actions">
        <button type="button" @click="release">Release</button>
        <button type="button" @click="pause">Pause</button>
    </div>
</template>

<script setup lang="ts">
// The FIX for handlers that differ only in the path they post to: one handler takes the action,
// each button names the one it wants, and the parent hears which one finished.
const props = defineProps<{ pickListId: number | null }>()
const emit = defineEmits<{ done: [action: string] }>()

// @fixed NearDuplicateFunction
async function post(action: string): Promise<void> {
    if (props.pickListId === null) {
        return
    }
    const response = await fetch(`/api/pick-lists/${props.pickListId}/${action}`, { method: 'POST' })
    if (response.ok) {
        emit('done', action)
    }
}
</script>

<template>
    <div class="toolbar">
        <button type="button" @click="post('release')">Release</button>
        <button type="button" @click="post('pause')">Pause</button>
    </div>
</template>

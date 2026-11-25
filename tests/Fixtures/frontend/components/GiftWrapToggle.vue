<script setup lang="ts">
// A top-level `let` of a <script setup> is the state of one component instance: setup runs once per instance.
let isWrapped = false
let toggles = 0

// @righteous MutableStaticState
function toggle(): void {
    isWrapped = !isWrapped
    toggles = toggles + 1
}
</script>

<template>
    <label class="gift-wrap" :data-toggles="toggles">
        <!-- @righteous MutableStaticState -->
        <input type="checkbox" :checked="isWrapped" @change="(event) => (isWrapped = (event.target as HTMLInputElement).checked)" />
        <button type="button" @click="toggle">Wrap it</button>
    </label>
</template>

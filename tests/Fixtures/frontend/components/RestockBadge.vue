<script setup lang="ts">
// Shows how many of a product are left, asking through the stock client rather than calling fetch itself.
import { ref } from 'vue'
import { stockLevels } from '../client/stock-levels'

const props = defineProps<{ sku: string; shelf: { fetch(sku: string): number } }>()
const left = ref(0)

async function refresh(): Promise<void> {
    // @fixed RawFetchInComponent
    const levels = await stockLevels(props.sku)
    left.value = levels.reduce((total, level) => total + level, 0)
}

function countOnShelf(): number {
    // @righteous RawFetchInComponent
    return props.shelf.fetch(props.sku)
}
</script>

<template>
  <span class="badge" @click="refresh">{{ left }} left, {{ countOnShelf() }} on the shelf</span>
</template>

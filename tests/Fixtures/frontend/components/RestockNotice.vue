<script setup lang="ts">
// The restock notice re-implements the stock loader from the stock-lookup module instead of
// importing it — one decision living in two files.
import { ref } from 'vue'

interface StockLevel {
    sku: string
    available: number
}

const props = defineProps<{ sku: string }>()
const available = ref<number[]>([])

// @sin DuplicateFunction
const fetchAvailability = async (sku: string): Promise<number[]> => {
    const response = await fetch(`/api/stock/${sku}`)
    if (!response.ok) {
        throw new Error(response.statusText)
    }
    const levels: StockLevel[] = await response.json()
    return levels.map((level) => level.available)
}

// @righteous DuplicateFunction
function isSoldOut(levels: number[]): boolean {
    return levels.length === 0
}

fetchAvailability(props.sku).then((levels) => (available.value = levels))
</script>

<template>
    <p class="restock" :data-sold-out="isSoldOut(available)">Back soon</p>
</template>

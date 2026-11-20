<script setup lang="ts">
const { order, counter } = defineProps<{
    order: { ready: boolean }
    counter: { staffed: boolean; tickets: number }
}>()
</script>

<template>
    <!-- @sin UnnamedCondition -->
    <!-- @sin ControlFlowOnElement -->
    <aside v-if="order.ready && counter.staffed && counter.tickets > 0">You can collect your order.</aside>
    <!-- @righteous UnnamedCondition -->
    <!-- @sin ControlFlowOnElement -->
    <p v-if="counter.staffed && counter.tickets > 0">Take a ticket.</p>
</template>

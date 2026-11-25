<script lang="ts">
// A top-level `let` of a plain <script> is module state: every instance of the component shares the one value.
let mounted = 0

export function countMount(): number {
    // @sin MutableStaticState
    mounted = mounted + 1
    return mounted
}
</script>

<script setup lang="ts">
const order = countMount()
</script>

<template>
    <section class="recently-viewed" :data-order="order">Recently viewed</section>
</template>

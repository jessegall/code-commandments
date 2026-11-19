<script setup lang="ts">
// The returns desk: staff type a return's number and send its label to the printer.
import { ref } from 'vue'

const props = defineProps<{ desk: { submit(address: string, copies: number): Promise<void> } }>()
const returnNumber = ref(0)
const copies = ref(1)

async function sendToPrinter(): Promise<void> {
    // @sin HardCodedServerURL
    await props.desk.submit('/returns/' + returnNumber.value + '/label', copies.value)
}
</script>

<template>
  <form class="returns-desk" @submit.prevent="sendToPrinter">
    <input v-model.number="returnNumber" type="number" />
    <input v-model.number="copies" type="number" min="1" />
    <button type="submit">Print label</button>
  </form>
</template>

<script setup lang="ts">
// One plan on the board: its title, a link to the plan and a button that opens the whole list.
import { route } from '../client/routes'

const props = defineProps<{ card: { id: number; number: number; title: string } }>()

function openBoard(): void {
    // @sin HardCodedServerURL
    window.location.assign('/plans')
}

function pingServer(): boolean {
    // @righteous HardCodedServerURL
    return navigator.sendBeacon('/healthz')
}

// @fixed HardCodedServerURL
const planAddress = route('plans.show', { plan: props.card.number })
</script>

<template>
  <article class="plan-card">
    <!-- @sin HardCodedServerURL -->
    <a :href="'/plans/' + card.id">{{ card.title }}</a>
    <a :href="planAddress">{{ card.title }}</a>
    <!-- @righteous HardCodedServerURL -->
    <a href="https://help.example.com/plans/archive">Help</a>
    <button type="button" @click="openBoard">All plans</button>
    <button type="button" @click="pingServer">Ping</button>
  </article>
</template>

<script setup lang="ts">
// Two delivery options laid out with one skeleton — a heading, a price, a list of perks and a
// choose button — each bound to its own option. The card is one component rendered twice.
defineProps<{ standard: { price: string; days: string }; express: { price: string; days: string } }>()
const emit = defineEmits<{ choose: [option: string] }>()
</script>

<template>
    <div class="slots">
        <!-- @sin NearDuplicateElement -->
        <article class="slot">
            <h3>Standard</h3>
            <strong class="slot-price">{{ standard.price }}</strong>
            <ul class="slot-perks">
                <li>{{ standard.days }} working days</li>
                <li>Tracked parcel</li>
            </ul>
            <button type="button" @click="emit('choose', 'standard')">Choose standard</button>
        </article>
        <!-- @sin NearDuplicateElement -->
        <article class="slot slot-express">
            <h3>Express</h3>
            <strong class="slot-price">{{ express.price }}</strong>
            <ul class="slot-perks">
                <li>{{ express.days }} working day</li>
                <li>Signed for on delivery</li>
            </ul>
            <button type="button" @click="emit('choose', 'express')">Choose express</button>
        </article>
        <!-- @righteous NearDuplicateElement -->
        <aside class="slot-note">
            <h3>Collect in store</h3>
            <p>Free, ready in two hours.</p>
            <a href="/stores">Find a store</a>
        </aside>
    </div>
</template>

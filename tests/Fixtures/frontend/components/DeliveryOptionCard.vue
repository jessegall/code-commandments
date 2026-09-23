<script setup lang="ts">
// The FIX for a delivery card written once per option: the skeleton lives here, once, and what
// differed between the copies — the option — is a prop.
defineProps<{ option: { key: string; title: string; price: string; perks: string[] } }>()
const emit = defineEmits<{ choose: [key: string] }>()
</script>

<template>
    <!-- @fixed NearDuplicateElement -->
    <article class="slot">
        <h3>{{ option.title }}</h3>
        <strong class="slot-price">{{ option.price }}</strong>
        <ul class="slot-perks">
            <template v-for="perk in option.perks" :key="perk">
                <li>{{ perk }}</li>
            </template>
        </ul>
        <button type="button" @click="emit('choose', option.key)">Choose {{ option.title }}</button>
    </article>
</template>

<script setup lang="ts">
// Renders the one extracted card per delivery option instead of a copy of its markup.
import DeliveryOptionCard from './DeliveryOptionCard.vue'

type Option = { key: string; title: string; price: string; perks: string[] }

defineProps<{ standard: Option; express: Option }>()
</script>

<template>
    <!-- @fixed NearDuplicateElement -->
    <div class="slots">
        <DeliveryOptionCard :option="standard" />
        <DeliveryOptionCard :option="express" />
    </div>
</template>

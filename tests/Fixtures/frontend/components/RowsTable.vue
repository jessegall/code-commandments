<script setup lang="ts">
defineProps<{ headings: string[]; rows: { id: number }[] }>()
</script>

<template>
    <table class="rows-table">
        <thead>
            <tr>
                <template v-for="heading in headings" :key="heading">
                    <th>{{ heading }}</th>
                </template>
            </tr>
        </thead>
        <tbody>
            <template v-for="row in rows" :key="row.id">
                <tr>
                    <slot name="row" :row="row" />
                </tr>
            </template>
        </tbody>
    </table>
</template>

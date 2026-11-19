<script setup lang="ts">
import RowsTable from './RowsTable.vue'

defineProps<{ runs: { id: number; courier: string; parcels: number; state: string; startedAt: string }[] }>()
</script>

<template>
    <!-- @righteous NearDuplicateElement -->
    <RowsTable :headings="['Run', 'Courier', 'Parcels', 'State', 'Started']" :rows="runs">
        <template #row="{ row: run }">
            <td><a :href="`/runs/${run.id}`">{{ run.id }}</a></td>
            <td>{{ run.courier }}</td>
            <td>{{ run.parcels }}</td>
            <td>{{ run.state }}</td>
            <td>{{ run.startedAt }}</td>
        </template>
    </RowsTable>
</template>

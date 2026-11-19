<script setup lang="ts">
import RowsTable from './RowsTable.vue'

defineProps<{ shares: { id: number; number: number; customer: string; reason: string; refund: string; expiresAt: string }[] }>()
</script>

<template>
    <!-- @righteous NearDuplicateElement -->
    <RowsTable :headings="['Return', 'Customer', 'Reason', 'Refund', 'Expires']" :rows="shares">
        <template #row="{ row: share }">
            <td><a :href="`/returns/${share.number}`">Return {{ share.number }}</a></td>
            <td>{{ share.customer }}</td>
            <td>{{ share.reason }}</td>
            <td>{{ share.refund }}</td>
            <td>{{ share.expiresAt }}</td>
        </template>
    </RowsTable>
</template>

<script setup lang="ts">
// Each table's body is the same loading row while its rows arrive: one skeleton written into both
// tables, differing only in the message and the column count.
defineProps<{ loadingStock: boolean; loadingOrders: boolean }>()
</script>

<template>
    <div class="stock-tables">
        <table class="stock">
            <thead>
                <tr><th>Sku</th><th>Shelf</th><th>Count</th><th>Reorder</th></tr>
            </thead>
            <!-- @sin NearDuplicateElement -->
            <tbody>
                <tr class="loading-row">
                    <td colspan="4">
                        <div role="status" class="loading">
                            <span class="spinner" />
                            <span class="sr-only">Loading stock</span>
                            <small class="loading-hint">Counting the shelves</small>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        <table class="orders">
            <!-- @sin NearDuplicateElement -->
            <tbody>
                <tr class="loading-row">
                    <td colspan="6">
                        <div role="status" class="loading">
                            <span class="spinner" />
                            <span class="sr-only">Loading orders</span>
                            <small class="loading-hint">Fetching this week's orders</small>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>

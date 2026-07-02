<script setup lang="ts">
// A purely-frontend view-model: it describes how the table renders, not a server
// payload. No `Data` class backs it, so it has exactly one source of truth — itself.
// @righteous MirroredServerType
interface TableColumn {
  key: string
  label: string
  sortable: boolean
  width: number
}

defineProps<{ columns: TableColumn[] }>()
</script>

<template>
  <table class="inventory-table">
    <caption class="inventory-table__caption">{{ columns.length }} columns</caption>
  </table>
</template>

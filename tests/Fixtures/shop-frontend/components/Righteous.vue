<script setup lang="ts">
defineProps<{ open: boolean; role: string; user: string; count: number }>();
</script>

<template>
  <!-- A plain toggle — not a dispatch on one value. Must NOT be flagged. -->
  <div v-if="open">Open</div>
  <div v-else>Closed</div>

  <!-- A branch testing two values (||) is not a single case. Must NOT be flagged. -->
  <div v-if="role === 'admin'">Admin</div>
  <div v-else-if="role === 'editor' || role === 'author'">Writer</div>

  <!-- Different subjects per branch — not a switch. Must NOT be flagged. -->
  <div v-if="role === 'admin'">A</div>
  <div v-else-if="user === 'root'">B</div>

  <!-- Range checks, not equality — not a switch. Must NOT be flagged. -->
  <div v-if="count > 10">many</div>
  <div v-else-if="count < 2">few</div>
</template>

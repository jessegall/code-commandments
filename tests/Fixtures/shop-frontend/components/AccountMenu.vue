<script setup lang="ts">
defineProps<{ role: string; membership: string }>();
</script>

<template>
  <nav class="account-menu">
    <!-- A branch testing two values (||) is not a single case — NOT a switch. -->
    <MenuLink v-if="role === 'admin'" to="/admin">Dashboard</MenuLink>
    <MenuLink v-else-if="role === 'editor' || role === 'author'" to="/catalog">Catalog</MenuLink>

    <!-- Different subjects per branch (role vs membership) — NOT a switch. -->
    <Badge v-if="role === 'admin'">Staff</Badge>
    <Badge v-else-if="membership === 'gold'">Gold member</Badge>
  </nav>
</template>

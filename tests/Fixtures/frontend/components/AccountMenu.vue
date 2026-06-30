<script setup lang="ts">
import type { Notification } from '@/types';
import NotificationBell from './NotificationBell.vue';
import UserAvatar from './UserAvatar.vue';

defineProps<{ role: string; membership: string; notifications: Notification[]; avatarUrl: string }>();
</script>

<template>
  <nav class="account-menu">
    <!-- A branch testing two values (||) is not a single case — NOT a switch. -->
    <!-- @sin ControlFlowOnElement -->
    <MenuLink v-if="role === 'admin'" to="/admin">Dashboard</MenuLink>
    <!-- @sin ControlFlowOnElement -->
    <MenuLink v-else-if="role === 'editor' || role === 'author'" to="/catalog">Catalog</MenuLink>

    <!-- Different subjects per branch (role vs membership) — NOT a switch. -->
    <!-- @sin ControlFlowOnElement -->
    <Badge v-if="role === 'admin'">Staff</Badge>
    <!-- @sin ControlFlowOnElement -->
    <Badge v-else-if="membership === 'gold'">Gold member</Badge>

    <!-- `notifications` is read nowhere here, and NotificationBell pipes it on — a chain. -->
    <!-- @sin PropDrilling -->
    <NotificationBell :items="notifications" />

    <!-- @righteous PropDrilling -->
    <!-- `avatarUrl` is forwarded and unused here too, but UserAvatar CONSUMES it — composition. -->
    <UserAvatar :src="avatarUrl" />
  </nav>
</template>

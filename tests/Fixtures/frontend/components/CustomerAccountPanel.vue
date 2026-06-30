<script setup lang="ts">
import type { Customer } from '@/types';

defineProps<{ customer: Customer }>();
</script>

<template>
  <section class="account">
    <header class="account__header">
      <h1 class="account__title">My account</h1>
      <span class="account__id">#{{ customer.id }}</span>
    </header>

    <div class="account__profile">
      <h2 class="account__section">Profile</h2>
      <!-- Righteous: a LONE deep reach (one field off customer.profile) is no cluster. -->
      <p class="account__name">{{ customer.profile.displayName }}</p>
      <p class="account__handle">{{ customer.handle }}</p>
    </div>

    <div class="account__contact">
      <h2 class="account__section">Contact</h2>
      <p class="account__email">{{ customer.email }}</p>
      <p class="account__phone">{{ customer.phone }}</p>
    </div>

    <!-- A cluster: customer.billing read in three fields → extract <AccountBilling :billing>. -->
    <!-- @sin DeepDataReachDetector -->
    <div class="account__billing">
      <h2 class="account__section">Billing</h2>
      <p class="account__plan">{{ customer.billing.plan }}</p>
      <p class="account__amount">{{ customer.billing.amount }}</p>
      <p class="account__renews">{{ customer.billing.renewsAt }}</p>
    </div>

    <div class="account__preferences">
      <h2 class="account__section">Preferences</h2>
      <ul class="account__prefs">
        <!-- @sin ControlFlowOnElementDetector -->
        <li v-for="pref in customer.preferences" :key="pref.id" class="account__pref">
          <span class="account__pref-name">{{ pref.label }}</span>
          <span class="account__pref-value">{{ pref.enabled }}</span>
        </li>
      </ul>
    </div>

    <div class="account__orders">
      <h2 class="account__section">Recent orders</h2>
      <ul class="account__order-list">
        <li class="account__order">Order placed last week</li>
        <li class="account__order">Order placed last month</li>
        <li class="account__order">Order placed last quarter</li>
      </ul>
    </div>

    <footer class="account__footer">
      <button class="account__save" type="button">Save changes</button>
      <button class="account__signout" type="button">Sign out</button>
    </footer>
  </section>
</template>

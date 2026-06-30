<script setup lang="ts">
import type { Order } from '@/types';

defineProps<{ order: Order }>();
</script>

<template>
  <article class="order-detail">
    <header class="order-detail__header">
      <h1 class="order-detail__title">Order #{{ order.id }}</h1>
      <time class="order-detail__date">{{ order.placedAt }}</time>
      <OrderStatusBadge :status="order.status" />
    </header>

    <section class="order-detail__customer">
      <h2 class="section-title">Customer</h2>
      <!-- @sin DeepDataReachDetector -->
      <p class="customer-name">{{ order.customer.fullName }}</p>
      <!-- @sin DeepDataReachDetector -->
      <p class="customer-email">{{ order.customer.email }}</p>
    </section>

    <section class="order-detail__shipping">
      <h2 class="section-title">Shipping to</h2>
      <!-- @sin DeepDataReachDetector -->
      <p class="shipping-city">{{ order.shipping.address.city }}</p>
      <!-- @sin DeepDataReachDetector -->
      <p class="shipping-method">{{ order.shipping.method }}</p>
    </section>

    <section class="order-detail__items">
      <h2 class="section-title">Items</h2>
      <ul class="item-list">
        <li v-for="item in order.items" :key="item.id" class="item-row">
          <span class="item-name">{{ item.name }}</span>
          <span class="item-qty">{{ item.quantity }}</span>
          <span class="item-price">{{ item.price }}</span>
        </li>
      </ul>
    </section>

    <footer class="order-detail__totals">
      <div class="total-row">
        <span class="total-label">Subtotal</span>
        <span class="total-value">{{ order.subtotal }}</span>
      </div>
      <div class="total-row">
        <span class="total-label">Tax</span>
        <span class="total-value">{{ order.tax }}</span>
      </div>
      <div class="total-row total-row--grand">
        <span class="total-label">Total</span>
        <span class="total-value">{{ order.total }}</span>
      </div>
      <!-- @sin DeepDataReachDetector -->
      <a class="invoice-link" :href="order.invoice.downloadUrl">Download invoice</a>
    </footer>
  </article>
</template>

<script setup lang="ts">
import type { Storefront } from '@/types';

defineProps<{ storefront: Storefront }>();
</script>

<template>
  <header class="hero">
    <div class="hero__bar">
      <span class="hero__name">{{ storefront.name }}</span>
      <span class="hero__status">{{ storefront.open }}</span>
    </div>

    <div class="hero__banner">
      <!-- @sin DeepDataReachDetector -->
      <h1 class="hero__headline">{{ storefront.campaign.banner.title }}</h1>
      <p class="hero__sub">{{ storefront.tagline }}</p>
    </div>

    <nav class="hero__nav">
      <ul class="hero__links">
        <li v-for="link in storefront.links" :key="link.id" class="hero__link">
          <span class="hero__link-label">{{ link.label }}</span>
        </li>
      </ul>
    </nav>

    <div class="hero__promo">
      <h2 class="hero__promo-title">Featured</h2>
      <!-- @sin DeepDataReachDetector -->
      <p class="hero__promo-text">{{ storefront.featured.product.name }}</p>
      <p class="hero__promo-price">{{ storefront.featuredPrice }}</p>
    </div>

    <div class="hero__meta">
      <span class="hero__hours">{{ storefront.hours }}</span>
      <span class="hero__location">{{ storefront.location }}</span>
      <span class="hero__rating">{{ storefront.rating }}</span>
    </div>

    <div class="hero__categories">
      <h2 class="hero__cat-title">Browse the store</h2>
      <ul class="hero__cat-list">
        <li class="hero__cat">New arrivals</li>
        <li class="hero__cat">Best sellers</li>
        <li class="hero__cat">On sale this week</li>
        <li class="hero__cat">Clearance</li>
      </ul>
    </div>

    <div class="hero__cta">
      <button class="hero__shop" type="button">Shop now</button>
      <button class="hero__learn" type="button">Learn more</button>
    </div>
  </header>
</template>

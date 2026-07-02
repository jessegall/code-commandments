<script setup lang="ts">
// A `type` alias restating the server `ProductData` payload — a second source of truth
// for a shape the backend already owns.
// @sin MirroredServerType
type ProductData = {
  id: string
  name: string
  price: number
  sku: string
  stock: number
}

defineProps<{ product: ProductData }>()
</script>

<template>
  <section class="product-info">
    <h3 class="product-info__name">{{ product.name }}</h3>
    <p class="product-info__sku">SKU: {{ product.sku }}</p>
    <p class="product-info__price">{{ product.price }}</p>
    <p class="product-info__stock">{{ product.stock }} in stock</p>
  </section>
</template>

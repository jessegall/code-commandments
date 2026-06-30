<script setup lang="ts">
defineProps<{ orderId: number; currency: string }>();
</script>

<template>
  <table class="line-items">
    <caption class="line-items__caption">Items in this order</caption>
    <thead class="line-items__head">
      <tr>
        <th scope="col">Product</th>
        <th scope="col">Quantity</th>
      </tr>
    </thead>
    <tbody>
      <!-- @sin DuplicateElementDetector -->
      <tr class="line-item">
        <td class="line-item__name">Sample product</td>
        <td class="line-item__qty">1</td>
      </tr>
      <!-- @sin DuplicateElementDetector -->
      <tr class="line-item">
        <td class="line-item__name">Sample product</td>
        <td class="line-item__qty">1</td>
      </tr>
    </tbody>
  </table>
</template>

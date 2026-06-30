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
      <!-- @sin DuplicateElement -->
      <tr class="line-item">
        <td class="line-item__name">Sample product</td>
        <td class="line-item__qty">1</td>
      </tr>
      <!-- @sin DuplicateElement -->
      <tr class="line-item">
        <td class="line-item__name">Sample product</td>
        <td class="line-item__qty">1</td>
      </tr>
      <!-- @sin LoopWithCondition -->
      <!-- @sin ControlFlowOnElement -->
      <tr v-for="line in lines" v-if="line.quantity > 0" :key="line.id" class="line-item">
        <td class="line-item__name">{{ line.name }}</td>
        <td class="line-item__qty">{{ line.quantity }}</td>
      </tr>
    </tbody>
  </table>
</template>

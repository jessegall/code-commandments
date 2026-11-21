<script setup lang="ts">
import Item from "./Item.vue";

const title = "Items";
</script>

<template>
  <Item :title="title" @select="title" />
</template>

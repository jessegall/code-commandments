<script setup lang="ts">
import { Kind } from './one'

const kind = Kind.Food // @contract promise/read-declaration-resolves -> one.ts#food
</script>

<template>
    <p>{{ kind }}</p>
</template>

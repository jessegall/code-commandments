<script setup lang="ts">
defineProps<{ variant: string }>();

const slots = defineSlots<Record<string, (() => unknown) | undefined>>();
</script>

<template>
    <!--
        A `<slot>` is a compile-time OUTLET, not a real element — it renders no node of its own, so
        there is no content here for the structure to be mixed with. Choosing between slot outlets IS
        the wrapper form the rule asks for; wrapping each in a `<template>` would add markup and change
        nothing. Same category as `<template>` itself, which the rule has always exempted.
    -->
    <!-- @righteous ControlFlowOnElement -->
    <slot v-if="slots[variant]" :name="variant" />
    <!-- @righteous ControlFlowOnElement -->
    <slot v-else name="default" />
</template>

<script setup lang="ts" generic="TValue extends string | number | symbol">
/**
 * Utility component that flattens long v-if/v-else-if chains in the
 * editor. Pass `value` and provide named slots per case, plus an
 * optional `default` slot. Only the slot whose name matches `value`
 * renders; if none match, the `default` slot renders (when present).
 */
import { computed } from 'vue';

const props = defineProps<{
    value: TValue;
}>();

const slots = defineSlots<Record<string, (() => unknown) | undefined>>();

const activeSlot = computed<string>(() => {
    const key = String(props.value);
    return slots[key] ? key : 'default';
});
</script>

<template>
    <slot v-if="activeSlot !== 'default'" :name="activeSlot" />
    <slot v-else name="default" />
</template>

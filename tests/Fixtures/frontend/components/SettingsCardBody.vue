<script setup lang="ts">
import type { Settings } from '@/types';

defineProps<{ settings: Settings }>();
</script>

<template>
  <!-- @fixed DeepNested -->
  <div class="settings-card__body">
    <div class="field-group">
      <label class="field__label">{{ settings.profile.displayName }}</label>
      <input class="field__input" :value="settings.profile.handle" />
    </div>
  </div>
</template>

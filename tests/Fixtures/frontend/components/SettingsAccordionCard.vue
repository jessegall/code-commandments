<script setup lang="ts">
import type { Settings } from '@/types';

defineProps<{ settings: Settings }>();
</script>

<template>
  <article class="settings-card">
    <h2 class="settings-card__title">Advanced settings</h2>

    <!-- 13 levels deep with 4 still below the boundary → extract the body. -->
    <!-- @sin DeepNestedDetector -->
    <div class="settings-card__body">
      <div class="accordion">
        <div class="accordion__item">
          <div class="accordion__panel">
            <div class="field-group">
              <div class="field-grid">
                <div class="field-grid__row">
                  <div class="field">
                    <div class="field__control">
                      <div class="field__input-wrap">
                        <div class="field__inner">
                          <label class="field__label">{{ settings.profile.displayName }}</label>
                          <input class="field__input" :value="settings.profile.handle" />
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </article>
</template>

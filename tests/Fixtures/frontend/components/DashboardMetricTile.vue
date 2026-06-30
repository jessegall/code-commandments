<script setup lang="ts">
import type { Metric } from '@/types';

defineProps<{ metric: Metric }>();
</script>

<template>
  <section class="tile">
    <header class="tile__head">
      <span class="tile__name">{{ metric.label }}</span>
    </header>

    <!-- @sin DeepNested -->
    <figure class="tile__viz">
      <div class="chart">
        <div class="chart__frame">
          <div class="chart__plot">
            <div class="series">
              <div class="series__track">
                <div class="bar">
                  <div class="bar__fill">
                    <div class="bar__cap">
                      <div class="bar__readout">
                        <div class="bar__numbers">
                          <span class="bar__value">{{ metric.summary.total }}</span>
                          <span class="bar__delta">{{ metric.summary.change }}</span>
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
    </figure>
  </section>
</template>

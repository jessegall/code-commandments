<script setup lang="ts">
import type { Cart } from '@/types';

defineProps<{ cart: Cart }>();
</script>

<template>
  <aside class="invoice">
    <p class="invoice__caption">Order summary</p>

    <!-- @sin DeepNestedDetector -->
    <section class="invoice__body">
      <article class="invoice__statement">
        <header class="invoice__masthead">
          <span class="invoice__ledger">
            <span class="invoice__entry">
              <span class="invoice__money">
                <strong class="invoice__frame">
                  <strong class="invoice__pad">
                    <strong class="invoice__inset">
                      <em class="invoice__stack">
                        <em class="invoice__line">
                          <em class="invoice__value">{{ cart.totals.grand }}</em>
                        </em>
                      </em>
                    </strong>
                  </strong>
                </strong>
              </span>
            </span>
          </span>
        </header>
      </article>
    </section>
  </aside>
</template>

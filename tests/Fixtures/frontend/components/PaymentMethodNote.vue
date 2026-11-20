<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
  method: { kind: string; last4: string; bank: string }
  total: number
  currency: string
}>()

const amount = computed(() =>
  new Intl.NumberFormat('en', { style: 'currency', currency: props.currency }).format(props.total / 100),
)
</script>

<template>
  <details class="payment-receipt">
    <summary>Payment of {{ amount }}</summary>
    <SwitchCase :value="method.kind">
      <template #card>
        <!-- @sin RetestedSwitchCase -->
        <template v-if="method.kind === 'card'">
          <dl>
            <dt>Card number</dt>
            <dd><code>•••• {{ method.last4 }}</code></dd>
            <dt>Charged</dt>
            <dd>{{ amount }}</dd>
          </dl>
        </template>
      </template>
      <template #transfer>
        <p>Transfer from <strong>{{ method.bank }}</strong></p>
      </template>
      <template #default>
        <p>The receipt is available in your account.</p>
      </template>
    </SwitchCase>
  </details>
</template>

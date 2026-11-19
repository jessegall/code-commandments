<script setup lang="ts">
defineProps<{ method: { kind: string; last4: string; bank: string } }>();
</script>

<template>
  <SwitchCase :value="method.kind">
    <template #card>
      <!-- @sin RetestedSwitchCase -->
      <template v-if="method.kind === 'card'">
        <span class="note">Card ending {{ method.last4 }}</span>
      </template>
    </template>
    <template #transfer>
      <span class="note">Transfer from {{ method.bank }}</span>
    </template>
    <template #default><span class="note">Paid</span></template>
  </SwitchCase>
</template>

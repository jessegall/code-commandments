<script setup lang="ts">
import type { PairingForm } from '@/types';

defineProps<{ open: boolean; form: PairingForm }>();
defineEmits<{ submit: []; 'update:open': [boolean] }>();
</script>

<template>
  <!-- @fixed CompoundInlineComponent -->
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="pairing-dialog">
      <DialogHeader>
        <DialogTitle>Pair Reader</DialogTitle>
      </DialogHeader>

      <form class="pairing-form" @submit.prevent="$emit('submit')">
        <Label class="pairing-form__label" for="device">Device name</Label>
        <Input id="device" v-model="form.name" placeholder="Front counter" />

        <Label class="pairing-form__label" for="model">Reader model</Label>
        <select id="model" v-model="form.model" class="pairing-form__select">
          <option value="s1">SumUp Solo</option>
          <option value="s2">SumUp Air</option>
        </select>

        <DialogFooter>
          <Button type="submit">Pair reader</Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>

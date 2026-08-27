<script setup lang="ts">
import type { PairingForm } from '@/types';

defineProps<{ open: boolean; form: PairingForm }>();
defineEmits<{ submit: []; 'update:open': [boolean] }>();
</script>

<template>
  <!-- @fixed CompoundInlineComponent -->
  <Dialog :open="open" @update:open="$emit('update:open', $event)">
    <DialogContent class="sm:max-w-md">
      <DialogHeader>
        <DialogTitle>Pair Reader</DialogTitle>
        <DialogDescription>Enter the device name and reader model to pair.</DialogDescription>
      </DialogHeader>
      <form class="space-y-4" @submit.prevent="$emit('submit')">
        <div class="field">
          <Label>Device name</Label>
          <Input v-model="form.name" type="text" placeholder="Front counter" />
        </div>
        <div class="select-row">
          <Label>Reader model</Label>
          <select v-model="form.model" class="select">
            <option value="s1">SumUp Solo</option>
            <option value="s2">SumUp Air</option>
          </select>
        </div>
        <DialogFooter>
          <Button variant="outline" @click="$emit('update:open', false)">Cancel</Button>
          <Button type="submit">Pair reader</Button>
        </DialogFooter>
      </form>
    </DialogContent>
  </Dialog>
</template>

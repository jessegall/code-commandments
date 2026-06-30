<script setup lang="ts">
import { reactive, ref } from 'vue';

const open = ref(false);
const form = reactive({ name: '', model: '' });
function submit() {}
</script>

<template>
  <section class="reader-pairing">
    <button class="btn" @click="open = true">Pair a reader</button>

    <!-- A whole dialog assembled inline — Dialog + Dialog* parts with a real form body.
         It is its own ReaderPairingDialog, not part of this panel. -->
    <!-- @sin CompoundInlineComponent -->
    <Dialog v-model:open="open">
      <DialogContent class="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Pair Reader</DialogTitle>
          <DialogDescription>Enter the device name and reader model to pair.</DialogDescription>
        </DialogHeader>
        <form class="space-y-4" @submit.prevent="submit">
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
            <Button variant="outline" @click="open = false">Cancel</Button>
            <Button type="submit">Pair reader</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>

    <!-- Righteous: the same dialog already lives in its own component, referenced here. -->
    <!-- @righteous CompoundInlineComponent -->
    <ReaderPairingDialog v-model:open="open" :form="form" @submit="submit" />
  </section>
</template>

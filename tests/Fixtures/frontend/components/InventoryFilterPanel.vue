<script setup lang="ts">
import { ref } from 'vue';

const open = ref(false);
const query = ref('');
function apply() {}
</script>

<template>
  <aside class="inventory-filter">
    <button class="btn" @click="open = true">Filters</button>

    <!-- A Sheet compound assembled inline — Sheet + Sheet* parts with a filter body.
         It is its own InventoryFilterSheet. -->
    <!-- @sin CompoundInlineComponent -->
    <Sheet v-model:open="open">
      <SheetContent side="right">
        <SheetHeader>
          <SheetTitle>Filter inventory</SheetTitle>
          <SheetDescription>Narrow the catalogue by name and availability.</SheetDescription>
        </SheetHeader>
        <div class="filter-body">
          <Label>Search</Label>
          <Input v-model="query" type="search" placeholder="SKU or name" />
          <fieldset class="availability">
            <legend>Availability</legend>
            <!-- @sin LoopWithCondition -->
            <!-- @sin ControlFlowOnElement -->
            <label v-for="opt in options" v-if="opt.enabled" :key="opt.id"><input type="checkbox" :value="opt.value" /> {{ opt.label }}</label>
          </fieldset>
        </div>
        <SheetFooter>
          <Button @click="apply">Apply filters</Button>
        </SheetFooter>
      </SheetContent>
    </Sheet>
  </aside>
</template>

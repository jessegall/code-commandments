<script setup lang="ts">
import { ref } from "vue";

defineProps<{ reasons: string[]; total: string }>();
const step = ref("reason");
const chosen = ref("");
</script>

<template>
  <!-- @sin InlineCaseViews -->
  <!-- @sin SwitchCase -->
  <template v-if="step === 'reason'">
    <form class="reason" @submit.prevent="step = 'review'">
      <fieldset>
        <legend>Why are you returning it?</legend>
        <template v-for="reason in reasons" :key="reason">
          <label><input v-model="chosen" type="radio" :value="reason" />{{ reason }}</label>
        </template>
      </fieldset>
      <button type="submit">Next</button>
    </form>
  </template>
  <template v-else-if="step === 'review'">
    <div class="review">
      <dl>
        <dt>Reason</dt>
        <dd><q>{{ chosen }}</q></dd>
        <dt>Refund</dt>
        <dd><b>{{ total }}</b></dd>
      </dl>
      <menu><button @click="step = 'done'">Confirm</button></menu>
    </div>
  </template>
  <template v-else>
    <p>Your refund is on its way.</p>
  </template>
</template>

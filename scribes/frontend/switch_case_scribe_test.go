package frontend

import (
	"strings"
	"testing"

	rules "github.com/jessegall/code-commandments/detectors/frontend"
)

func TestSwitchCaseRewritesAChainIntoSwitchCase(t *testing.T) {
	fixed := sameAsPHP(t, rules.SwitchCaseDetector{}, "SwitchCaseDetector", map[string]string{"Card.vue": `<script setup lang="ts">
const status = 'active';
</script>
<template>
  <div class="card">
    <Badge v-if="status === 'active'" tone="green">Active</Badge>
    <Badge v-else-if="status === 'pending'" tone="amber">Pending</Badge>
    <Badge v-else tone="grey">Unknown</Badge>
  </div>
</template>`})["Card.vue"]

	for _, want := range []string{`<SwitchCase :value="status">`, `<template #active><Badge tone="green">Active</Badge></template>`, `<template #default><Badge tone="grey">Unknown</Badge></template>`} {
		if !strings.Contains(fixed, want) {
			t.Fatalf("lost %q:\n%s", want, fixed)
		}
	}
	if strings.Contains(fixed, "v-if") || strings.Contains(fixed, "v-else") {
		t.Fatalf("a structural directive survives:\n%s", fixed)
	}
}

func TestSwitchCaseIsIdempotent(t *testing.T) {
	once := sameAsPHP(t, rules.SwitchCaseDetector{}, "SwitchCaseDetector", map[string]string{"Card.vue": `<template>
  <p v-if="tab === 'one'">1</p>
  <p v-else-if="tab === 'two'">2</p>
</template>`})["Card.vue"]

	if again := goRewrites(t, rules.SwitchCaseDetector{}, project(t, map[string]string{"Card.vue": once})); len(again) != 0 {
		t.Fatalf("a second pass rewrote:\n%s", again["Card.vue"])
	}
}

func TestSwitchCaseLeavesAGenuineConditionalUntouched(t *testing.T) {
	if rewrote := sameAsPHP(t, rules.SwitchCaseDetector{}, "SwitchCaseDetector", map[string]string{"Plain.vue": "<template>\n  <div v-if=\"open\">x</div>\n  <div v-else>y</div>\n</template>\n"}); len(rewrote) != 0 {
		t.Fatal("a genuine conditional is left alone")
	}
}

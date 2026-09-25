package frontend

import (
	"strings"
	"testing"

	rules "github.com/jessegall/code-commandments/detectors/frontend"
)

// PHP's ControlFlowOnElementDetectorTest writes these templates in single quotes, so each `\n` is a backslash and an n.
func TestWrapControlFlowWrapsTheElementInATemplate(t *testing.T) {
	fixed := sameAsPHP(t, rules.ControlFlowOnElementDetector{}, "ControlFlowOnElementDetector", map[string]string{"Component.vue": `<template>\n  <div>\n    <span v-if="open" class="x">hi</span>\n  </div>\n</template>`})["Component.vue"]

	if !strings.Contains(fixed, `<template v-if="open">`) || !strings.Contains(fixed, `<span class="x">hi</span>`) || strings.Contains(fixed, "<span v-if") {
		t.Fatalf("got\n%s", fixed)
	}
}

func TestWrapControlFlowCarriesVForAndItsKey(t *testing.T) {
	fixed := sameAsPHP(t, rules.ControlFlowOnElementDetector{}, "ControlFlowOnElementDetector", map[string]string{"Component.vue": `<template>\n  <ul>\n    <li v-for="i in items" :key="i.id" class="row">{{ i.name }}</li>\n  </ul>\n</template>`})["Component.vue"]

	if !strings.Contains(fixed, `<template v-for="i in items" :key="i.id">`) || !strings.Contains(fixed, `<li class="row">{{ i.name }}</li>`) {
		t.Fatalf("got\n%s", fixed)
	}
}

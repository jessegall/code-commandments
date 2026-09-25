package frontend

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine/vue"
	"github.com/jessegall/code-commandments/scribes"
)

// scriptedOracle answers each query's names with the types it was given, and records what it was asked.
type scriptedOracle struct {
	types map[string]string
	asked []vue.TypeQuery
}

func (o *scriptedOracle) ResolveAll(queries []vue.TypeQuery) map[string]map[string]string {
	o.asked = append(o.asked, queries...)
	resolved := map[string]map[string]string{}
	for _, query := range queries {
		for _, name := range query.Names {
			if typed, ok := o.types[name]; ok {
				if resolved[query.Component.File()] == nil {
					resolved[query.Component.File()] = map[string]string{}
				}
				resolved[query.Component.File()][name] = typed
			}
		}
	}

	return resolved
}

// rewrittenWith is what the extraction strategy writes over the folder with the oracle consulted, keyed by path
// relative to it.
func rewrittenWith(t *testing.T, detector, strategy string, oracle vue.TypeOracle, dir string) map[string]string {
	t.Helper()
	scanner := served(t)
	codebase, err := scanner.Scan(scribes.Pass{Roots: []string{dir}, Scope: everywhere{}})
	if err != nil {
		t.Fatal(err)
	}
	scribe := &ExtractComponentScribe{strategy: strategy}
	scribe.Situate([]string{dir}, scanner)
	scribe.oracle = oracle
	rewrites, err := scribe.Rewrite(enrolled(t, detector).Find(codebase), codebase)
	if err != nil {
		t.Fatal(err)
	}
	relative := map[string]string{}
	for path, content := range rewrites.Contents() {
		relative[strings.TrimPrefix(path, dir+"/")] = content
	}

	return relative
}

func TestTheOracleTypesAPropNoReadingOfTheSourceCould(t *testing.T) {
	popover := `<Popover><PopoverTrigger as-child><Button>Open</Button></PopoverTrigger>` +
		`<PopoverContent class="w-64"><header><h4>Summary</h4></header>` +
		`<p class="title">{{ blurb }}</p><p class="note">Detail</p>` +
		`<ul><li>One</li><li>Two</li></ul>` +
		`<div class="actions"><Button>Close</Button></div></PopoverContent></Popover>`
	sfc := "<script setup lang=\"ts\">\nimport { Popover, PopoverTrigger, PopoverContent } from '@/ui/popover';\nconst blurb = computed(() => schema.value.label);\n</script>\n" +
		"<template>\n  <div>\n    <button>Open</button>\n    " + popover + "\n  </div>\n</template>\n"
	dir := project(t, map[string]string{"Panel.vue": sfc})
	oracle := &scriptedOracle{types: map[string]string{"blurb": "string | null"}}
	files := rewrittenWith(t, "CompoundInlineComponentDetector", compound, oracle, dir)
	var created []string
	for path, content := range files {
		if path != "Panel.vue" {
			created = append(created, content)
		}
	}
	if len(created) != 1 {
		t.Fatalf("created %d components: %v", len(created), keys(files))
	}
	if !strings.Contains(created[0], "blurb: string | null") || strings.Contains(created[0], "blurb: unknown") {
		t.Errorf("the oracle's type was not taken:\n%s", created[0])
	}
	if len(oracle.asked) != 1 || oracle.asked[0].Names[0] != "blurb" {
		t.Errorf("the oracle was asked %v", oracle.asked)
	}
}

func TestTheOraclesDryRunLeavesNoPhantomReuses(t *testing.T) {
	filler := strings.Repeat("  <p>row</p>\n", 55)
	file := "<template>\n  <div>\n" + filler + "  <section><p>{{ order.customer.fullName }}</p><p>{{ order.customer.email }}</p></section>\n  </div>\n</template>\n"
	dir := project(t, map[string]string{"PanelA.vue": file})
	files := rewrittenWith(t, "DeepDataReachDetector", deepReach, &scriptedOracle{}, dir)
	if _, ok := files["CustomerSection.vue"]; !ok {
		t.Fatalf("the extracted component was not created: %v", keys(files))
	}
	if !strings.Contains(files["PanelA.vue"], "<CustomerSection") {
		t.Errorf("the call site does not call it:\n%s", files["PanelA.vue"])
	}
}

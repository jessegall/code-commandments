package vue_test

import (
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
	"github.com/jessegall/code-commandments/typescript"
	"github.com/jessegall/code-commandments/vue"
)

func TestAnElementKnowsItsDirectivesAndBindings(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{"Row.vue": `<template>
  <li v-for="(line, index) in lines" :key="index" :order-table="table" v-if="shown">x</li>
</template>`})
	row := vue.Of(frontendtest.Named(t, codebase, "Element", "li"))
	if !row.Has(vue.For) || !row.HasAny(vue.ElseIf, vue.If) || row.Has(vue.Show) {
		t.Error("the directives written on the element are not the ones it has")
	}
	if aliases := row.Directive(vue.For).Aliases(); len(aliases) != 2 || aliases[1].Name() != "index" {
		t.Errorf("the v-for binds %d aliases", len(aliases))
	}
	if row.Directive(vue.For).Iterable().Name() != "lines" {
		t.Error("the v-for iterates something else than lines")
	}
	if row.Binding("key").Value().Name() != "index" {
		t.Error(":key binds something else than index")
	}
	if _, ok := row.Bindings()["orderTable"]; !ok {
		t.Error(":order-table is not the orderTable prop")
	}
}

func TestAnElementMeasuresItsNesting(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{"Card.vue": `<template>
  <section>
    <div>
      <p><b>deep</b></p>
      <span>beside</span>
    </div>
  </section>
</template>`})
	bold := vue.Of(frontendtest.Named(t, codebase, "Element", "b"))
	section := vue.Of(frontendtest.Named(t, codebase, "Element", "section"))
	if bold.Depth() != 4 || section.Depth() != 1 {
		t.Errorf("b sits %d deep and section %d", bold.Depth(), section.Depth())
	}
	if section.Height() != 4 || section.Size() != 5 {
		t.Errorf("section is %d high and holds %d elements", section.Height(), section.Size())
	}
	if !section.IsTemplateRoot() {
		t.Error("the template's only element is not its root")
	}
	if bold.Boundary().Tag() != "p" {
		t.Errorf("b climbs to %s, not to the p that holds only it", bold.Boundary().Tag())
	}
}

func TestAChainReTestingOneSubjectIsASwitchCase(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{"Badge.vue": `<template>
  <div>
    <b v-if="status === 'paid'">paid</b>
    <i v-else-if="status === 'open'">open</i>
    <u v-else>other</u>
    <s v-if="stock > 10">many</s>
    <em v-else-if="stock === 0">none</em>
  </div>
</template>`})
	chain, ok := vue.Of(frontendtest.Named(t, codebase, "Element", "b")).SwitchCaseChain()
	if !ok || chain.Subject != "status" || len(chain.Branches) != 3 {
		t.Errorf("the status chain reads as %+v", chain)
	}
	if vue.Of(frontendtest.Named(t, codebase, "Element", "s")).HeadsSwitchCase() {
		t.Error("a range guard heads a switch case")
	}
}

func TestAFingerprintIgnoresFormattingAndAShapeIgnoresValues(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{"List.vue": `<template>
  <div>
    <ul class="a" :title="x"><li>{{ one }}</li></ul>
    <ul   :title="x"
          class="a"><li>{{  one  }}</li></ul>
    <ul class="b" :title="y"><li>{{ two }}</li></ul>
  </div>
</template>`})
	lists := codebase.WhereKind("Element").Where(func(m engine.Match) bool { return m.Name() == "ul" }).Get()
	first, second, third := vue.Of(lists[0]), vue.Of(lists[1]), vue.Of(lists[2])
	if first.StructureHash() != second.StructureHash() {
		t.Error("the same markup formatted apart fingerprints apart")
	}
	if first.StructureHash() == third.StructureHash() || first.ShapeHash() != third.ShapeHash() {
		t.Error("markup differing only in values is not one shape with two fingerprints")
	}
}

func TestAComponentReadsItsPropsAndScriptTypes(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{
		"types.ts": `export interface Props { items: string[]; title?: string }`,
		"List.vue": `<script setup lang="ts">
import { ref, computed } from 'vue'
import type { Props } from './types'
const props = defineProps<Props>()
const rows = ref<number[]>([])
const count = computed(() => rows.value.length)
function reset() {}
</script>
<template><p>{{ props.title }}</p></template>`,
	})
	component := vue.ComponentOf(frontendtest.Named(t, codebase, "Element", "p"))
	var names []string
	for _, prop := range component.Props(codebase) {
		names = append(names, prop.Name())
	}
	if !slices.Equal(names, []string{"items", "title"}) {
		t.Errorf("the props followed into types.ts are %v", names)
	}
	if items, ok := component.TypeOf(codebase, "items"); !ok || !typescript.IsArray(items) {
		t.Error("the items prop is not read as an array")
	}
	if rows, ok := component.TypeOf(codebase, "rows"); !ok || !typescript.IsArray(rows) {
		t.Error("a ref of an array is not read as its array")
	}
	if component.PropsVariable() != "props" {
		t.Errorf("the props are held under %q", component.PropsVariable())
	}
	if component.ReadsMember("props", "title") {
		t.Error("a read in the template counts as the script's")
	}
	if !slices.Contains(component.LocalNames(), "reset") || !slices.Contains(component.LocalNames(), "count") {
		t.Errorf("the script's top-level names are %v", component.LocalNames())
	}
}

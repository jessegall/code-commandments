package frontend_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
)

func sent(t *testing.T, codebase *engine.Codebase, kind string) engine.Match {
	t.Helper()
	for _, node := range codebase.WhereKind(kind).Get() {
		return node.Receiver()
	}

	t.Fatalf("no %s in the sources", kind)

	return engine.Match{}
}

func TestWhatANodeIsSentToIsAnsweredForTheFrontend(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{
		"src/till.ts": `export class Till {
    open(): void {}
}

export function start(till: Till): void {
    till.open();
}

export function named(till: Till): string {
    return till.constructor.name;
}

export function free(): void {
    start(new Till());
}
`,
	})

	if read := sent(t, codebase, "PropertyAccessExpression"); read.Written() != "till" {
		t.Errorf("a member read is sent to %q", read.Written())
	}

	if called := sent(t, codebase, "CallExpression"); called.Written() != "till" {
		t.Errorf("a method call is sent to %q", called.Written())
	}
}

func TestACallOfABareNameIsSentToNobody(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{
		"src/plain.ts": `export function one(): number {
    return two();
}

export function two(): number {
    return 2;
}
`,
	})

	if read := sent(t, codebase, "CallExpression"); read.Exists() {
		t.Errorf("a call of a bare name is sent to %q", read.Written())
	}
}

func TestWhatANodeDecidesByIsAnsweredForTheFrontend(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{
		"src/decide.ts": `export function decide(ready: boolean, lines: string[]): number {
    if (ready) {
        return 1;
    }

    while (ready) {
        return 2;
    }

    for (const line of lines) {
        void line;
    }

    return ready ? 3 : 4;
}
`,
	})

	for kind, want := range map[string]string{
		"IfStatement":           "ready",
		"WhileStatement":        "ready",
		"ConditionalExpression": "ready",
	} {
		for _, node := range codebase.WhereKind(kind).Get() {
			if decided := node.Condition(); decided.Written() != want {
				t.Errorf("a %s decides by %q", kind, decided.Written())
			}

			break
		}
	}

	for _, node := range codebase.WhereKind("ForOfStatement").Get() {
		if decided := node.Condition(); decided.Exists() {
			t.Errorf("a for-of decides by %q, and it decides nothing", decided.Written())
		}
	}
}

func TestBothAnswersHoldInsideAVueComponent(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{
		"src/Till.vue": `<script setup lang="ts">
const till = { open: () => undefined, ready: true }

if (till.ready) {
    till.open()
}
</script>

<template>
    <button @click="till.open()">Open</button>
</template>
`,
	})

	if read := sent(t, codebase, "PropertyAccessExpression"); read.Written() != "till" {
		t.Errorf("a member read in a component is sent to %q", read.Written())
	}

	for _, node := range codebase.WhereKind("IfStatement").Get() {
		if decided := node.Condition(); decided.Written() != "till.ready" {
			t.Errorf("a branch in a component decides by %q", decided.Written())
		}

		break
	}
}

func TestVueSetupStateIsPerInstanceAndComposablesReturnRefs(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{
		"src/Counter.vue": `<script setup lang="ts">
let count = 0;
function advance() { count += 1; }
</script>
`,
		"src/counter.ts": `import { ref as vueRef } from "vue";
let count = 0;
export function advance() { count += 1; }
const global = vueRef(false);
export function useCounter() {
    const current = vueRef(0);
    return { current, global, advance };
}
export function totals() {
    return { first: 1, second: 2 };
}
`,
	})
	static := codebase.Where(engine.Match.MutatesStaticState).Get()
	if len(static) != 1 || static[0].Source().Language() == "vue" {
		t.Fatalf("only the module assignment should be static, found %v", static)
	}
	records := codebase.WhereIs(engine.Return).Where(engine.Match.ReturnsRecordBag).Get()
	if len(records) != 1 || records[0].EnclosingFunction().Name() != "totals" {
		t.Fatalf("only the ordinary record should be reported, found %v", records)
	}
}

func TestTypescriptNamedCallbacksAndModuleOwnedRecordBehavior(t *testing.T) {
	codebase := frontendtest.FromSource(t, map[string]string{
		"src/panes.ts": `import type { Ref, ComputedRef } from "vue";
export type Pane = { id: string; label: string; children: string[] };
export function close(pane: Pane) {
    const first = pane.children.length;
    return pane.id + pane.label + first;
}
export function drag(store: { a: Ref<string>; b: ComputedRef<string>; c: Ref<string> }) {
    return store.a.value + store.b.value + store.c.value;
}
type Report = { index: number; label: string };
export const reports = ["one"].map((label, index): Report => {
    return { index, label };
});
export function loose() { return { index: 1, label: "one" }; }
`,
		"src/caller.ts": `import type { Pane } from "./panes";
export function summary(pane: Pane) {
    return pane.id + pane.label + pane.children.length;
}
`,
	})
	records := codebase.WhereIs(engine.Return).Where(engine.Match.ReturnsRecordBag).Get()
	if len(records) != 1 || records[0].EnclosingFunction().Name() != "loose" {
		t.Fatalf("only the loose record should be reported, found %v", records)
	}
	envy := codebase.WhereFunction().Where(engine.Match.EnviesAnotherObject).Get()
	if len(envy) != 1 || envy[0].Name() != "summary" {
		for _, found := range envy {
			t.Logf("%s is reported", found.Name())
		}
		t.Fatal("only the operation outside the source module should be reported")
	}
}

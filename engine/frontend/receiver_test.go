package frontend_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/frontend/frontendtest"
)

// The engine asks every language the same two questions — what is this sent to, and what does this decide by — and
// the frontend answered neither, so a rule stepping onto a receiver or a condition found nothing in TypeScript and
// in Vue while finding it in PHP, Python and C#. These hold both answers, for the shapes each language writes them
// in.

// sent is the receiver of the first node of that kind, as the engine answers it.
func sent(t *testing.T, codebase *engine.Codebase, kind string) engine.Match {
	t.Helper()
	for _, node := range codebase.WhereKind(kind).Get() {
		return node.Receiver()
	}

	t.Fatalf("no %s in the sources", kind)

	return engine.Match{}
}

// TestWhatANodeIsSentToIsAnsweredForTheFrontend holds the receiver to meaning the same thing it means elsewhere:
// the object, whether the rule asks it of the read or of the call around it.
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

	// A call is its own node here, where PHP's method call is one node holding both, so the call is read through
	// what it calls and means the same object.
	if called := sent(t, codebase, "CallExpression"); called.Written() != "till" {
		t.Errorf("a method call is sent to %q", called.Written())
	}
}

// TestACallOfABareNameIsSentToNobody holds the honest empty answer: a free function is sent to no object, and the
// receiver must say so rather than name the function itself.
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

// TestWhatANodeDecidesByIsAnsweredForTheFrontend holds the condition for each shape that decides by one, and holds
// a for-of to deciding nothing: it walks what it is given, and its expression is that rather than a question.
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

// TestBothAnswersHoldInsideAVueComponent holds them where a component's script is read rather than a module's: the
// same tree, reached through the file a reader actually writes.
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

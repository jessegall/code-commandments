package golang_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/golang"
)

// TestARangeLoopNamesTheItemItBinds holds Go's answer to the one reading a shared rule asks of it: the name a
// loop binds for each item of what it walks. The Go bridge writes every field of a node in lower case, as the
// tree's own spelling and not the AST struct's, so a reader asking for `Value` finds nothing and the hook
// answers for no loop at all — which is how it stood from the day it was registered until this test.
func TestARangeLoopNamesTheItemItBinds(t *testing.T) {
	codebase := codebaseOf(t, map[string]string{
		"go.mod": "module example.com/shop\n",
		"shop/shop.go": `package shop

type Line struct{ Cents int }

func total(lines []Line) int {
	cents := 0
	for _, line := range lines {
		cents = cents + line.Cents
	}

	for at := range lines {
		cents = cents + lines[at].Cents
	}

	for cents > 0 {
		cents = cents - 1
	}

	return cents
}
`,
	})

	var named []string
	for _, loop := range golang.In(codebase).WhereIs(engine.Loop).Get() {
		named = append(named, loop.ElementName())
	}

	// The range with a value binds `line`; the one that ranges over the index alone binds no item, and a bare
	// `for` with a condition binds none either.
	want := []string{"line", "", ""}
	if len(named) != len(want) {
		t.Fatalf("the module holds %d loops, not %d: %v", len(named), len(want), named)
	}
	for at, name := range want {
		if named[at] != name {
			t.Errorf("loop %d binds %q, want %q", at, named[at], name)
		}
	}
}

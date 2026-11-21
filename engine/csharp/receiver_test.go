package csharp_test

import (
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func sentTo(t *testing.T, codebase *engine.Codebase, kind string) engine.Match {
	t.Helper()
	for _, node := range codebase.WhereKind(kind).Get() {
		return node.Receiver()
	}

	t.Fatalf("no %s in the sources", kind)

	return engine.Match{}
}


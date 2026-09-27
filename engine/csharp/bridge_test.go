package csharp_test

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/bridge/bridgetest"
	"github.com/jessegall/code-commandments/engine/csharp"
)

func TestTheBridgeScansCSharpIntoACodebase(t *testing.T) {
	bridgetest.Roslyn(t, os.TempDir())
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(root, "Cart.cs"), []byte("namespace Shop;\n\npublic sealed class Cart { }\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	codebase, err := csharp.Here().Scan(root)
	if err != nil {
		t.Fatal(err)
	}
	if types := csharp.In(codebase).WhereType().Count(); types != 1 {
		t.Errorf("the scan holds %d types, not the Cart", types)
	}
}

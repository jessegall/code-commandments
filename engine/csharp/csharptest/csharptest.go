// Package csharptest builds a codebase from C# sources for a test, through the real bridge.
package csharptest

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/csharp"
)

// FromSource is the codebase the C# sources describe, keyed by their path under one project folder, as the
// bridge and the engine read them: `{"Shop/Cart.cs": "namespace Shop; public sealed class Cart { }"}`.
func FromSource(t testing.TB, sources map[string]string) *engine.Codebase {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for path, source := range sources {
		file := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(file), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(file, []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	stream, err := bridge.Once(bridge.TestRoslyn(t, root), root)
	if err != nil {
		t.Fatal(err)
	}

	return engine.Load(stream)
}

// First is the first C# node of the kind in the codebase's file whose path ends in the one given to FromSource.
func First(t testing.TB, codebase *engine.Codebase, path, kind string) csharp.Node {
	t.Helper()
	for _, file := range codebase.Files() {
		if !strings.HasSuffix(file.Path, "/"+path) {
			continue
		}
		for _, match := range file.Match(0).Descendants() {
			if match.Kind() == kind {
				return csharp.Node{Match: match}
			}
		}
		t.Fatalf("no %s in %s", kind, path)
	}
	t.Fatalf("no file %s", path)

	return csharp.Node{}
}

// Package frontendtest builds a codebase from Vue and TypeScript sources for a test, through the real bridge.
package frontendtest

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/frontend"
)

// FromSource is the codebase the sources describe, keyed by their path under one project folder, as the bridge
// and the engine read them: `{"Cart.vue": "<template>...</template>", "types.ts": "export interface ..."}`.
func FromSource(t testing.TB, sources map[string]string) *engine.Codebase {
	t.Helper()
	if _, err := exec.LookPath("node"); err != nil {
		t.Skip("node is not on PATH")
	}
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
	codebase, err := frontend.Here().Scan(root)
	if err != nil {
		t.Fatal(err)
	}

	return codebase
}

// Where is the first node of the kind whose source text is the one given; the test fails when there is none.
func Where(t testing.TB, codebase *engine.Codebase, kind, text string) engine.Match {
	t.Helper()
	for _, match := range codebase.WhereKind(kind).Get() {
		if span, err := match.Span(); err == nil && strings.TrimSpace(span.Text()) == text {
			return match
		}
	}
	t.Fatalf("no %s reads %q", kind, text)

	return engine.Match{}
}

// Named is the first node of the kind with the name; the test fails when there is none.
func Named(t testing.TB, codebase *engine.Codebase, kind, name string) engine.Match {
	t.Helper()
	for _, match := range codebase.WhereKind(kind).Get() {
		if match.Name() == name {
			return match
		}
	}
	t.Fatalf("no %s is named %q", kind, name)

	return engine.Match{}
}

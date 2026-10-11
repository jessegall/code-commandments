package sync

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/binary"
)

// TestAProjectComposerDoesNotInstallTheToolInGetsNoComposerHook holds the wiring to a binary that is really there.
// A project the agent journal's plugin drives has a composer.json of its own and no package of ours, so the shim
// a composer event would name is never created and the project's next `composer install` would fail on our hook.
func TestAProjectComposerDoesNotInstallTheToolInGetsNoComposerHook(t *testing.T) {
	driven := t.TempDir()
	composer := filepath.Join(driven, "composer.json")
	declared := `{"name": "acme/app", "require": {"acme/other": "^1.0"}}`

	write(t, composer, declared)
	write(t, filepath.Join(driven, ".journal", "plugins", "code-commandments", ".journal-plugin", "plugin.json"), "{}")
	write(t, filepath.Join(driven, ".journal", "plugins", "code-commandments", binary.Release), "#!/bin/sh\n")

	changed, wires, err := EnsureComposerHook(driven)
	if err != nil {
		t.Fatal(err)
	}
	if changed || wires {
		t.Errorf("a project the plugin drives was wired (changed %v, wires %v)", changed, wires)
	}
	if got := read(t, composer); got != declared {
		t.Errorf("its composer.json was rewritten to %q", got)
	}
}

// TestAComposerProjectIsStillWiredToResync holds the wiring where composer does install the tool: its own install
// and update are what put a new curriculum in the project, so they have to call sync.
func TestAComposerProjectIsStillWiredToResync(t *testing.T) {
	project := t.TempDir()
	write(t, filepath.Join(project, "composer.json"), `{"name": "acme/app"}`)

	changed, wires, err := EnsureComposerHook(project)
	if err != nil {
		t.Fatal(err)
	}
	if !changed || !wires {
		t.Fatalf("a composer project was left unwired (changed %v, wires %v)", changed, wires)
	}

	if got := read(t, filepath.Join(project, "composer.json")); !strings.Contains(got, "@php "+binary.Shim+" sync") {
		t.Errorf("its composer events do not call the shim: %s", got)
	}
}

func write(t *testing.T, path, text string) {
	t.Helper()

	if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(path, []byte(text), 0o644); err != nil {
		t.Fatal(err)
	}
}

func read(t *testing.T, path string) string {
	t.Helper()

	text, err := os.ReadFile(path)
	if err != nil {
		t.Fatal(err)
	}

	return string(text)
}

package binary

import (
	"os"
	"path/filepath"
	"testing"
)

func TestAJournalDrivenProjectNamesThePluginsOwnBinary(t *testing.T) {
	driven := t.TempDir()
	installed := filepath.Join(driven, ".journal", "plugins", "code-commandments")
	if err := os.MkdirAll(filepath.Join(installed, ".journal-plugin"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(installed, ".journal-plugin", "plugin.json"), []byte("{}"), 0o644); err != nil {
		t.Fatal(err)
	}

	if got := Invocation(driven); got != Name {
		t.Errorf("with the plugin's manifest but no binary yet, the invocation is %q, want the bare %q", got, Name)
	}

	if err := os.MkdirAll(filepath.Join(installed, "bin"), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(installed, Release), []byte("#!/bin/sh\n"), 0o755); err != nil {
		t.Fatal(err)
	}

	want := ".journal/plugins/code-commandments/" + Release
	if got := Invocation(driven); got != want {
		t.Errorf("a journal-driven project is told to run %q, want %q", got, want)
	}

	withComposer := t.TempDir()
	if err := os.WriteFile(filepath.Join(withComposer, "composer.json"), []byte("{}"), 0o644); err != nil {
		t.Fatal(err)
	}
	if got := Invocation(withComposer); got != Shim {
		t.Errorf("a composer project is told to run %q, want %q", got, Shim)
	}

	if got := Invocation(t.TempDir()); got != Name {
		t.Errorf("a project with neither is told to run %q, want the bare %q", got, Name)
	}

	both := filepath.Join(driven, "composer.json")
	if err := os.WriteFile(both, []byte("{}"), 0o644); err != nil {
		t.Fatal(err)
	}
	if got := Invocation(driven); got != want {
		t.Errorf("a journal-driven project that also has a composer.json is told to run %q, want the plugin's own %q, since no vendor/ is there", got, want)
	}

	vendor := filepath.Join(driven, Shim)
	if err := os.MkdirAll(filepath.Dir(vendor), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(vendor, []byte("#!/bin/sh\n"), 0o755); err != nil {
		t.Fatal(err)
	}
	if got := Invocation(driven); got != Shim {
		t.Errorf("once the composer shim is really installed it is told to run %q, want %q", got, Shim)
	}
}

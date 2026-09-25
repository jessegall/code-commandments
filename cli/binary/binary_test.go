package binary

import (
	"os"
	"path/filepath"
	"testing"
)

func TestAProjectRunsTheToolThroughPHPOnlyWhenItInstallsItSo(t *testing.T) {
	composer, bare := t.TempDir(), t.TempDir()

	if err := os.WriteFile(filepath.Join(composer, "composer.json"), []byte("{}"), 0o644); err != nil {
		t.Fatal(err)
	}

	if got := Invocation(composer); got != "vendor/bin/commandments" {
		t.Errorf("a composer project runs %q", got)
	}

	if got := Invocation(bare); got != "commandments" {
		t.Errorf("a project with no PHP runs %q", got)
	}
}

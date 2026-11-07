package binary

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/workspace"
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

func TestThePackagesOwnCheckoutRunsItsOwnBin(t *testing.T) {
	checkout := t.TempDir()
	for _, file := range []string{"composer.json", "bin/commandments"} {
		if err := os.MkdirAll(filepath.Dir(filepath.Join(checkout, file)), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(filepath.Join(checkout, file), []byte("{}"), 0o755); err != nil {
			t.Fatal(err)
		}
	}

	if got := Invocation(checkout); got != "bin/commandments" {
		t.Errorf("the package's own checkout runs %q", got)
	}
}

func TestJudgingIsTheAliasWhereTheJournalCanRunIt(t *testing.T) {
	driven := t.TempDir()
	installed := filepath.Join(driven, ".journal", "plugins", workspace.JournalPlugin)

	for path, contents := range map[string]string{
		filepath.Join(installed, ".journal-plugin", "plugin.json"): "{}",
		filepath.Join(installed, Release):                          "",
	} {
		if err := os.MkdirAll(filepath.Dir(path), 0o755); err != nil {
			t.Fatal(err)
		}

		if err := os.WriteFile(path, []byte(contents), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	if got := Judging(driven); got != Alias {
		t.Errorf("a journal-driven project is told to run %q, want %q", got, Alias)
	}

	if got := Judging(t.TempDir()); got != Name+" judge" {
		t.Errorf("a project of its own is told to run %q, want %q", got, Name+" judge")
	}

	if got := JudgingChanges(driven); got != ChangesAlias {
		t.Errorf("a journal-driven project is told to run %q to judge its changes, want the alias %q, since the "+
			"journal's lines name no command an agent has to type out", got, ChangesAlias)
	}

	if got := JudgingChanges(t.TempDir()); got != Name+" judge --changes" {
		t.Errorf("a project of its own is told to run %q to judge its changes, want %q", got, Name+" judge --changes")
	}
}

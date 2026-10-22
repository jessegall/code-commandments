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

// TestThePackagesOwnCheckoutRunsItsOwnBin holds the one composer project with no shim of the package: composer never
// links a package's own bin into its own vendor, so the checkout is told the bin/commandments it has.
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

// TestJudgingIsTheAliasWhereTheJournalCanRunIt holds the one command an agent is told to run: where the journal
// drives the project it is the alias the plugin's manifest ships, so a briefing never names a file inside
// .journal/plugins, and anywhere else it is the binary with its verb.
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
}

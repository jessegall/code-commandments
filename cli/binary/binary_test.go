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

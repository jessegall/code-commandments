package sins_test

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/sins"
)

func TestAComposerPackageIsInstalledWhenComposerInstalledIt(t *testing.T) {
	root := t.TempDir()
	vendor := filepath.Join(root, "vendor", "composer")
	if err := os.MkdirAll(vendor, 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(vendor, "installed.json"), []byte(`{"packages":[{"name":"illuminate/support"}]}`), 0o644); err != nil {
		t.Fatal(err)
	}
	if !(sins.Package{Name: "illuminate/support", Ecosystem: sins.Composer}).InstalledIn(root) {
		t.Error("an installed package reads as missing")
	}
	if (sins.Package{Name: "spatie/laravel-data", Ecosystem: sins.Composer}).InstalledIn(root) {
		t.Error("a package composer did not install reads as installed")
	}
}

func TestAProjectWithoutAManifestHasEveryPackage(t *testing.T) {
	if !(sins.Package{Name: "spatie/laravel-data", Ecosystem: sins.Composer}).InstalledIn(t.TempDir()) {
		t.Error("a missing manifest silences the package's rules")
	}
}

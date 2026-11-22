package config

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/engine"
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
	if !InstalledIn(root)(sins.Package{Name: "illuminate/support", Ecosystem: sins.Composer}) {
		t.Error("an installed package reads as missing")
	}
	if InstalledIn(root)(sins.Package{Name: "spatie/laravel-data", Ecosystem: sins.Composer}) {
		t.Error("a package composer did not install reads as installed")
	}
}

func TestAProjectComposerNeverInstalledIntoHasNoPackage(t *testing.T) {
	if InstalledIn(t.TempDir())(sins.Package{Name: "spatie/laravel-data", Ecosystem: sins.Composer}) {
		t.Error("a project with no installed packages runs a package's rules")
	}
}

func TestAPackageAnotherReplacesOrProvidesIsInstalled(t *testing.T) {
	root := t.TempDir()
	vendor := filepath.Join(root, "vendor", "composer")
	if err := os.MkdirAll(vendor, 0o755); err != nil {
		t.Fatal(err)
	}
	manifest := `{"packages":[{"name":"laravel/framework","replace":{"illuminate/support":"self.version"},"provide":{"psr/log-implementation":"1.0"}}]}`
	if err := os.WriteFile(filepath.Join(vendor, "installed.json"), []byte(manifest), 0o644); err != nil {
		t.Fatal(err)
	}
	for _, name := range []string{"illuminate/support", "psr/log-implementation"} {
		if !InstalledIn(root)(sins.Package{Name: name, Ecosystem: sins.Composer}) {
			t.Errorf("%s reads as missing", name)
		}
	}
}

func TestAProjectHoldingOnlyItsLockOrOnlyItsInstalledListAnswersTheSame(t *testing.T) {
	manifests := map[string]string{
		"composer.lock":                  `{"packages":[{"name":"laravel/framework","version":"v12.0.0","replace":{"illuminate/support":"self.version"}}]}`,
		"vendor/composer/installed.json": `{"packages":[{"name":"laravel/framework","version":"v12.0.0","replace":{"illuminate/support":"self.version"}}]}`,
	}
	for path, text := range manifests {
		root := t.TempDir()
		full := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(full), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(full, []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
		if !InstalledIn(root)(sins.Package{Name: "illuminate/support", Ecosystem: sins.Composer}) {
			t.Errorf("a project holding only %s does not hold the package laravel/framework replaces", path)
		}
		if version := engine.PackagesIn(root)["composer"]["laravel/framework"]; version != "12.0.0" {
			t.Errorf("a project holding only %s reads laravel/framework at %q, not 12.0.0", path, version)
		}
	}
}

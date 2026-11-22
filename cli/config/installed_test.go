package config

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/sins"
)

func TestAPackageIsReadFromTheComposerProjectTheJudgedFolderBelongsTo(t *testing.T) {
	project := filepath.Join(t.TempDir(), "shop")
	written := map[string]string{
		"composer.json":                  `{"require": {"laravel/framework": "^12"}}`,
		"vendor/composer/installed.json": `{"packages": [{"name": "laravel/framework", "replace": {"illuminate/support": "self.version"}}]}`,
		"app/Mail/Invoice.php":           "<?php\n",
	}
	for path, text := range written {
		full := filepath.Join(project, path)
		if err := os.MkdirAll(filepath.Dir(full), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(full, []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	support := sins.Package{Name: "illuminate/support", Ecosystem: sins.Composer}

	if !InstalledIn(filepath.Join(project, "app", "Mail"))(support) {
		t.Error("a package laravel/framework replaces is not installed for a folder inside the project")
	}
	if InstalledIn(t.TempDir())(support) {
		t.Error("a folder of no Composer project holds the package")
	}
}

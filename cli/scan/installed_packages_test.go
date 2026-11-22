package scan

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func TestAKeptLineIsAskedAgainWhenTheProjectInstallsItsPackages(t *testing.T) {
	project := t.TempDir()
	source := filepath.Join(project, "app", "Order.php")
	if err := os.MkdirAll(filepath.Dir(source), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(source, []byte("<?php class Order {}\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(filepath.Join(project, "composer.lock"), []byte(`{"packages": []}`), 0o644); err != nil {
		t.Fatal(err)
	}
	before := keptLines{folder: project, root: project, identity: "bridge", packages: engine.InstalledPackages([]string{project})}
	installed := filepath.Join(project, "vendor", "composer", "installed.json")
	if err := os.MkdirAll(filepath.Dir(installed), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(installed, []byte(`{"packages": [{"name": "laravel/framework", "version": "v11.9.0"}]}`), 0o644); err != nil {
		t.Fatal(err)
	}
	after := keptLines{folder: project, root: project, identity: "bridge", packages: engine.InstalledPackages([]string{project})}

	if before.recordOf(source, nil) == after.recordOf(source, nil) {
		t.Errorf("a file read before and after the project installed its packages is served the same kept line")
	}
	again := keptLines{folder: project, root: project, identity: "bridge", packages: engine.InstalledPackages([]string{project})}
	if after.recordOf(source, nil) != again.recordOf(source, nil) {
		t.Errorf("the same installed packages name a different kept line")
	}
}

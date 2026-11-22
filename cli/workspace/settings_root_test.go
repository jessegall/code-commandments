package workspace

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"
)

func TestAProjectInsideACheckoutReadsItsOwnSettings(t *testing.T) {
	checkout, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	if out, err := exec.Command("git", "-C", checkout, "init", "-q").CombinedOutput(); err != nil {
		t.Fatalf("git init: %s", out)
	}
	nested := filepath.Join(checkout, "fixtures", "shop")
	for _, folder := range []string{checkout, nested} {
		if err := os.MkdirAll(filepath.Dir(JSONConfig(folder)), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(JSONConfig(folder), []byte("{}\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	if root := SettingsRoot(filepath.Join(nested, "src")); root != nested {
		t.Errorf("a folder of the nested project reads the settings of %s, not its own at %s", root, nested)
	}
	if root := SettingsRoot(filepath.Join(checkout, "src")); root != checkout {
		t.Errorf("a folder outside the nested project reads the settings of %s, not the checkout's", root)
	}
}

package binary

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

// TestTheLauncherRunsTheBinaryTheShimLastRan holds bin/hook to the binary the shim beside it recorded, and to
// the shim whenever that record cannot be trusted: none written, its binary gone, or the environment naming
// another.
func TestTheLauncherRunsTheBinaryTheShimLastRan(t *testing.T) {
	if _, err := exec.LookPath("php"); err != nil {
		t.Skip("no php to run the shim")
	}
	checkout := t.TempDir()
	put := func(path, content string, mode os.FileMode) {
		t.Helper()
		if err := os.MkdirAll(filepath.Dir(filepath.Join(checkout, path)), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(filepath.Join(checkout, path), []byte(content), mode); err != nil {
			t.Fatal(err)
		}
	}
	copied := func(path string) string {
		t.Helper()
		content, err := os.ReadFile(filepath.Join("..", "..", path))
		if err != nil {
			t.Fatal(err)
		}

		return string(content)
	}
	put("bin/commandments", copied("bin/commandments"), 0o755)
	put("bin/hook", copied("bin/hook"), 0o755)
	put("scripts/dev", "", 0o755)
	put("bin/commandments-go", "#!/bin/sh\necho \"binary $*\"\n", 0o755)
	run := func(environment ...string) string {
		t.Helper()
		hook := exec.Command("sh", filepath.Join(checkout, "bin/hook"), "hooks", "now")
		hook.Env = append(os.Environ(), environment...)
		out, err := hook.CombinedOutput()
		if err != nil {
			t.Fatalf("%v: %s", err, out)
		}

		return strings.TrimSpace(string(out))
	}
	record := filepath.Join(checkout, "bin/.binary")

	if got := run(); got != "binary hooks now" {
		t.Errorf("with no record the hook goes through the shim to the binary, and writes %q", got)
	}
	if written, _ := os.ReadFile(record); string(written) != filepath.Join(checkout, "bin/commandments-go") {
		t.Fatalf("the shim records %q", written)
	}

	put("bin/commandments", "<?php echo 'shim ', implode(' ', array_slice($argv, 1)), \"\\n\";", 0o755)
	if got := run(); got != "binary hooks now" {
		t.Errorf("with a record the hook runs the binary without the shim, and writes %q", got)
	}
	if got := run("COMMANDMENTS_RELEASE=v5.0.0"); got != "shim hooks now" {
		t.Errorf("with a release pinned the hook leaves it to the shim, and writes %q", got)
	}
	if err := os.Remove(filepath.Join(checkout, "bin/commandments-go")); err != nil {
		t.Fatal(err)
	}
	if got := run(); got != "shim hooks now" {
		t.Errorf("with the recorded binary gone the hook leaves it to the shim, and writes %q", got)
	}
}

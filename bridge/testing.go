package bridge

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"
)

// TestMypy is the Python bridge's command for a test, run by an interpreter that already has mypy:
// $COMMANDMENTS_MYPY_PYTHON, else the one the PHP tool built, so a test never installs anything. It skips the
// test when there is none.
func TestMypy(t testing.TB) []string {
	t.Helper()
	python := os.Getenv("COMMANDMENTS_MYPY_PYTHON")
	if python == "" {
		home, _ := os.UserHomeDir()
		built, _ := filepath.Glob(filepath.Join(home, ".cache/code-commandments/mypy-bridge/*/venv/bin/python"))
		for _, candidate := range built {
			if exec.Command(candidate, "-c", "import mypy").Run() == nil {
				python = candidate
				break
			}
		}
	}
	if python == "" {
		t.Skip("no Python with mypy: set COMMANDMENTS_MYPY_PYTHON")
	}
	t.Setenv("COMMANDMENTS_MYPY_PYTHON", python)
	t.Setenv("XDG_CACHE_HOME", t.TempDir())
	command, err := Mypy()
	if err != nil {
		t.Fatal(err)
	}

	return command
}

// TestRoslyn is the C# bridge's command for a test over the roots, run in a container of its prebuilt image. It
// skips the test when there is no Docker to run it in, and fails it when the image is not built.
func TestRoslyn(t testing.TB, roots ...string) []string {
	t.Helper()
	if exec.Command("docker", "info").Run() != nil {
		t.Skip("no Docker to run the C# bridge in")
	}
	command, err := Roslyn(roots...)
	if err != nil {
		t.Fatal(err)
	}

	return command
}

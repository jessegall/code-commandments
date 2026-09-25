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

// TestRoslyn is the C# bridge's command for a test, built once per version of its sources in the user's cache.
// It skips the test when there is no dotnet.
func TestRoslyn(t testing.TB) []string {
	t.Helper()
	if _, err := exec.LookPath("dotnet"); err != nil {
		t.Skip("no dotnet on the PATH")
	}
	command, err := Roslyn()
	if err != nil {
		t.Fatal(err)
	}

	return command
}

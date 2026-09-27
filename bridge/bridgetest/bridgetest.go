// Package bridgetest holds what a test needs of the bridges: a command for each, and the skips when the machine has
// no way to run one. Only tests import it, so the tool itself never carries Go's testing package.
package bridgetest

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
)

// Mypy is the Python bridge's command for a test, run by an interpreter that already has mypy:
// $COMMANDMENTS_MYPY_PYTHON, else the one the PHP tool built, so a test never installs anything. It skips the
// test when there is none.
func Mypy(t testing.TB) []string {
	t.Helper()
	t.Setenv("COMMANDMENTS_MYPY_PYTHON", MypyPython(t))
	t.Setenv("XDG_CACHE_HOME", t.TempDir())
	command, err := bridge.Mypy()
	if err != nil {
		t.Fatal(err)
	}

	return command
}

// MypyPython is an interpreter that already has mypy: $COMMANDMENTS_MYPY_PYTHON, else one a bridge built under
// the cache folder. It skips the test when there is none.
func MypyPython(t testing.TB) string {
	t.Helper()
	if python := os.Getenv("COMMANDMENTS_MYPY_PYTHON"); python != "" {
		return python
	}
	home, _ := os.UserHomeDir()
	built, _ := filepath.Glob(filepath.Join(home, ".cache", "code-commandments", "mypy-tree", "*", "venv", "bin", "python"))
	for _, candidate := range built {
		if exec.Command(candidate, "-c", "import mypy").Run() == nil {
			return candidate
		}
	}
	t.Skip("no Python with mypy: set COMMANDMENTS_MYPY_PYTHON")

	return ""
}

// Roslyn is the C# bridge's command for a test over the roots, run in a capped container of its image, as every
// development run of it is. It skips the test when there is no Docker to run it in, and fails it when the image is not
// built.
func Roslyn(t testing.TB, roots ...string) []string {
	t.Helper()
	if exec.Command("docker", "info").Run() != nil {
		t.Skip("no Docker to run the C# bridge in")
	}
	command, err := bridge.RoslynInDocker(roots)
	if err != nil {
		t.Fatal(err)
	}

	return command
}

package bridge

import (
	"embed"
	"fmt"
	"os"
	"os/exec"
	"path/filepath"

	"github.com/jessegall/code-commandments/bridge/bundle"
)

// mypy is the Python bridge's sources, carried in the binary and written out beside the environment they run in.
//
//go:embed mypy/tree.py mypy/bridge.py mypy/requirements.txt
var mypy embed.FS

// mypyTree is the bridge's sources, written out as a folder the virtual environment is built in beside them.
var mypyTree = bundle.Embedded("mypy-tree", mypy, "mypy")

// Mypy is the command that runs the Python tree bridge. The first run for a version of its sources builds it a
// virtual environment with the pinned mypy under the cache folder; $COMMANDMENTS_MYPY_PYTHON, an interpreter
// that already has mypy, skips the build.
func Mypy() ([]string, error) {
	folder, err := mypyTree.Folder()
	if err != nil {
		return nil, err
	}
	tree := filepath.Join(folder, "tree.py")
	if python := os.Getenv("COMMANDMENTS_MYPY_PYTHON"); python != "" {
		return []string{python, tree}, nil
	}
	python := filepath.Join(folder, "venv", "bin", "python")
	if _, err := os.Stat(filepath.Join(folder, "ready")); err == nil {
		return []string{python, tree}, nil
	}

	return []string{python, tree}, buildMypy(folder)
}

// buildMypy builds a virtual environment with the pinned mypy in the sources' folder, marked ready only once it
// is whole.
func buildMypy(folder string) error {
	python, err := exec.LookPath("python3")
	if err != nil {
		return fmt.Errorf("the Python bridge needs python3 on the PATH: %w", err)
	}
	venv := filepath.Join(folder, "venv")
	steps := [][]string{
		{python, "-m", "venv", venv},
		{filepath.Join(venv, "bin", "pip"), "install", "-q", "-r", filepath.Join(folder, "requirements.txt")},
	}
	for _, step := range steps {
		if out, err := exec.Command(step[0], step[1:]...).CombinedOutput(); err != nil {
			return Failed(step, err, string(out))
		}
	}

	return os.WriteFile(filepath.Join(folder, "ready"), nil, 0o644)
}

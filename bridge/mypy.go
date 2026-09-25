package bridge

import (
	"crypto/sha1"
	"embed"
	"encoding/hex"
	"fmt"
	"io/fs"
	"os"
	"os/exec"
	"path/filepath"
)

// mypy is the Python bridge's sources, carried in the binary and written out beside the environment they run in.
//
//go:embed mypy/tree.py mypy/bridge.py mypy/requirements.txt
var mypy embed.FS

// Mypy is the command that runs the Python tree bridge. The first run for a version of its sources builds it a
// virtual environment with the pinned mypy under the cache folder; $COMMANDMENTS_MYPY_PYTHON, an interpreter
// that already has mypy, skips the build.
func Mypy() ([]string, error) {
	folder, err := mypyFolder()
	if err != nil {
		return nil, err
	}
	tree := filepath.Join(folder, "tree.py")
	if python := os.Getenv("COMMANDMENTS_MYPY_PYTHON"); python != "" {
		return []string{python, tree}, writeMypy(folder)
	}
	python := filepath.Join(folder, "venv", "bin", "python")
	if _, err := os.Stat(filepath.Join(folder, "ready")); err == nil {
		return []string{python, tree}, nil
	}

	return []string{python, tree}, buildMypy(folder)
}

// mypyFolder is where this version of the sources lives: the cache folder, keyed by what the sources hold.
func mypyFolder() (string, error) {
	cache := os.Getenv("XDG_CACHE_HOME")
	if cache == "" {
		home, err := os.UserHomeDir()
		if err != nil {
			return "", err
		}
		cache = filepath.Join(home, ".cache")
	}
	hash := sha1.New()
	err := fs.WalkDir(mypy, "mypy", func(path string, entry fs.DirEntry, err error) error {
		if err != nil || entry.IsDir() {
			return err
		}
		source, err := mypy.ReadFile(path)
		hash.Write([]byte(path))
		hash.Write(source)

		return err
	})

	return filepath.Join(cache, "code-commandments", "mypy-tree", hex.EncodeToString(hash.Sum(nil))[:16]), err
}

// writeMypy writes the sources into the folder.
func writeMypy(folder string) error {
	if err := os.MkdirAll(folder, 0o755); err != nil {
		return err
	}

	return fs.WalkDir(mypy, "mypy", func(path string, entry fs.DirEntry, err error) error {
		if err != nil || entry.IsDir() {
			return err
		}
		source, err := mypy.ReadFile(path)
		if err != nil {
			return err
		}

		return os.WriteFile(filepath.Join(folder, filepath.Base(path)), source, 0o644)
	})
}

// buildMypy writes the sources and a virtual environment with the pinned mypy into the folder, marked ready
// only once it is whole.
func buildMypy(folder string) error {
	python, err := exec.LookPath("python3")
	if err != nil {
		return fmt.Errorf("the Python bridge needs python3 on the PATH: %w", err)
	}
	if err := writeMypy(folder); err != nil {
		return err
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

package bridge

import (
	"embed"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strings"
	"time"

	"github.com/jessegall/code-commandments/bridge/bundle"
	"github.com/jessegall/code-commandments/cli/atomic"
)

//go:embed mypy/tree.py mypy/session.py mypy/kept.py mypy/requirements.txt
var mypy embed.FS

var mypyTree = bundle.Embedded("mypy-tree", mypy, "mypy")

const mypyNeeds = "3.10 or newer"

const mypyPythonCheck = "import sys; print(\"%s %s\" % (sys.version.split()[0], sys.version_info >= (3, 10)))"

var mypyPythons = []string{"python3.14", "python3.13", "python3.12", "python3.11", "python3.10", "python3", "python"}

func Mypy() ([]string, error) {
	folder, err := mypyTree.Folder()
	if err != nil {
		return nil, err
	}
	tree := []string{filepath.Join(folder, "tree.py")}
	if lines, err := bundle.TreesFolder("python"); err == nil {
		tree = append(tree, "--cache="+lines)
	}
	if caches, err := mypyCaches(); err == nil {
		bundle.ForgetUnused(caches, time.Now())
		tree = append(tree, "--mypy-cache="+caches)
	}
	if python := os.Getenv("COMMANDMENTS_MYPY_PYTHON"); python != "" {
		return append([]string{python}, tree...), nil
	}
	python := filepath.Join(folder, "venv", "bin", "python")
	if runtime.GOOS == "windows" {
		python = filepath.Join(folder, "venv", "Scripts", "python.exe")
	}
	if _, err := os.Stat(filepath.Join(folder, "ready")); err == nil {
		return append([]string{python}, tree...), nil
	}

	return append([]string{python}, tree...), buildMypy(folder)
}

func mypyCaches() (string, error) {
	kept, err := bundle.KeptFolder()
	if err != nil {
		return "", err
	}

	return filepath.Join(kept, "mypy-cache"), nil
}

func pythonFolders() []string {
	return append(filepath.SplitList(os.Getenv("PATH")), "/opt/homebrew/bin", "/usr/local/bin")
}

func pythonsIn(folders []string) []string {
	var found []string
	for _, name := range mypyPythons {
		for _, folder := range folders {
			if python, err := exec.LookPath(filepath.Join(folder, name)); err == nil {
				found = append(found, python)
			}
		}
	}

	return found
}

func mypyPython(folders []string) (string, error) {
	var tooOld []string
	for _, python := range pythonsIn(folders) {
		out, err := exec.Command(python, "-c", mypyPythonCheck).Output()
		if err != nil {
			continue
		}
		version, enough, _ := strings.Cut(strings.TrimSpace(string(out)), " ")
		if enough == "True" {
			return python, nil
		}
		tooOld = append(tooOld, python+" "+version)
	}

	need := "Python " + mypyNeeds
	if len(tooOld) > 0 {
		need += " (found " + strings.Join(tooOld, ", ") + ")"
	}

	return "", ToolchainMissing{Language: "Python", Need: need}
}

func buildMypy(folder string) error {
	release := atomic.Hold(filepath.Join(folder, ".building"))
	defer release()

	if _, err := os.Stat(filepath.Join(folder, "ready")); err == nil {
		return nil
	}

	python, err := mypyPython(pythonFolders())
	if err != nil {
		return err
	}
	venv := filepath.Join(folder, "venv")
	pip := filepath.Join(venv, "bin", "pip")
	if runtime.GOOS == "windows" {
		pip = filepath.Join(venv, "Scripts", "pip.exe")
	}
	steps := [][]string{
		{python, "-m", "venv", "--clear", venv},
		{pip, "install", "-q", "-r", filepath.Join(folder, "requirements.txt")},
	}
	for _, step := range steps {
		if out, err := exec.Command(step[0], step[1:]...).CombinedOutput(); err != nil {
			return Failed(step, err, string(out))
		}
	}

	return os.WriteFile(filepath.Join(folder, "ready"), nil, 0o644)
}

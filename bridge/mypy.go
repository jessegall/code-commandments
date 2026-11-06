package bridge

import (
	"embed"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"

	"github.com/jessegall/code-commandments/bridge/bundle"
)

//go:embed mypy/tree.py mypy/session.py mypy/requirements.txt
var mypy embed.FS

var mypyTree = bundle.Embedded("mypy-tree", mypy, "mypy")

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
	if runtime.GOOS == "windows" {
		python = filepath.Join(folder, "venv", "Scripts", "python.exe")
	}
	if _, err := os.Stat(filepath.Join(folder, "ready")); err == nil {
		return []string{python, tree}, nil
	}

	return []string{python, tree}, buildMypy(folder)
}

func buildMypy(folder string) error {
	python, err := exec.LookPath("python3")
	if err != nil {
		python, err = exec.LookPath("python")
	}
	if err != nil {
		return ToolchainMissing{Language: "Python", Need: "python3 on the PATH"}
	}
	venv := filepath.Join(folder, "venv")
	pip := filepath.Join(venv, "bin", "pip")
	if runtime.GOOS == "windows" {
		pip = filepath.Join(venv, "Scripts", "pip.exe")
	}
	steps := [][]string{
		{python, "-m", "venv", venv},
		{pip, "install", "-q", "-r", filepath.Join(folder, "requirements.txt")},
	}
	for _, step := range steps {
		if out, err := exec.Command(step[0], step[1:]...).CombinedOutput(); err != nil {
			return Failed(step, err, string(out))
		}
	}

	return os.WriteFile(filepath.Join(folder, "ready"), nil, 0o644)
}

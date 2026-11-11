package bridge

import (
	"errors"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

func fakePython(t *testing.T, name, answer string) (string, string) {
	t.Helper()

	folder := t.TempDir()
	python := filepath.Join(folder, name)
	if err := os.WriteFile(python, []byte("#!/bin/sh\necho '"+answer+"'\n"), 0o755); err != nil {
		t.Fatal(err)
	}

	return folder, python
}

func mypyFolder(t *testing.T, requirements string) string {
	t.Helper()

	folder := t.TempDir()
	if err := os.WriteFile(filepath.Join(folder, "requirements.txt"), []byte(requirements), 0o644); err != nil {
		t.Fatal(err)
	}

	return folder
}

func TestAPythonTooOldForMypyIsPassedOverForANewerOne(t *testing.T) {
	old, _ := fakePython(t, "python3", "3.9.6 False")
	newer, expected := fakePython(t, "python3.12", "3.12.1 True")

	python, err := mypyPython([]string{old, newer})

	if err != nil {
		t.Fatal(err)
	}
	if python != expected {
		t.Errorf("chose %s, want %s", python, expected)
	}
}

func TestOnlyAPythonTooOldNamesWhatWasFoundAndWhatIsNeeded(t *testing.T) {
	old, python := fakePython(t, "python3", "3.9.6 False")

	_, err := mypyPython([]string{old})

	var missing ToolchainMissing
	if !errors.As(err, &missing) {
		t.Fatalf("the error is %v, not a missing toolchain", err)
	}
	for _, named := range []string{mypyNeeds, python, "3.9.6"} {
		if !strings.Contains(err.Error(), named) {
			t.Errorf("%q does not name %s", err.Error(), named)
		}
	}
}

func TestAFailedBuildOfTheMypyBridgeIsNotReady(t *testing.T) {
	folder := mypyFolder(t, "-r missing.txt\n")

	if err := buildMypy(folder); err == nil {
		t.Fatal("a requirements file that cannot be read still built")
	}

	if _, err := os.Stat(filepath.Join(folder, "ready")); !os.IsNotExist(err) {
		t.Errorf("a failed build left ready behind: %v", err)
	}
}

func TestABuildReplacesThePythonAFailedBuildLeftBehind(t *testing.T) {
	folder := mypyFolder(t, "")
	python := filepath.Join(folder, "venv", "bin", "python")
	if err := os.MkdirAll(filepath.Dir(python), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.Symlink("/bin/false", python); err != nil {
		t.Fatal(err)
	}

	if err := buildMypy(folder); err != nil {
		t.Fatal(err)
	}

	if out, err := exec.Command(python, "-c", mypyPythonCheck).CombinedOutput(); err != nil {
		t.Errorf("the built python does not run: %v %s", err, out)
	}
	if _, err := os.Stat(filepath.Join(folder, "ready")); err != nil {
		t.Errorf("a finished build is not ready: %v", err)
	}
}

func mypyCacheFolderOf(t *testing.T, python, data, path string) string {
	t.Helper()

	read := exec.Command(python, "-c", "import sys; sys.path.insert(0, 'mypy'); import session; print(session.cache_folder([sys.argv[1]]))", path)
	read.Env = append(os.Environ(), "JOURNAL_PLUGIN_DATA="+data)

	out, err := read.CombinedOutput()
	if err != nil {
		t.Fatalf("%v: %s", err, out)
	}

	return strings.TrimSpace(string(out))
}

func gitIn(t *testing.T, folder string, arguments ...string) {
	t.Helper()

	run := exec.Command("git", arguments...)
	run.Dir = folder

	if out, err := run.CombinedOutput(); err != nil {
		t.Fatalf("git %v: %v: %s", arguments, err, out)
	}
}

func TestAWorktreeSharesItsProjectsMypyCache(t *testing.T) {
	python := os.Getenv("COMMANDMENTS_MYPY_PYTHON")
	if python == "" {
		t.Skip("no python with mypy is named here")
	}

	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	project, worktree, data := filepath.Join(root, "project"), filepath.Join(root, "worktree"), filepath.Join(root, "data")
	if err := os.Mkdir(project, 0o755); err != nil {
		t.Fatal(err)
	}

	gitIn(t, project, "init", "-q")
	gitIn(t, project, "-c", "user.name=a", "-c", "user.email=a@b", "commit", "-q", "--allow-empty", "-m", "first")
	gitIn(t, project, "worktree", "add", "-q", worktree)

	if here, there := mypyCacheFolderOf(t, python, data, project), mypyCacheFolderOf(t, python, data, worktree); here != there {
		t.Errorf("the project caches in %s and its worktree in %s, so every new worktree starts cold", here, there)
	}
}

const modulesHeldAfterEachRequest = `
import gc, os, sys
sys.path.insert(0, 'mypy')
import tree
from mypy.nodes import MypyFile

said, sys.stdout = sys.stdout, open(os.devnull, 'w')
session, held = tree.Session(), []
for _ in range(4):
    tree.answered(session, '{"paths": ["%s"], "write": ["%s"]}' % (sys.argv[1], sys.argv[1]))
    held.append(sum(1 for each in gc.get_objects() if isinstance(each, MypyFile)))
print(' '.join(str(count) for count in held), file=said)
`

func TestAServingMypyBridgeHoldsNoMoreAfterItsFourthRequestThanItsFirst(t *testing.T) {
	python := os.Getenv("COMMANDMENTS_MYPY_PYTHON")
	if python == "" {
		t.Skip("no python with mypy is named here")
	}

	project := t.TempDir()
	if err := os.WriteFile(filepath.Join(project, "shop.py"), []byte("def price(count: int) -> int:\n    return count * 2\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	read := exec.Command(python, "-c", modulesHeldAfterEachRequest, project)
	read.Env = append(os.Environ(), "JOURNAL_PLUGIN_DATA="+t.TempDir())

	out, err := read.CombinedOutput()
	if err != nil {
		t.Fatalf("%v: %s", err, out)
	}

	held := strings.Fields(string(out))
	if len(held) != 4 {
		t.Fatalf("four requests left %d counts: %s", len(held), out)
	}

	if held[3] != held[0] {
		t.Errorf("the modules mypy still holds after each of four requests are %s, so every request leaves its whole build behind", strings.Join(held, ", "))
	}
}

func TestTwoProjectsKeepTheirOwnMypyCache(t *testing.T) {
	python := os.Getenv("COMMANDMENTS_MYPY_PYTHON")
	if python == "" {
		t.Skip("no python with mypy is named here")
	}

	one, other, data := t.TempDir(), t.TempDir(), t.TempDir()

	if mypyCacheFolderOf(t, python, data, one) == mypyCacheFolderOf(t, python, data, other) {
		t.Error("two projects write into one mypy cache")
	}
}

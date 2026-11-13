package bridge_test

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bridgetest"
)

var pythonShop = map[string]string{
	"price.py":  "class Price:\n    amount: int = 0\n",
	"basket.py": "from price import Price\n\n\nclass Basket:\n    def total(self) -> Price:\n        return Price()\n",
}

func pythonLines(t *testing.T) string {
	t.Helper()

	cache := t.TempDir()
	t.Setenv("COMMANDMENTS_MYPY_PYTHON", bridgetest.MypyPython(t))
	t.Setenv("XDG_CACHE_HOME", cache)
	t.Setenv("JOURNAL_PLUGIN_DATA", t.TempDir())

	return filepath.Join(cache, "code-commandments", "trees", "python")
}

func TestAServedPythonBridgeEndsOnceTheRunThatStartedItIsKilled(t *testing.T) {
	pythonLines(t)
	command, err := bridge.Mypy()
	if err != nil {
		t.Fatal(err)
	}

	bridgetest.EndsWithItsRun(t, command)
}

// countingBuilds runs a script after a prelude that counts mypy's builds: each is named by how it was asked and the files it compiled.
func countingBuilds(t *testing.T, script string, args ...string) string {
	t.Helper()

	python := os.Getenv("COMMANDMENTS_MYPY_PYTHON")
	if python == "" {
		t.Skip("no python with mypy is named here")
	}

	read := exec.Command(python, append([]string{"-c", countedBuilds + script}, args...)...)
	read.Env = append(os.Environ(), "JOURNAL_PLUGIN_DATA="+t.TempDir())

	out, err := read.CombinedOutput()
	if err != nil {
		t.Fatalf("%v: %s", err, out)
	}

	return strings.TrimSpace(string(out))
}

const countedBuilds = `
import glob, json, os, sys
sys.path.insert(0, 'mypy')
import tree, session
from kept import Kept

builds = []
counting = [False]
for name in ("checked", "compiled"):
    real = getattr(session.Session, name)
    def counted(self, paths, *args, _real=real, _name=name):
        if counting[0]:
            builds.append(_name + ":" + ",".join(sorted(os.path.basename(path) for path in paths)))
        return _real(self, paths, *args)
    setattr(session.Session, name, counted)

def files_of(root):
    return sorted(glob.glob(os.path.join(root, "**", "*.py"), recursive=True))

def run(root, kept):
    return [json.loads(line) if isinstance(line, str) else line for line in tree.stream(tree.Session(), files_of(root), [], Kept(kept))]

def said(lines):
    print(" ".join(builds) or "none", sum(1 for line in lines if "file" in line))
`

func TestTwoRunsBuildingTheMypyBridgeAtOnceBuildItOnce(t *testing.T) {
	python := os.Getenv("COMMANDMENTS_MYPY_PYTHON")
	if python == "" {
		t.Skip("no python with mypy is named here")
	}

	t.Setenv("XDG_CACHE_HOME", t.TempDir())
	t.Setenv("COMMANDMENTS_MYPY_PYTHON", "")
	t.Setenv("PATH", filepath.Dir(python)+string(os.PathListSeparator)+os.Getenv("PATH"))

	done := make(chan error, 2)
	for range 2 {
		go func() {
			_, err := bridge.Mypy()
			done <- err
		}()
	}

	for range 2 {
		if err := <-done; err != nil {
			t.Errorf("a run building the bridge beside another failed: %v", err)
		}
	}
}

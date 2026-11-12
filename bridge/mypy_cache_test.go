package bridge_test

import (
	"bytes"
	"compress/zlib"
	"encoding/json"
	"fmt"
	"io"
	"io/fs"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/bridge"
	"github.com/jessegall/code-commandments/bridge/bridgetest"
)

var pythonShop = map[string]string{
	"price.py":  "class Price:\n    amount: int = 0\n",
	"basket.py": "from price import Price\n\n\nclass Basket:\n    def total(self) -> Price:\n        return Price()\n",
}

func pythonRun(t *testing.T, root string) string {
	t.Helper()

	command, err := bridge.Mypy()
	if err != nil {
		t.Fatal(err)
	}

	stream, err := bridge.Once(command, root)
	if err != nil {
		t.Fatal(err)
	}

	written, err := json.Marshal(stream.Files)
	if err != nil {
		t.Fatal(err)
	}

	return string(written)
}

func tamperedLines(t *testing.T, folder, was, now string) int {
	t.Helper()

	tampered := 0
	filepath.WalkDir(folder, func(path string, entry fs.DirEntry, err error) error {
		if err != nil || entry.IsDir() || strings.HasPrefix(entry.Name(), ".") {
			return nil
		}

		stored, err := os.ReadFile(path)
		if err != nil {
			return nil
		}

		reader, err := zlib.NewReader(bytes.NewReader(stored))
		if err != nil {
			return nil
		}

		line, err := io.ReadAll(reader)
		if err != nil || !bytes.Contains(line, []byte(was)) {
			return nil
		}

		var packed bytes.Buffer
		writer := zlib.NewWriter(&packed)
		writer.Write(bytes.ReplaceAll(line, []byte(was), []byte(now)))
		writer.Close()

		if os.WriteFile(path, packed.Bytes(), 0o644) == nil {
			tampered++
		}

		return nil
	})

	return tampered
}

func pythonLines(t *testing.T) string {
	t.Helper()

	cache := t.TempDir()
	t.Setenv("COMMANDMENTS_MYPY_PYTHON", bridgetest.MypyPython(t))
	t.Setenv("XDG_CACHE_HOME", cache)
	t.Setenv("JOURNAL_PLUGIN_DATA", t.TempDir())

	return filepath.Join(cache, "code-commandments", "trees", "python")
}

func TestASecondRunOverAnUnchangedPythonProjectCompilesNothing(t *testing.T) {
	lines := pythonLines(t)
	root := checkoutHolding(t, pythonShop)

	pythonRun(t, root)

	if tamperedLines(t, lines, `"Basket"`, `"KeptBasket"`) == 0 {
		t.Fatal("the first run kept no line for basket.py")
	}

	if again := pythonRun(t, root); !strings.Contains(again, "KeptBasket") {
		t.Error("the second run compiled basket.py again instead of answering from the cache")
	}
}

func TestAChangeToAModuleTheOthersImportInvalidatesTheirKeptLines(t *testing.T) {
	lines := pythonLines(t)
	root := checkoutHolding(t, pythonShop)

	pythonRun(t, root)
	tamperedLines(t, lines, `"Basket"`, `"KeptBasket"`)

	if err := os.WriteFile(filepath.Join(root, "price.py"), []byte("class Price:\n    amount: int = 0\n    currency: str = \"EUR\"\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	if again := pythonRun(t, root); strings.Contains(again, "KeptBasket") || !strings.Contains(again, "Basket") {
		t.Error("basket.py imports price.py, which changed, yet its kept line was written")
	}
}

func TestAWorktreeHoldingTheSamePythonSharesTheKeptLines(t *testing.T) {
	lines := pythonLines(t)
	root := checkoutHolding(t, pythonShop)

	pythonRun(t, root)
	tamperedLines(t, lines, `"Basket"`, `"KeptBasket"`)

	worktree := checkoutHolding(t, pythonShop)
	again := pythonRun(t, worktree)

	if !strings.Contains(again, "KeptBasket") {
		t.Error("a worktree holding the same modules compiled them again instead of sharing the kept lines")
	}

	if strings.Contains(again, root) || !strings.Contains(again, worktree) {
		t.Error("the worktree's kept lines name the checkout they were first written in, not its own")
	}
}

func TestAServedPythonBridgeEndsOnceTheRunThatStartedItIsKilled(t *testing.T) {
	pythonLines(t)
	command, err := bridge.Mypy()
	if err != nil {
		t.Fatal(err)
	}

	bridgetest.EndsWithItsRun(t, command)
}

const oneBuildOnAColdCache = `
import os, sys
sys.path.insert(0, 'mypy')
import tree, session
from kept import Kept

builds = []
for name in ("checked", "from_cache", "compiled"):
    real = getattr(session.Session, name)
    def counted(self, *args, _real=real, _name=name):
        builds.append(_name)
        return _real(self, *args)
    setattr(session.Session, name, counted)

lines = list(tree.stream(tree.Session(), [sys.argv[1]], [], None, Kept(sys.argv[2])))
print(" ".join(builds), sum(1 for line in lines if "file" in line))
`

func TestAColdPythonCacheIsFilledByOneBuild(t *testing.T) {
	python := os.Getenv("COMMANDMENTS_MYPY_PYTHON")
	if python == "" {
		t.Skip("no python with mypy is named here")
	}

	root := checkoutHolding(t, pythonShop)
	read := exec.Command(python, "-c", oneBuildOnAColdCache, root, t.TempDir())
	read.Env = append(os.Environ(), "JOURNAL_PLUGIN_DATA="+t.TempDir())

	out, err := read.CombinedOutput()
	if err != nil {
		t.Fatalf("%v: %s", err, out)
	}

	if said := strings.TrimSpace(string(out)); said != "from_cache 2" {
		t.Errorf("a cold run made the builds and lines %q; one build writes both files", said)
	}
}

const noBuildWhenNothingChanged = `
import os, sys
sys.path.insert(0, 'mypy')
import tree, session
from kept import Kept

list(tree.stream(tree.Session(), [sys.argv[1]], [], None, Kept(sys.argv[2])))

builds = []
for name in ("checked", "from_cache", "compiled"):
    real = getattr(session.Session, name)
    def counted(self, *args, _real=real, _name=name):
        builds.append(_name)
        return _real(self, *args)
    setattr(session.Session, name, counted)

lines = list(tree.stream(tree.Session(), [sys.argv[1]], [], None, Kept(sys.argv[2])))
print(" ".join(builds) or "none", sum(1 for line in lines if "file" in line))
`

func TestAnUnchangedPythonProjectStartsNoMypyBuild(t *testing.T) {
	python := os.Getenv("COMMANDMENTS_MYPY_PYTHON")
	if python == "" {
		t.Skip("no python with mypy is named here")
	}

	root := checkoutHolding(t, pythonShop)
	read := exec.Command(python, "-c", noBuildWhenNothingChanged, root, t.TempDir())
	read.Env = append(os.Environ(), "JOURNAL_PLUGIN_DATA="+t.TempDir())

	out, err := read.CombinedOutput()
	if err != nil {
		t.Fatalf("%v: %s", err, out)
	}

	if said := strings.TrimSpace(string(out)); said != "none 2" {
		t.Errorf("a second run over an unchanged project made the builds and lines %q; it writes both from disk with no build", said)
	}
}

const noBuildInAnotherWorktree = `
import os, sys
sys.path.insert(0, 'mypy')
import tree, session
from kept import Kept

list(tree.stream(tree.Session(), [sys.argv[1]], [], None, Kept(sys.argv[3])))

builds = []
for name in ("checked", "from_cache", "compiled"):
    real = getattr(session.Session, name)
    def counted(self, *args, _real=real, _name=name):
        builds.append(_name)
        return _real(self, *args)
    setattr(session.Session, name, counted)

lines = list(tree.stream(tree.Session(), [sys.argv[2]], [], None, Kept(sys.argv[3])))
paths = sorted(line["file"]["path"] for line in lines if "file" in line)
print(" ".join(builds) or "none", all(path.startswith(os.path.realpath(sys.argv[2])) for path in paths), len(paths))
`

func TestAnotherWorktreeHoldingTheSamePythonStartsNoMypyBuild(t *testing.T) {
	python := os.Getenv("COMMANDMENTS_MYPY_PYTHON")
	if python == "" {
		t.Skip("no python with mypy is named here")
	}

	read := exec.Command(python, "-c", noBuildInAnotherWorktree, checkoutHolding(t, pythonShop), checkoutHolding(t, pythonShop), t.TempDir())
	read.Env = append(os.Environ(), "JOURNAL_PLUGIN_DATA="+t.TempDir())

	out, err := read.CombinedOutput()
	if err != nil {
		t.Fatalf("%v: %s", err, out)
	}

	if said := strings.TrimSpace(string(out)); said != "none True 2" {
		t.Errorf("a second checkout of the same files made the builds, own paths and lines %q; it is answered from disk under its own paths with no build", said)
	}
}

const keptByAStoppedRun = `
import os, sys
sys.path.insert(0, 'mypy')
import tree, session
from kept import Kept

# The run is stopped while it hands its lines back: everything was kept, its last lines were read by no one.
stream = tree.stream(tree.Session(), [sys.argv[1]], [], None, Kept(sys.argv[2]))
next(stream)
next(stream)
stream.close()

builds = []
for name in ("checked", "from_cache", "compiled"):
    real = getattr(session.Session, name)
    def counted(self, *args, _real=real, _name=name):
        builds.append(_name)
        return _real(self, *args)
    setattr(session.Session, name, counted)

lines = list(tree.stream(tree.Session(), [sys.argv[1]], [], None, Kept(sys.argv[2])))
print(" ".join(builds) or "none", sum(1 for line in lines if "file" in line))
`

func TestARunStoppedPartWayLeavesWhatTheNextRunNeedsToSkipMypy(t *testing.T) {
	python := os.Getenv("COMMANDMENTS_MYPY_PYTHON")
	if python == "" {
		t.Skip("no python with mypy is named here")
	}

	read := exec.Command(python, "-c", keptByAStoppedRun, checkoutHolding(t, pythonShop), t.TempDir())
	read.Env = append(os.Environ(), "JOURNAL_PLUGIN_DATA="+t.TempDir())

	out, err := read.CombinedOutput()
	if err != nil {
		t.Fatalf("%v: %s", err, out)
	}

	if said := strings.TrimSpace(string(out)); said != "none 2" {
		t.Errorf("after a run stopped while handing its lines back, the next run made the builds and lines %q; it should start no build", said)
	}
}

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

func TestALongChainOfPythonModulesIsKeyedInOnePass(t *testing.T) {
	python := os.Getenv("COMMANDMENTS_MYPY_PYTHON")
	if python == "" {
		t.Skip("no python with mypy is named here")
	}

	files := map[string]string{"m0.py": "class C0:\n    pass\n"}
	for at := 1; at < 600; at++ {
		files[fmt.Sprintf("m%d.py", at)] = fmt.Sprintf("from m%d import C%d\n\n\nclass C%d(C%d):\n    pass\n", at-1, at-1, at, at-1)
	}

	read := exec.Command(python, "-c", noBuildWhenNothingChanged, checkoutHolding(t, files), t.TempDir())
	read.Env = append(os.Environ(), "JOURNAL_PLUGIN_DATA="+t.TempDir())

	started := time.Now()
	out, err := read.CombinedOutput()
	if err != nil {
		t.Fatalf("%v: %s", err, out)
	}

	if said := strings.TrimSpace(string(out)); said != "none 600" {
		t.Errorf("a second run over an unchanged chain of 600 modules made the builds and lines %q", said)
	}
	if took := time.Since(started); took > 90*time.Second {
		t.Errorf("two runs over a chain of 600 modules took %v: what each module reaches is walked again per module", took)
	}
}

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

	stream, err := bridge.Once(command, bridge.Listed(root)...)
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
    return [json.loads(line) if isinstance(line, str) else line for line in tree.stream(tree.Session(), files_of(root), [], None, Kept(kept))]

def said(lines):
    print(" ".join(builds) or "none", sum(1 for line in lines if "file" in line))
`

func TestAColdPythonCacheIsFilledByOneBuild(t *testing.T) {
	said := countingBuilds(t, `
counting[0] = True
said(run(sys.argv[1], sys.argv[2]))
`, checkoutHolding(t, pythonShop), t.TempDir())

	if said != "compiled:basket.py,price.py 2" {
		t.Errorf("a cold run made the builds and lines %q; one build writes both files", said)
	}
}

func TestAnUnchangedPythonProjectStartsNoMypyBuild(t *testing.T) {
	said := countingBuilds(t, `
run(sys.argv[1], sys.argv[2])
counting[0] = True
said(run(sys.argv[1], sys.argv[2]))
`, checkoutHolding(t, pythonShop), t.TempDir())

	if said != "none 2" {
		t.Errorf("a second run over an unchanged project made the builds and lines %q; it writes both from disk with no build", said)
	}
}

const edited = `
def edit(root, name, text):
    with open(os.path.join(root, name), "w") as file:
        file.write(text)
`

func TestAnEditInsideAFunctionChecksOnlyTheFileEdited(t *testing.T) {
	said := countingBuilds(t, edited+`
run(sys.argv[1], sys.argv[2])
edit(sys.argv[1], "price.py", "class Price:\n    amount: int = 0\n\n    def doubled(self) -> int:\n        return self.amount * 2\n")
run(sys.argv[1], sys.argv[2])
edit(sys.argv[1], "price.py", "class Price:\n    amount: int = 0\n\n    def doubled(self) -> int:\n        return self.amount + self.amount\n")
counting[0] = True
said(run(sys.argv[1], sys.argv[2]))
`, checkoutHolding(t, pythonShop), t.TempDir())

	if said != "compiled:price.py 2" {
		t.Errorf("an edit inside a method of price.py, which basket.py imports, made the builds and lines %q; only price.py is checked, its interface is the same", said)
	}
}

func TestAChangedInterfaceChecksTheFilesThatImportIt(t *testing.T) {
	said := countingBuilds(t, edited+`
run(sys.argv[1], sys.argv[2])
edit(sys.argv[1], "price.py", "class Price:\n    amount: int = 0\n    currency: str = 'EUR'\n")
counting[0] = True
said(run(sys.argv[1], sys.argv[2]))
`, checkoutHolding(t, pythonShop), t.TempDir())

	if said != "compiled:price.py compiled:basket.py 2" {
		t.Errorf("a new field on Price made the builds and lines %q; price.py is checked, then basket.py, which saw its old interface", said)
	}
}

func TestAnotherWorktreeHoldingTheSamePythonStartsNoMypyBuild(t *testing.T) {
	said := countingBuilds(t, `
run(sys.argv[1], sys.argv[3])
counting[0] = True
lines = run(sys.argv[2], sys.argv[3])
paths = sorted(line["file"]["path"] for line in lines if "file" in line)
print(" ".join(builds) or "none", all(path.startswith(os.path.realpath(sys.argv[2])) for path in paths), len(paths))
`, checkoutHolding(t, pythonShop), checkoutHolding(t, pythonShop), t.TempDir())

	if said != "none True 2" {
		t.Errorf("a second checkout of the same files made the builds, own paths and lines %q; it is answered from disk under its own paths with no build", said)
	}
}

func TestARunStoppedPartWayLeavesWhatTheNextRunNeedsToSkipMypy(t *testing.T) {
	said := countingBuilds(t, `
# The run is stopped while it hands its lines back: everything was kept, its last lines were read by no one.
stream = tree.stream(tree.Session(), files_of(sys.argv[1]), [], None, Kept(sys.argv[2]))
next(stream)
next(stream)
stream.close()
counting[0] = True
said(run(sys.argv[1], sys.argv[2]))
`, checkoutHolding(t, pythonShop), t.TempDir())

	if said != "none 2" {
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

func TestALongChainOfPythonModulesIsReadFromDiskAndAnEditAtItsRootChecksOneFile(t *testing.T) {
	files := map[string]string{"m0.py": "class C0:\n    pass\n"}
	for at := 1; at < 600; at++ {
		files[fmt.Sprintf("m%d.py", at)] = fmt.Sprintf("from m%d import C%d\n\n\nclass C%d(C%d):\n    pass\n", at-1, at-1, at, at-1)
	}

	started := time.Now()
	said := countingBuilds(t, edited+`
import time
run(sys.argv[1], sys.argv[2])
counting[0] = True
began = time.time()
lines = run(sys.argv[1], sys.argv[2])
unchanged = time.time() - began
edit(sys.argv[1], "m0.py", "class C0:\n    def name(self) -> str:\n        return 'zero'\n")
run(sys.argv[1], sys.argv[2])
builds.clear()
edit(sys.argv[1], "m0.py", "class C0:\n    def name(self) -> str:\n        return 'nil'\n")
began = time.time()
lines = run(sys.argv[1], sys.argv[2])
print(" ".join(builds) or "none", sum(1 for line in lines if "file" in line), unchanged < 10, time.time() - began < 30)
`, checkoutHolding(t, files), t.TempDir())

	if said != "compiled:m0.py 600 True True" {
		t.Errorf("a chain of 600 modules, read again unchanged and then with a body edit at its root, made the builds, lines and timings %q; the edit checks m0.py alone", said)
	}
	if took := time.Since(started); took > 50*time.Second {
		t.Errorf("the runs over a chain of 600 modules took %v", took)
	}
}

func TestAnUnchangedProjectOfThreeThousandModulesIsServedInSeconds(t *testing.T) {
	files := map[string]string{}
	for at := 0; at < 3000; at++ {
		files[fmt.Sprintf("m%d.py", at)] = fmt.Sprintf("class C%d:\n    def total(self, count: int) -> int:\n        return count * %d\n", at, at)
	}

	said := countingBuilds(t, `
import time
run(sys.argv[1], sys.argv[2])
counting[0] = True
began = time.time()
lines = [line for line in tree.stream(tree.Session(), files_of(sys.argv[1]), [], None, Kept(sys.argv[2]))]
took = time.time() - began
print(" ".join(builds) or "none", sum(1 for line in lines if "file" in line), took < 3, round(took, 1))
`, checkoutHolding(t, files), t.TempDir())

	if !strings.HasPrefix(said, "none 3000 True") {
		t.Errorf("a second run over 3,000 unchanged modules made the builds, lines, whether it took under 3 s, and its seconds %q", said)
	}
}

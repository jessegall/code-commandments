package bridge_test

import (
	"bytes"
	"compress/zlib"
	"encoding/json"
	"io"
	"io/fs"
	"os"
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

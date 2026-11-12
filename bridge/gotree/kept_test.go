package gotree_test

import (
	"encoding/json"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/bridge/gotree"
)

var shop = map[string]string{
	"go.mod":   "module example.com/shop\n\ngo 1.22\n",
	"cart.go":  "package shop\n\ntype Cart struct{ lines int }\n\nfunc (c Cart) Lines() int { return c.lines }\n",
	"price.go": "package shop\n\nfunc Price() int {\n\treturn 1\n}\n",
}

func checkoutOf(t *testing.T, files map[string]string) string {
	t.Helper()

	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for name, source := range files {
		if err := os.WriteFile(filepath.Join(root, name), []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return root
}

func streamed(t *testing.T, root string) string {
	t.Helper()

	stream, err := gotree.Stream([]string{root}, nil)
	if err != nil {
		t.Fatal(err)
	}

	written, err := json.Marshal(stream.Files)
	if err != nil {
		t.Fatal(err)
	}

	return string(written)
}

func tampered(t *testing.T, cache, was, now string) int {
	t.Helper()

	entries, _ := filepath.Glob(filepath.Join(cache, "code-commandments", "trees", "go", "*.line"))
	count := 0
	for _, entry := range entries {
		kept, err := os.ReadFile(entry)
		if err != nil || !strings.Contains(string(kept), was) {
			continue
		}
		if os.WriteFile(entry, []byte(strings.ReplaceAll(string(kept), was, now)), 0o644) == nil {
			count++
		}
	}

	return count
}

func TestASecondGoRunAnswersAnUnchangedFileFromTheCache(t *testing.T) {
	cache := t.TempDir()
	t.Setenv("XDG_CACHE_HOME", cache)
	root := checkoutOf(t, shop)

	streamed(t, root)
	if tampered(t, cache, `"Cart"`, `"KeptCart"`) == 0 {
		t.Fatal("the first run kept no line for cart.go")
	}

	if again := streamed(t, root); !strings.Contains(again, "KeptCart") {
		t.Error("the second run parsed cart.go again instead of answering from the cache")
	}
}

func TestAChangeInsideAGoBodyKeepsEveryOtherFilesLine(t *testing.T) {
	cache := t.TempDir()
	t.Setenv("XDG_CACHE_HOME", cache)
	root := checkoutOf(t, shop)

	streamed(t, root)
	tampered(t, cache, `"Cart"`, `"KeptCart"`)

	if err := os.WriteFile(filepath.Join(root, "price.go"), []byte("package shop\n\nfunc Price() int {\n\treturn 42\n}\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	if again := streamed(t, root); !strings.Contains(again, "KeptCart") {
		t.Error("a change inside a body of price.go made cart.go be parsed again")
	}
}

func TestAnotherWorktreeOfTheSameGoSharesTheKeptLinesUnderItsOwnPaths(t *testing.T) {
	cache := t.TempDir()
	t.Setenv("XDG_CACHE_HOME", cache)

	first := checkoutOf(t, shop)
	streamed(t, first)
	tampered(t, cache, `"Cart"`, `"KeptCart"`)

	second := checkoutOf(t, shop)
	again := streamed(t, second)

	if !strings.Contains(again, "KeptCart") {
		t.Error("a worktree holding the same Go files parsed them again instead of sharing the kept lines")
	}
	if strings.Contains(again, first) || !strings.Contains(again, second) {
		t.Error("the worktree's kept lines name the checkout they were first written in, not its own")
	}
}

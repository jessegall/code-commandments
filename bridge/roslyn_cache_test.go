package bridge_test

import (
	"encoding/json"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/bridge"
)

func checkoutHolding(t *testing.T, files map[string]string) string {
	t.Helper()

	root := t.TempDir()
	if err := os.Mkdir(filepath.Join(root, ".git"), 0o755); err != nil {
		t.Fatal(err)
	}

	for name, source := range files {
		if err := os.WriteFile(filepath.Join(root, name), []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return root
}

func cachedRun(t *testing.T, trees, root string) string {
	t.Helper()

	if exec.Command("docker", "info").Run() != nil {
		t.Skip("no Docker to run the C# bridge in")
	}

	command, err := bridge.RoslynInDocker([]string{root}, trees)
	if err != nil {
		t.Fatal(err)
	}

	stream, err := bridge.Once(append(command, "--cache="+trees), root)
	if err != nil {
		t.Fatal(err)
	}

	written, err := json.Marshal(stream.Files)
	if err != nil {
		t.Fatal(err)
	}

	return string(written)
}

func tamperedKept(t *testing.T, trees, was, now string) int {
	t.Helper()

	entries, err := filepath.Glob(filepath.Join(trees, "*.tree"))
	if err != nil {
		t.Fatal(err)
	}

	tampered := 0
	for _, entry := range entries {
		kept, err := os.ReadFile(entry)
		if err != nil {
			t.Fatal(err)
		}

		if !strings.Contains(string(kept), was) {
			continue
		}

		if err := os.WriteFile(entry, []byte(strings.ReplaceAll(string(kept), was, now)), 0o644); err != nil {
			t.Fatal(err)
		}
		tampered++
	}

	return tampered
}

var shop = map[string]string{
	"Price.cs":  "namespace Shop;\n\npublic sealed record Price(decimal Amount);\n",
	"Basket.cs": "namespace Shop;\n\npublic sealed class Basket\n{\n    public Price Total() => new(0);\n}\n",
}

func TestASecondRunOverAnUnchangedCSharpProjectAnswersFromTheCache(t *testing.T) {
	trees := t.TempDir()
	root := checkoutHolding(t, shop)

	cachedRun(t, trees, root)

	if tamperedKept(t, trees, `"Basket"`, `"KeptBasket"`) == 0 {
		t.Fatal("the first run kept no line for Basket.cs")
	}

	if again := cachedRun(t, trees, root); !strings.Contains(again, "KeptBasket") {
		t.Errorf("the second run compiled Basket.cs again instead of answering from the cache")
	}
}

func TestAChangeToAFileTheOthersReachInvalidatesTheirKeptLines(t *testing.T) {
	trees := t.TempDir()
	root := checkoutHolding(t, shop)

	cachedRun(t, trees, root)
	tamperedKept(t, trees, `"Basket"`, `"KeptBasket"`)

	if err := os.WriteFile(filepath.Join(root, "Price.cs"), []byte("namespace Shop;\n\npublic sealed record Price(decimal Amount, string Currency);\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	if again := cachedRun(t, trees, root); strings.Contains(again, "KeptBasket") || !strings.Contains(again, "Basket") {
		t.Errorf("Basket.cs reaches Price.cs, which changed, yet its kept line was written")
	}
}

func TestAWorktreeHoldingTheSameSourcesSharesTheKeptLines(t *testing.T) {
	trees := t.TempDir()
	root := checkoutHolding(t, shop)

	cachedRun(t, trees, root)
	tamperedKept(t, trees, `"Basket"`, `"KeptBasket"`)

	worktree := checkoutHolding(t, shop)
	again := cachedRun(t, trees, worktree)

	if !strings.Contains(again, "KeptBasket") {
		t.Errorf("a worktree holding the same sources compiled them again instead of sharing the kept lines")
	}

	if strings.Contains(again, root) || !strings.Contains(again, worktree) {
		t.Errorf("the worktree's kept lines name the checkout they were first written in, not its own")
	}
}

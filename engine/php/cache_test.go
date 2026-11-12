package php

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/bridge"
)

func streamed(t *testing.T, program Bridge, arguments ...string) string {
	t.Helper()
	command, err := program.Command()
	if err != nil {
		t.Fatal(err)
	}
	out, err := exec.Command(command[0], append(command[1:], bridge.ListedArguments(arguments...)...)...).Output()
	if err != nil {
		t.Fatalf("%v: %v", command, err)
	}

	return string(out)
}

func TestAKeptTreeIsWrittenAsThePHPBridgeWouldWriteItAfresh(t *testing.T) {
	needsPHP(t)
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	put := func(path, source string) {
		if err := os.WriteFile(filepath.Join(root, path), []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	put("Money.php", "<?php\n\nnamespace Shop;\n\nfinal class Money\n{\n    public function __construct(public readonly int $cents) {}\n}\n")
	put("Order.php", "<?php\n\nnamespace Shop;\n\nfinal class Order implements \\Countable\n{\n    public function total(): Money\n    {\n        return new Money(12);\n    }\n\n    public function count(): int\n    {\n        return 1;\n    }\n}\n")
	fresh := Here()
	kept := Here()
	kept.Trees = t.TempDir()
	same := func(why string, arguments ...string) {
		t.Helper()
		want := streamed(t, fresh, arguments...)
		for _, run := range []string{"first", "again"} {
			if got := streamed(t, kept, arguments...); got != want {
				t.Errorf("%s, the %s cached run writes another stream than an uncached one", why, run)
			}
		}
	}

	same("as written", root)
	if trees, _ := filepath.Glob(filepath.Join(kept.Trees, "*.tree")); len(trees) != 2 {
		t.Fatalf("the cache keeps %d trees for 2 files", len(trees))
	}
	put("Order.php", "<?php\n\nnamespace Shop;\n\nfinal class Order implements \\Stringable\n{\n    public function __toString(): string\n    {\n        return 'order';\n    }\n}\n")
	same("after a change to Order.php", root)
	same("with one file judged and the other context", "--write="+filepath.Join(root, "Money.php"), root)
}

func TestAKeptTreeTheBridgeUsesAgainIsRenewedAndOneItDoesNotUseIsNot(t *testing.T) {
	needsPHP(t)
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	for name, source := range map[string]string{"Money.php": "<?php\n\nnamespace Shop;\n\nfinal class Money {}\n", "Order.php": "<?php\n\nnamespace Shop;\n\nfinal class Order {}\n"} {
		if err := os.WriteFile(filepath.Join(root, name), []byte(source), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	kept := Here()
	kept.Trees = t.TempDir()
	streamed(t, kept, root)

	old := time.Now().Add(-13 * time.Hour)
	trees, _ := filepath.Glob(filepath.Join(kept.Trees, "*.tree"))
	for _, tree := range trees {
		if err := os.Chtimes(tree, old, old); err != nil {
			t.Fatal(err)
		}
	}

	streamed(t, kept, filepath.Join(root, "Money.php"))

	renewed := 0
	for _, tree := range trees {
		if info, err := os.Stat(tree); err == nil && time.Since(info.ModTime()) < time.Hour {
			renewed++
		}
	}
	if renewed != 1 {
		t.Errorf("%d of the 2 kept trees were renewed by a run that read one file, want 1: a use renews an entry and nothing else does", renewed)
	}
}

func TestTwoCheckoutsHoldingTheSameFileShareOneKeptTree(t *testing.T) {
	needsPHP(t)
	parent, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	checkouts := []string{filepath.Join(parent, "main"), filepath.Join(parent, "worktree")}
	for _, checkout := range checkouts {
		if err := os.MkdirAll(checkout, 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(filepath.Join(checkout, "Money.php"), []byte("<?php\n\nnamespace Shop;\n\nfinal class Money {}\n"), 0o644); err != nil {
			t.Fatal(err)
		}
	}
	fresh := Here()
	kept := Here()
	kept.Trees = t.TempDir()

	streamed(t, kept, checkouts[0])
	got := streamed(t, kept, "--write="+filepath.Join(checkouts[1], "Money.php"), checkouts[1])

	if trees, _ := filepath.Glob(filepath.Join(kept.Trees, "*.tree")); len(trees) != 1 {
		t.Errorf("two checkouts holding one file keep %d trees, where they share one", len(trees))
	}
	if want := streamed(t, fresh, "--write="+filepath.Join(checkouts[1], "Money.php"), checkouts[1]); got != want {
		t.Errorf("the second checkout read the shared tree as another stream than its own:\n%s\nwant:\n%s", got, want)
	}
}

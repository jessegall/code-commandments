package php

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"
)

// streamed is what the bridge writes, byte for byte, for its arguments.
func streamed(t *testing.T, bridge Bridge, arguments ...string) string {
	t.Helper()
	command, err := bridge.Command()
	if err != nil {
		t.Fatal(err)
	}
	out, err := exec.Command(command[0], append(command[1:], arguments...)...).Output()
	if err != nil {
		t.Fatalf("%v: %v", command, err)
	}

	return string(out)
}

// TestAKeptTreeIsWrittenAsThePHPBridgeWouldWriteItAfresh holds the PHP bridge's tree cache to the stream an uncached
// run writes: cold and warm, after a change to one file, and with files judged and read only for context.
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

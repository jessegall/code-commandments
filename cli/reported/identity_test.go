package reported_test

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/reported"
)

func TestASinKeepsItsIdentityUntilItsOwnLineChanges(t *testing.T) {
	root := t.TempDir()
	file := filepath.Join(root, "src", "refund.py")
	write(t, file, "def refund(order):\n    return order.total\n")

	was, read := reported.Identity("RedundantElseDetector", root, file, 2)
	if !read {
		t.Fatal("the flagged line was not read, though it holds text")
	}

	write(t, file, "# a comment nobody asked for\n\ndef refund(order):\n    return order.total\n")
	moved, _ := reported.Identity("RedundantElseDetector", root, file, 4)
	if moved != was {
		t.Error("a sin moved down by an edit above it reads as another sin, and a report about it would be orphaned")
	}

	write(t, file, "def refund(order):\n    return order.total or 0\n")
	written, _ := reported.Identity("RedundantElseDetector", root, file, 2)
	if written == was {
		t.Error("a line written anew reads as the same sin, so a report would still cover code nobody reported")
	}
}

func TestTheRuleAndThePathAreBothPartOfIt(t *testing.T) {
	root := t.TempDir()
	file := filepath.Join(root, "src", "refund.py")
	write(t, file, "    return order.total\n")
	write(t, filepath.Join(root, "src", "ship.py"), "    return order.total\n")

	one, _ := reported.Identity("RedundantElseDetector", root, file, 1)
	another, _ := reported.Identity("InventedDefaultDetector", root, file, 1)
	elsewhere, _ := reported.Identity("RedundantElseDetector", root, filepath.Join(root, "src", "ship.py"), 1)

	if one == another {
		t.Error("two rules about one line share an identity, so reporting one would silence the other")
	}

	if one == elsewhere {
		t.Error("the same line in two files shares an identity, so reporting one would silence both")
	}
}

func TestAPathIsReadAsTheProjectNamesIt(t *testing.T) {
	here, there := t.TempDir(), t.TempDir()
	write(t, filepath.Join(here, "src", "refund.py"), "    return order.total\n")
	write(t, filepath.Join(there, "src", "refund.py"), "    return order.total\n")

	one, _ := reported.Identity("RedundantElseDetector", here, filepath.Join(here, "src", "refund.py"), 1)
	other, _ := reported.Identity("RedundantElseDetector", there, filepath.Join(there, "src", "refund.py"), 1)

	if one != other {
		t.Error("two checkouts of one project disagree on a sin's identity, so a report would follow only one of them")
	}
}

func TestTwoIdenticalLinesInOneFileShareTheirIdentity(t *testing.T) {
	root := t.TempDir()
	file := filepath.Join(root, "src", "totals.py")
	write(t, file, "    return order.total\nx = 1\n    return order.total\n")

	first, _ := reported.Identity("RedundantElseDetector", root, file, 1)
	second, _ := reported.Identity("RedundantElseDetector", root, file, 3)

	if first != second {
		t.Error("two identical flagged lines read as different sins; the key is the line's text, so they cannot")
	}
}

func TestALineThatCannotBeReadIsRefusedAsAKey(t *testing.T) {
	root := t.TempDir()
	file := filepath.Join(root, "src", "refund.py")
	write(t, file, "def refund(order):\n\n    return order.total\n")

	for name, line := range map[string]int{"a blank line": 2, "a line past the end": 9, "a line before the first": 0} {
		t.Run(name, func(t *testing.T) {
			id, read := reported.Identity("RedundantElseDetector", root, file, line)
			if read {
				t.Error("the line reads as text, and there is none there to read")
			}
			if id == "" {
				t.Error("no identity at all, so a mark of this run has no key to be raised under")
			}
		})
	}

	gone, read := reported.Identity("RedundantElseDetector", root, filepath.Join(root, "src", "nothing.py"), 1)
	if read {
		t.Error("a file that is not there reads as text")
	}

	held, _ := reported.Identity("RedundantElseDetector", root, file, 1)
	if gone == held {
		t.Error("a file that is not there shares its identity with one that is")
	}
}

func write(t *testing.T, file, text string) {
	t.Helper()
	if err := os.MkdirAll(filepath.Dir(file), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(file, []byte(text), 0o644); err != nil {
		t.Fatal(err)
	}
}

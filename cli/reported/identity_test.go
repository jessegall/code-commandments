package reported_test

import (
	"os"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/reported"
)

// TestASinKeepsItsIdentityUntilItsOwnLineChanges holds what a report is keyed by: the sin stays the same sin while
// its own line reads the same, wherever that line has moved to, and becomes another sin once the line itself is
// written anew. A report outlives an edit above it and does not outlive an edit to it.
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

// TestTheRuleAndThePathAreBothPartOfIt holds the two things besides the line: the same line of the same file is a
// different finding under a different rule, and the same line in another file is another place.
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

// TestAPathIsReadAsTheProjectNamesIt holds the identity to the path under the root, so two checkouts of one
// project agree on what has been reported.
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

// TestTwoIdenticalLinesInOneFileShareTheirIdentity holds a limit of this key, written down rather than discovered:
// two places whose sin, file and line text all read the same cannot be told apart, so a report about one covers
// both. The alternative is keying on the line's number, which an edit above it breaks, and a report that forgets
// its sin on the next edit is worth less than one that covers a twin.
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

// TestALineThatCannotBeReadIsRefusedAsAKey holds the one case a report must not key on: a file that is gone, a
// line past its end, a blank line. The identity still answers, so a mark of this run can still be told apart, but
// it says it did not read the line, and the report writer refuses it rather than keying every such sin alike.
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

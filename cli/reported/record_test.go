package reported_test

import (
	"encoding/json"
	"errors"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/reported"
)

// TestAReportIsReadBackAtTheSinItWasAbout holds the whole point of the record: the sin a report was filed about
// reads as reported afterwards, wherever its line has moved to, and every other sin reads as active.
func TestAReportIsReadBackAtTheSinItWasAbout(t *testing.T) {
	root := t.TempDir()
	file := filepath.Join(root, "src", "refund.py")
	write(t, file, "def refund(order):\n    return order.total\n")

	if _, err := reported.Add(root, row(file, 2)); err != nil {
		t.Fatal(err)
	}

	held, read := reported.Of(root)
	if !read {
		t.Fatal("the record it just wrote does not read")
	}

	found, ok := held.Reported("InventedDefaultDetector", root, file, 2)
	if !ok {
		t.Fatal("the sin that was reported reads as active")
	}

	if found.Issue != 814 || found.Repository != "jessegall/code-commandments" {
		t.Errorf("the row holds %+v", found)
	}

	if found.File != "src/refund.py" {
		t.Errorf("the row names the file as %q, and a record two checkouts share names it under the root", found.File)
	}

	if _, ok := held.Reported("RedundantElseDetector", root, file, 2); ok {
		t.Error("another rule's finding on the same line reads as reported, and nobody has answered that one")
	}

	write(t, file, "# moved down\n\ndef refund(order):\n    return order.total\n")
	if _, ok := held.Reported("InventedDefaultDetector", root, file, 4); !ok {
		t.Error("a sin an edit above it moved reads as active again, and the issue is still open")
	}

	write(t, file, "def refund(order):\n    return order.total or 0\n")
	if _, ok := held.Reported("InventedDefaultDetector", root, file, 2); ok {
		t.Error("a line written anew still reads as reported, so the report covers code nobody reported")
	}
}

// TestTheRecordKeepsNoCodeOfTheProject holds what may be committed: the record is tracked and the issues it names
// are public, so the flagged line is kept as a hash and nowhere as text.
func TestTheRecordKeepsNoCodeOfTheProject(t *testing.T) {
	root := t.TempDir()
	file := filepath.Join(root, "src", "secrets.py")
	write(t, file, "    token = \"a-very-secret-token\"\n")

	if _, err := reported.Add(root, row(file, 1)); err != nil {
		t.Fatal(err)
	}

	content, err := os.ReadFile(reported.Path(root))
	if err != nil {
		t.Fatal(err)
	}

	if strings.Contains(string(content), "a-very-secret-token") {
		t.Error("the record holds the text of the flagged line, which is committed and public once the issue is")
	}

	if !strings.Contains(string(content), "deleting this file") {
		t.Error("the record does not say what deleting it costs")
	}
}

// TestASecondReportOfOneSinIsRefused holds what keeps an open issue from being forgotten: the row is not replaced,
// and the refusal names the issue that already has it, so the person is sent to the conversation already running.
func TestASecondReportOfOneSinIsRefused(t *testing.T) {
	root := t.TempDir()
	file := filepath.Join(root, "src", "refund.py")
	write(t, file, "    return order.total\n")

	if _, err := reported.Add(root, row(file, 1)); err != nil {
		t.Fatal(err)
	}

	again := row(file, 1)
	again.Issue = 900
	held, err := reported.Add(root, again)

	var already *reported.Already
	if !errors.As(err, &already) {
		t.Fatalf("a second report answered %v", err)
	}

	if held.Issue != 814 || already.Row.Issue != 814 {
		t.Errorf("the refusal names issue %d and %d, want the one already filed", held.Issue, already.Row.Issue)
	}

	record, _ := reported.Of(root)
	if len(record.Rows()) != 1 {
		t.Errorf("the record holds %d rows after a refused second report", len(record.Rows()))
	}
}

// TestAPlaceWhoseLineCannotBeReadIsRefused holds the one place a row may not be keyed on, since every such sin of
// one rule in one file would key alike and the report would cover code nobody reported.
func TestAPlaceWhoseLineCannotBeReadIsRefused(t *testing.T) {
	root := t.TempDir()
	file := filepath.Join(root, "src", "refund.py")
	write(t, file, "def refund(order):\n\n    return order.total\n")

	var unreadable *reported.Unreadable
	if _, err := reported.Add(root, row(file, 2)); !errors.As(err, &unreadable) {
		t.Errorf("a blank flagged line answered %v", err)
	}

	if _, err := reported.Add(root, row(filepath.Join(root, "src", "gone.py"), 1)); !errors.As(err, &unreadable) {
		t.Errorf("a file that is not there answered %v", err)
	}

	if _, err := os.Stat(reported.Path(root)); err == nil {
		t.Error("a refused report wrote a record anyway")
	}
}

// TestARecordNobodyCanReadIsAnEmptyOneThatSaysSo holds the rule every reader of it follows: a record that is gone
// or no longer reads as one leaves every sin active, and never stops a run. The second answer is what tells a
// judge to warn and a hook to stay silent.
func TestARecordNobodyCanReadIsAnEmptyOneThatSaysSo(t *testing.T) {
	root := t.TempDir()

	held, read := reported.Of(root)
	if !read || len(held.Rows()) != 0 {
		t.Errorf("a project with no record reads as %+v, read %v", held.Rows(), read)
	}

	if err := os.MkdirAll(filepath.Dir(reported.Path(root)), 0o755); err != nil {
		t.Fatal(err)
	}

	if err := os.WriteFile(reported.Path(root), []byte("{not json at all"), 0o644); err != nil {
		t.Fatal(err)
	}

	held, read = reported.Of(root)
	if read {
		t.Error("a record nobody can read says it read")
	}

	if len(held.Rows()) != 0 {
		t.Error("a record nobody can read answered rows")
	}
}

// TestForgettingAReportMakesItsSinOrdinaryAgain holds the way out: once an answer has settled a report, its row
// goes, the sin is judged as any other, and a number no row holds is refused rather than silently ignored.
func TestForgettingAReportMakesItsSinOrdinaryAgain(t *testing.T) {
	root := t.TempDir()
	file := filepath.Join(root, "src", "refund.py")
	write(t, file, "    return order.total\n")

	if _, err := reported.Add(root, row(file, 1)); err != nil {
		t.Fatal(err)
	}

	gone, ok, err := reported.Forget(root, 814)
	if err != nil || !ok || gone.Issue != 814 {
		t.Fatalf("forgetting issue 814 answered %+v, %v, %v", gone, ok, err)
	}

	held, _ := reported.Of(root)
	if _, reported := held.Reported("InventedDefaultDetector", root, file, 1); reported {
		t.Error("the sin still reads as reported after its report was forgotten")
	}

	if _, ok, _ := reported.Forget(root, 999); ok {
		t.Error("forgetting a number no row holds said it did")
	}
}

// TestWhatWasLastSeenIsRememberedOnTheRow holds what keeps an answer from being told at every look: the state and
// the comment count the last look found are kept on the row.
func TestWhatWasLastSeenIsRememberedOnTheRow(t *testing.T) {
	root := t.TempDir()
	file := filepath.Join(root, "src", "refund.py")
	write(t, file, "    return order.total\n")

	if _, err := reported.Add(root, row(file, 1)); err != nil {
		t.Fatal(err)
	}

	if err := reported.Saw(root, 814, reported.Seen{State: "OPEN", Comments: 2, At: "2026-10-25T14:00:00Z"}); err != nil {
		t.Fatal(err)
	}

	held, _ := reported.Of(root)
	if seen := held.Rows()[0].Seen; seen.State != "OPEN" || seen.Comments != 2 {
		t.Errorf("the row remembers %+v of the last look", seen)
	}
}

// TestTheRecordReadsAsJSONAReaderCanFollow holds the file to a shape a person or another tool can read, since it
// is committed to the project and a reader will meet it in a diff.
func TestTheRecordReadsAsJSONAReaderCanFollow(t *testing.T) {
	root := t.TempDir()
	file := filepath.Join(root, "src", "refund.py")
	write(t, file, "    return order.total\n")

	if _, err := reported.Add(root, row(file, 1)); err != nil {
		t.Fatal(err)
	}

	content, err := os.ReadFile(reported.Path(root))
	if err != nil {
		t.Fatal(err)
	}

	var read map[string]any
	if err := json.Unmarshal(content, &read); err != nil {
		t.Fatalf("the record does not read as JSON: %v", err)
	}

	if !strings.HasSuffix(string(content), "\n") {
		t.Error("the record does not end on a newline, which every file this tool writes does")
	}
}

func row(file string, line int) reported.Row {
	return reported.Row{
		Detector:   "InventedDefaultDetector",
		Sin:        "csharp-invented-default",
		File:       file,
		Line:       line,
		Reason:     "zero is the true count of an empty set\nand the rest of the argument",
		Repository: "jessegall/code-commandments",
		Issue:      814,
	}
}

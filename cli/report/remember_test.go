package report

import (
	"errors"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/reported"

	// The record keys a report by the SIN the reported detector finds, which only the registry can answer, and
	// the real command runs with it linked.
	_ "github.com/jessegall/code-commandments/registry"
)

// TestADetectorReportRecordsTheSinItWasAbout holds the whole of the first phase at the one place it is used: the
// issue is filed, and the line it was about is recorded as reported with that issue, keyed so a judge can read it
// back.
func TestADetectorReportRecordsTheSinItWasAbout(t *testing.T) {
	root := project(t, "src/refund.py", "def refund(order):\n    return order.total or 0\n")
	ghAnswers(t, "https://github.com/jessegall/code-commandments/issues/814\n", nil)

	said := &strings.Builder{}
	if code := detectorReport(input(t, "src/refund.py:2"), "InventedDefaultDetector", "zero is the true count", refs("src/refund.py:2"), "v6.10.2", cli.Console{Out: said, Err: said}); code != 0 {
		t.Fatalf("the report answered %d: %s", code, said)
	}

	held, _ := reported.Of(root)
	row, found := held.Reported("InventedDefaultDetector", root, filepath.Join(root, "src/refund.py"), 2)
	if !found {
		t.Fatal("the line the report was about is not recorded as reported")
	}

	if row.Issue != 814 || row.Repository != "jessegall/code-commandments" {
		t.Errorf("the row names %+v", row)
	}

	if row.Reason != "zero is the true count" {
		t.Errorf("the row's reason reads %q", row.Reason)
	}

	if !strings.Contains(said.String(), reported.Filename) {
		t.Errorf("the run never said the sin was recorded: %q", said.String())
	}
}

// TestNothingIsRecordedWhereNoIssueWasFiled holds the pairing the record depends on: a row exists only where an
// issue does, or a judge would call a sin reported with no report to show for it.
func TestNothingIsRecordedWhereNoIssueWasFiled(t *testing.T) {
	for name, test := range map[string]struct {
		stdout string
		err    error
	}{
		"gh refused the issue":    {err: errors.New("exit status 1")},
		"gh printed no issue URL": {stdout: "nothing was created\n"},
	} {
		t.Run(name, func(t *testing.T) {
			root := project(t, "src/refund.py", "    return order.total or 0\n")
			ghAnswers(t, test.stdout, test.err)

			said := &strings.Builder{}
			detectorReport(input(t, "src/refund.py:1"), "InventedDefaultDetector", "zero is the true count", refs("src/refund.py:1"), "v6.10.2", cli.Console{Out: said, Err: said})

			if _, err := os.Stat(reported.Path(root)); err == nil {
				t.Error("a report that filed no issue wrote a row")
			}
		})
	}
}

// TestASinAlreadyReportedIsSaidOutLoudAndNotForgotten holds what happens once the issue is already filed and the
// row cannot be written: the run says the issue, says the line is not recorded, and fails, since silence would
// invite a second issue for a maintainer to read.
func TestASinAlreadyReportedIsSaidOutLoudAndNotForgotten(t *testing.T) {
	root := project(t, "src/refund.py", "    return order.total or 0\n")
	ghAnswers(t, "https://github.com/jessegall/code-commandments/issues/814\n", nil)

	first := &strings.Builder{}
	detectorReport(input(t, "src/refund.py:1"), "InventedDefaultDetector", "zero is the true count", refs("src/refund.py:1"), "v6.10.2", cli.Console{Out: first, Err: first})

	ghAnswers(t, "https://github.com/jessegall/code-commandments/issues/900\n", nil)
	said := &strings.Builder{}
	code := detectorReport(input(t, "src/refund.py:1"), "InventedDefaultDetector", "the same again", refs("src/refund.py:1"), "v6.10.2", cli.Console{Out: said, Err: said})

	if code == 0 {
		t.Error("a report whose row was refused answered success")
	}

	if !strings.Contains(said.String(), "issues/900") || !strings.Contains(said.String(), "814") {
		t.Errorf("the run named neither the issue it filed nor the one that has the sin: %q", said.String())
	}

	held, _ := reported.Of(root)
	if row, _ := held.Reported("InventedDefaultDetector", root, filepath.Join(root, "src/refund.py"), 1); row.Issue != 814 {
		t.Errorf("the row now names issue %d, and the first issue is forgotten", row.Issue)
	}
}

// TestALineThatCannotBeReadIsSaidOutLoud holds the other refusal at the same place: a ref whose line is blank or
// gone has no identity to be remembered by, and the run says so rather than keying every such sin alike.
func TestALineThatCannotBeReadIsSaidOutLoud(t *testing.T) {
	project(t, "src/refund.py", "def refund(order):\n\n    return order.total or 0\n")
	ghAnswers(t, "https://github.com/jessegall/code-commandments/issues/814\n", nil)

	said := &strings.Builder{}
	code := detectorReport(input(t, "src/refund.py:2"), "InventedDefaultDetector", "zero is the true count", refs("src/refund.py:2"), "v6.10.2", cli.Console{Out: said, Err: said})

	if code == 0 {
		t.Error("a report whose line could not be read answered success")
	}

	if !strings.Contains(said.String(), "could not be read") {
		t.Errorf("the run did not say why the line is not recorded: %q", said.String())
	}
}

// TestEveryReferenceOfOneReportIsRecorded holds a report that names several lines: each is reported, because each
// is a place a judge will meet.
func TestEveryReferenceOfOneReportIsRecorded(t *testing.T) {
	root := project(t, "src/refund.py", "    return order.total or 0\n    return order.tax or 0\n")
	ghAnswers(t, "https://github.com/jessegall/code-commandments/issues/814\n", nil)

	said := &strings.Builder{}
	detectorReport(input(t, "src/refund.py:1"), "InventedDefaultDetector", "zero is the true count", refs("src/refund.py:1", "src/refund.py:2"), "v6.10.2", cli.Console{Out: said, Err: said})

	held, _ := reported.Of(root)
	if len(held.Rows()) != 2 {
		t.Errorf("the record holds %d rows for a report of two lines", len(held.Rows()))
	}
}

// project is a project at a temp root holding the file, with the command run from inside it.
func project(t *testing.T, path, text string) string {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	file := filepath.Join(root, path)
	if err := os.MkdirAll(filepath.Dir(file), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(file, []byte(text), 0o644); err != nil {
		t.Fatal(err)
	}
	if err := os.MkdirAll(filepath.Join(root, ".commandments"), 0o755); err != nil {
		t.Fatal(err)
	}

	was, err := os.Getwd()
	if err != nil {
		t.Fatal(err)
	}
	if err := os.Chdir(root); err != nil {
		t.Fatal(err)
	}
	t.Cleanup(func() { os.Chdir(was) })

	return root
}

// ghAnswers puts a GitHub CLI of the test's own in the place of the one on the machine.
func ghAnswers(t *testing.T, stdout string, err error) {
	t.Helper()
	found, ran := looking, filing
	looking = func() (string, error) { return "gh", nil }
	filing = func(string, ...string) (string, string, error) { return stdout, "", err }
	t.Cleanup(func() { looking, filing = found, ran })
}

// refs are the references a report names.
func refs(labels ...string) []Reference {
	var named []Reference
	for _, label := range labels {
		if ref, ok := ParseReference(label); ok {
			named = append(named, ref)
		}
	}

	return named
}

// input is a command's input naming one ref, which is all the recording reads from it.
func input(t *testing.T, ref string) *cli.Input {
	t.Helper()

	return cli.InputOf("report", "--detector=InventedDefaultDetector", "--ref="+ref)
}

package report

import (
	"errors"
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/reported"

	_ "github.com/jessegall/code-commandments/registry"
)

func TestADetectorReportRecordsTheSinItWasAbout(t *testing.T) {
	root := project(t, "src/refund.py", "def refund(order):\n    return order.total or 0\n")
	ghAnswers(t, "https://github.com/jessegall/code-commandments/issues/814\n", nil)

	said := &strings.Builder{}
	if code := detectorReport(input(t, "src/refund.py:2"), "python/InventedDefaultDetector", "zero is the true count", refs("src/refund.py:2"), "v6.10.2", cli.Console{Out: said, Err: said}); code != 0 {
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
			detectorReport(input(t, "src/refund.py:1"), "python/InventedDefaultDetector", "zero is the true count", refs("src/refund.py:1"), "v6.10.2", cli.Console{Out: said, Err: said})

			if _, err := os.Stat(reported.Path(root)); err == nil {
				t.Error("a report that filed no issue wrote a row")
			}
		})
	}
}

func TestASinAlreadyReportedIsSaidOutLoudAndNotForgotten(t *testing.T) {
	root := project(t, "src/refund.py", "    return order.total or 0\n")
	ghAnswers(t, "https://github.com/jessegall/code-commandments/issues/814\n", nil)

	first := &strings.Builder{}
	detectorReport(input(t, "src/refund.py:1"), "python/InventedDefaultDetector", "zero is the true count", refs("src/refund.py:1"), "v6.10.2", cli.Console{Out: first, Err: first})

	ghAnswers(t, "https://github.com/jessegall/code-commandments/issues/900\n", nil)
	said := &strings.Builder{}
	code := detectorReport(input(t, "src/refund.py:1"), "python/InventedDefaultDetector", "the same again", refs("src/refund.py:1"), "v6.10.2", cli.Console{Out: said, Err: said})

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

func TestALineThatCannotBeReadIsSaidOutLoud(t *testing.T) {
	project(t, "src/refund.py", "def refund(order):\n\n    return order.total or 0\n")
	ghAnswers(t, "https://github.com/jessegall/code-commandments/issues/814\n", nil)

	said := &strings.Builder{}
	code := detectorReport(input(t, "src/refund.py:2"), "python/InventedDefaultDetector", "zero is the true count", refs("src/refund.py:2"), "v6.10.2", cli.Console{Out: said, Err: said})

	if code == 0 {
		t.Error("a report whose line could not be read answered success")
	}

	if !strings.Contains(said.String(), "could not be read") {
		t.Errorf("the run did not say why the line is not recorded: %q", said.String())
	}
}

func TestEveryReferenceOfOneReportIsRecorded(t *testing.T) {
	root := project(t, "src/refund.py", "    return order.total or 0\n    return order.tax or 0\n")
	ghAnswers(t, "https://github.com/jessegall/code-commandments/issues/814\n", nil)

	said := &strings.Builder{}
	detectorReport(input(t, "src/refund.py:1"), "python/InventedDefaultDetector", "zero is the true count", refs("src/refund.py:1", "src/refund.py:2"), "v6.10.2", cli.Console{Out: said, Err: said})

	held, _ := reported.Of(root)
	if len(held.Rows()) != 2 {
		t.Errorf("the record holds %d rows for a report of two lines", len(held.Rows()))
	}
}

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

func ghAnswers(t *testing.T, stdout string, err error) {
	t.Helper()
	found, ran := looking, filing
	looking = func() (string, error) { return "gh", nil }
	filing = func(string, ...string) (string, string, error) { return stdout, "", err }
	t.Cleanup(func() { looking, filing = found, ran })
}

func refs(labels ...string) []Reference {
	var named []Reference
	for _, label := range labels {
		if ref, ok := ParseReference(label); ok {
			named = append(named, ref)
		}
	}

	return named
}

func input(t *testing.T, ref string) *cli.Input {
	t.Helper()

	return cli.InputOf("report", "--detector=python/InventedDefaultDetector", "--ref="+ref)
}

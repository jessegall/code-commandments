package info

import (
	"os"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/cli/reported"
	_ "github.com/jessegall/code-commandments/registry"
)

// TestInfoNamesWhatThisProjectHasReported holds where a teammate who did not file a report learns of it: the rule's
// own explanation. Without it, a reader meeting a line a judge calls reported has nowhere to go but the record.
func TestInfoNamesWhatThisProjectHasReported(t *testing.T) {
	root := projectReporting(t, "InventedDefaultDetector", "src/refund.py", 1, 814)

	said := &strings.Builder{}
	code, err := Command{}.Run(cli.InputOf("info", "InventedDefaultDetector"), cli.Console{Out: said, Err: said})
	if err != nil || code != 0 {
		t.Fatalf("info answered %d: %v", code, err)
	}

	for _, want := range []string{"Reported by this project", "src/refund.py:1", "issues/814", "report follow"} {
		if !strings.Contains(said.String(), want) {
			t.Errorf("the explanation does not name %q:\n%s", want, said)
		}
	}

	if _, dropped, err := reported.Forget(root, 814); err != nil || !dropped {
		t.Fatalf("forgetting the report answered %v, %v", dropped, err)
	}

	quiet := &strings.Builder{}
	if _, err := (Command{}).Run(cli.InputOf("info", "InventedDefaultDetector"), cli.Console{Out: quiet, Err: quiet}); err != nil {
		t.Fatal(err)
	}

	if strings.Contains(quiet.String(), "Reported by this project") {
		t.Error("a rule this project has not reported still says it has")
	}
}

// projectReporting is a project at a temp root that has reported the rule's finding at this line, with the command
// run from inside it.
func projectReporting(t *testing.T, detector, path string, line, issue int) string {
	t.Helper()
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	file := filepath.Join(root, path)
	if err := os.MkdirAll(filepath.Dir(file), 0o755); err != nil {
		t.Fatal(err)
	}
	if err := os.WriteFile(file, []byte("    return order.total or 0\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	row := reported.Row{
		Detector:   detector,
		File:       file,
		Line:       line,
		Reason:     "zero is the true count of an empty set",
		Repository: "jessegall/code-commandments",
		Issue:      issue,
		Filed:      "2026-10-25T13:41:12Z",
	}

	if _, err := reported.Add(root, row); err != nil {
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

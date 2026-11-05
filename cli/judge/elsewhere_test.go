package judge

import (
	"bytes"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	_ "github.com/jessegall/code-commandments/registry"
)

// TestARunJudgedForAnotherFolderReadsThatFoldersPaths holds the one thing that breaks when a service judges on
// someone else's behalf: a path the run named is relative to the folder the RUN was started in, never to the
// process's own.
//
// The service's folder is the plugin's, so an unresolved `src` was read there, found nothing, and said so with a
// green tick and exit 0 over code holding sins. That was reported from a consumer on v7.7.0, for `judge src`,
// `judge --changes` and `judge --changed-files` alike, and a green tick that lies is the one answer a judging
// must never give.
func TestARunJudgedForAnotherFolderReadsThatFoldersPaths(t *testing.T) {
	project := sinfulProject(t)

	// The process stands somewhere else entirely, as the service does.
	elsewhere, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	chdir(t, elsewhere)

	var out, errs bytes.Buffer

	code, err := (Command{In: project}).Run(
		cli.InputOf("judge", "src", "--no-checklist", "--parallel=1"),
		cli.Console{Out: &out, Err: &errs},
	)
	if err != nil {
		t.Fatal(err)
	}

	if !strings.Contains(out.String(), "docstring-in-method") && !strings.Contains(out.String(), "DocstringInMethod") {
		t.Errorf("a run judged for %s found none of its sins (exit %d):\n%s%s", project, code, out.String(), errs.String())
	}

	if code != 1 {
		t.Errorf("the run exits %d, want 1 for a run that found a sin", code)
	}
}

// sinfulProject is a python project with one rule of its own and one file that breaks it.
func sinfulProject(t *testing.T) string {
	t.Helper()

	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	for path, text := range map[string]string{
		".commandments/config.json": `{"paths": ["src"], "detectors": ["DocstringInMethodDetector"]}`,
		".commandments/custom/DocstringInMethodDetector.json": `{"engine": "python",
 "sin": {"name": "docstring-in-method", "description": "a docstring inside a method", "skill": "python/absence"},
 "find": {"select": "function", "where": [{"documented": true}]}}`,
		"src/__init__.py": "",
		"src/order.py": `class Order:
    def total(self):
        """Says the total."""
        return 0
`,
	} {
		whole := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(whole), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(whole, []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	return root
}

// chdir moves the process into the folder for the test and puts it back afterwards, which is this module's own
// idiom: it declares go 1.23, where t.Chdir is refused by vet.
func chdir(t *testing.T, folder string) {
	t.Helper()

	was, err := os.Getwd()
	if err != nil {
		t.Fatal(err)
	}

	if err := os.Chdir(folder); err != nil {
		t.Fatal(err)
	}

	t.Cleanup(func() { os.Chdir(was) })
}

// TestTheChangedLinesAreTheRunsOwnFoldersChanges holds the headline symptom of the same fault: judged for
// another folder, `--changes` answered "no source file under the path is among the lines changed" with exit 0,
// because the path it scoped itself with was the service's folder and nothing had changed there.
func TestTheChangedLinesAreTheRunsOwnFoldersChanges(t *testing.T) {
	project := sinfulProject(t)

	for _, command := range [][]string{
		{"init", "-q"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "add", "-A"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", "clean"},
	} {
		if out, err := exec.Command("git", append([]string{"-C", project}, command...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", command, out)
		}
	}

	// The sin is in the working tree and in no commit, which is what --changes judges.
	if err := os.WriteFile(filepath.Join(project, "src", "order.py"), []byte(`class Order:
    def total(self):
        """Says the total."""
        return 0

    def paid(self):
        """Says whether it is paid."""
        return True
`), 0o644); err != nil {
		t.Fatal(err)
	}

	elsewhere, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}
	chdir(t, elsewhere)

	var out, errs bytes.Buffer

	code, err := (Command{In: project}).Run(
		cli.InputOf("judge", "--changes", "--no-checklist", "--parallel=1"),
		cli.Console{Out: &out, Err: &errs},
	)
	if err != nil {
		t.Fatal(err)
	}

	if code != 1 {
		t.Errorf("judging the changed lines of %s exits %d, want 1:\n%s%s", project, code, out.String(), errs.String())
	}

	if !strings.Contains(out.String(), "order.py") {
		t.Errorf("the changed lines hold a sin the run did not name:\n%s%s", out.String(), errs.String())
	}
}

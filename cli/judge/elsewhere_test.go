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

func TestARunJudgedForAnotherFolderReadsThatFoldersPaths(t *testing.T) {
	project := sinfulProject(t)

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

func TestANewFileIsAmongTheChangedLines(t *testing.T) {
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

	if err := os.WriteFile(filepath.Join(project, "src", "refund.py"), []byte(`class Refund:
    def total(self):
        """Says the total."""
        return 0
`), 0o644); err != nil {
		t.Fatal(err)
	}

	chdir(t, project)

	var out, errs bytes.Buffer

	code, err := (Command{}).Run(
		cli.InputOf("judge", "--changes", "--no-checklist", "--parallel=1"),
		cli.Console{Out: &out, Err: &errs},
	)
	if err != nil {
		t.Fatal(err)
	}

	if !strings.Contains(out.String(), "refund.py") {
		t.Errorf("the changed lines leave out a file git has never seen (exit %d):\n%s%s", code, out.String(), errs.String())
	}
}

func TestAWorktreesOwnNewFileIsAmongItsChangedLines(t *testing.T) {
	project := sinfulProject(t)

	worktree := filepath.Join(project, ".journal", "worktrees", "worker-1")
	for _, command := range [][]string{
		{"init", "-q", "-b", "main"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "add", "-A"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", "clean"},
		{"worktree", "add", "-q", "-b", "a-worker", worktree},
	} {
		if out, err := exec.Command("git", append([]string{"-C", project}, command...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", command, out)
		}
	}

	if err := os.WriteFile(filepath.Join(worktree, "src", "refund.py"), []byte(`class Refund:
    def total(self):
        """Says the total."""
        return 0
`), 0o644); err != nil {
		t.Fatal(err)
	}

	chdir(t, worktree)

	var out, errs bytes.Buffer

	code, err := (Command{}).Run(
		cli.InputOf("judge", "--changes", "--no-checklist", "--parallel=1", "--allow-cold-read"),
		cli.Console{Out: &out, Err: &errs},
	)
	if err != nil {
		t.Fatal(err)
	}

	if !strings.Contains(out.String(), "refund.py") {
		t.Errorf("a worker's own new file is not among its worktree's changed lines (exit %d):\n%s%s", code, out.String(), errs.String())
	}
}

package judge

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/engine"
)

func TestATwinInAnotherCheckoutIsToldWhereThisOneHasIt(t *testing.T) {
	project := filepath.Join(t.TempDir(), "project")
	if err := os.MkdirAll(filepath.Join(project, "src"), 0o755); err != nil {
		t.Fatal(err)
	}

	for _, line := range [][]string{
		{"init", "-q", "-b", "main"},
		{"config", "user.email", "t@t"},
		{"config", "user.name", "t"},
	} {
		if out, err := exec.Command("git", append([]string{"-C", project}, line...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", line, out)
		}
	}

	if err := os.WriteFile(filepath.Join(project, "src", "order.py"), []byte("class Order:\n    pass\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	for _, line := range [][]string{{"add", "-A"}, {"commit", "-qm", "first"}} {
		if out, err := exec.Command("git", append([]string{"-C", project}, line...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", line, out)
		}
	}

	worktree := filepath.Join(filepath.Dir(project), "project-worker-1")
	if out, err := exec.Command("git", "-C", project, "worktree", "add", "-q", "-b", "worker-1", worktree).CombinedOutput(); err != nil {
		t.Fatalf("git worktree add: %s", out)
	}

	report := NewReport(worktree, []engine.Finding{{
		Location: filepath.Join(worktree, "src", "made.py") + ":3",
		Twins:    []string{filepath.Join(project, "src", "order.py") + ":1"},
		Skill:    "python/duplication",
	}}, nil, nil, Skipped{})

	if said := report.relative(filepath.Join(project, "src", "order.py") + ":1"); said != "src/order.py:1" {
		t.Errorf("a twin in the project's own checkout is told as %q, which the worker cannot open; it holds that "+
			"same file at src/order.py", said)
	}

	if said := report.relative(filepath.Join(worktree, "src", "made.py") + ":3"); said != "src/made.py:3" {
		t.Errorf("the finding's own path is told as %q", said)
	}

	if said := report.relative("/somewhere/else/other.py:2"); !strings.HasPrefix(said, "/somewhere") {
		t.Errorf("a path belonging to no checkout of this project is rewritten to %q, which invents a file", said)
	}
}

func TestTheProjectsOwnReportMirrorsNothing(t *testing.T) {
	folder := t.TempDir()

	if out, err := exec.Command("git", "-C", folder, "init", "-q", "-b", "main").CombinedOutput(); err != nil {
		t.Fatalf("git init: %s", out)
	}

	if said := mirrored(folder); said != "" {
		t.Errorf("the project's own checkout says it mirrors %q, so its own paths would be rewritten", said)
	}
}

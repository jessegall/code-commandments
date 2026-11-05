package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
)

// TestAWorkersFileInAWorktreeIsJudged holds the gate that drops nothing a worker writes. A worktree is a
// checkout of its own inside the project's hidden folder, so a file in it, named from the project, reads as a
// path outside every declared root: a worker's edits were never judged at all and its events were answered with
// nothing, while a judging of the same file by its absolute path found eight sins. Reported from a consumer on
// v7.8.1 for worker 545.
func TestAWorkersFileInAWorktreeIsJudged(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	for path, text := range map[string]string{
		".commandments/config.json": `{"paths": ["src"]}`,
		"src/order.py":              "class Order:\n    pass\n",
	} {
		whole := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(whole), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(whole, []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	// Both checkouts are real ones, which is what makes the worktree a checkout of its own rather than a folder.
	for _, command := range [][]string{
		{"init", "-q", "-b", "main"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "add", "-A"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", "the project"},
		{"worktree", "add", "-q", "-b", "a-worker", filepath.Join(root, ".journal", "worktrees", "worker-1")},
	} {
		if out, err := exec.Command("git", append([]string{"-C", root}, command...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", command, out)
		}
	}

	if err := os.WriteFile(filepath.Join(root, ".journal", "worktrees", "worker-1", "src", "order.py"), []byte("class Order:\n    pass\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	project, _ := config.Load(root)

	own := filepath.Join(root, "src", "order.py")
	if judged := judgedFile(root, own, project); judged != own {
		t.Errorf("the project's own file is judged as %q", judged)
	}

	worker := filepath.Join(root, ".journal", "worktrees", "worker-1", "src", "order.py")
	if judged := judgedFile(root, worker, project); judged != worker {
		t.Errorf("a worker's file, named from the project, is judged as %q; a worker's edits are then never judged", judged)
	}
}

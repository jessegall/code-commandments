package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"testing"
)

func TestACutWorktreeHoldsOnlyItsDetectors(t *testing.T) {
	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	for path, text := range map[string]string{
		".commandments/config.json":       "{}\n",
		".commandments/custom/Order.json": "{}\n",
	} {
		if err := os.MkdirAll(filepath.Dir(filepath.Join(root, path)), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(filepath.Join(root, path), []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")
	for _, line := range [][]string{
		{"init", "-q", "-b", "main"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "add", "-A"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", "the project"},
		{"worktree", "add", "-q", "-b", "a-worker", worktree},
	} {
		if out, err := exec.Command("git", append([]string{"-C", root}, line...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", line, out)
		}
	}

	WorktreeCut(map[string]any{"event": cutEvent, "row": map[string]any{"folder": worktree}})

	if _, err := os.Stat(filepath.Join(worktree, ".commandments", "config.json")); !os.IsNotExist(err) {
		t.Errorf("the cut worktree still holds a config of its own: %v", err)
	}

	if _, err := os.Stat(filepath.Join(worktree, ".commandments", "custom", "Order.json")); err != nil {
		t.Errorf("the cut worktree lost its detectors: %v", err)
	}

	if _, err := os.Stat(filepath.Join(root, ".commandments", "config.json")); err != nil {
		t.Errorf("the main checkout lost its config: %v", err)
	}

	if out, _ := exec.Command("git", "-C", worktree, "status", "--porcelain").CombinedOutput(); len(out) != 0 {
		t.Errorf("git in the cut worktree sees a change a worker could commit:\n%s", out)
	}
}

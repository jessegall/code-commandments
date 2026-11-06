package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

func TestAMarkNamesTheFileAsTheWorkerSeesIt(t *testing.T) {
	root, _ := workerProject(t)
	worktree := filepath.Join(root, ".journal", "worktrees", "development", "worker-5")

	if out, err := exec.Command("git", "-C", root, "worktree", "add", "-q", "-b", "feature/worker-5", worktree).CombinedOutput(); err != nil {
		t.Fatalf("git worktree add: %s", out)
	}

	under := filepath.Join("src", "made.py")
	if err := os.WriteFile(filepath.Join(worktree, under), []byte(olderSin), 0o644); err != nil {
		t.Fatal(err)
	}

	for _, told := range []struct {
		what string
		file string
	}{
		{"the worker's own copy", filepath.Join(worktree, under)},
		{"the main checkout's copy of the same path", filepath.Join(root, under)},
	} {
		named := seenFrom(worktree, told.file)

		if named != under {
			t.Errorf("%s is named %q, and the worker opens %q", told.what, named, under)
		}

		if filepath.IsAbs(named) || strings.Contains(named, root) {
			t.Errorf("%s is named with a path outside the worker's checkout: %s", told.what, named)
		}
	}
}

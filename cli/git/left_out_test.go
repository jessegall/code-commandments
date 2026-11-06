package git

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

func TestAFolderLeftOutOfAWorktreeIsStillCommittedWhole(t *testing.T) {
	root := aRepositoryWith(t, map[string]string{
		".commandments/config.json": `{"paths": ["src"]}`,
		"src/order.py":              "class Order:\n    pass\n",
	})

	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")
	gitIn(t, root, "worktree", "add", "-q", "-b", "a-worker", worktree)

	if _, err := os.Stat(filepath.Join(worktree, ".commandments", "config.json")); err != nil {
		t.Fatalf("the worktree never carried the folder, so this proves nothing: %v", err)
	}

	if err := LeaveOutOfCheckout(worktree, ".commandments"); err != nil {
		t.Fatal(err)
	}

	if _, err := os.Stat(filepath.Join(worktree, ".commandments")); !os.IsNotExist(err) {
		t.Errorf("the folder is still on the worktree's disk: %v", err)
	}

	if _, err := os.Stat(filepath.Join(root, ".commandments", "config.json")); err != nil {
		t.Errorf("the project's own rules went with it: %v", err)
	}

	if said := gitIn(t, worktree, "status", "--porcelain"); strings.Contains(said, ".commandments") {
		t.Errorf("git reports the folder as changed, so a hand-in would commit its deletion:\n%s", said)
	}

	if err := os.WriteFile(filepath.Join(worktree, "src", "order.py"), []byte("class Order:\n    paid = True\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	gitIn(t, worktree, "-c", "user.email=t@t", "-c", "user.name=t", "commit", "-aqm", "the worker's work")

	if said := gitIn(t, worktree, "ls-tree", "-r", "--name-only", "HEAD"); !strings.Contains(said, ".commandments/config.json") {
		t.Errorf("the worker's commit no longer holds the project's rules:\n%s", said)
	}
}

func TestACheckoutThatAlreadyLeavesFilesOutIsLeftAlone(t *testing.T) {
	root := aRepositoryWith(t, map[string]string{"src/order.py": "class Order:\n    pass\n"})

	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")
	gitIn(t, root, "worktree", "add", "-q", "-b", "a-worker", worktree)
	gitIn(t, worktree, "sparse-checkout", "set", "--no-cone", "/src/")

	if err := LeaveOutOfCheckout(worktree, ".commandments"); err == nil {
		t.Error("a checkout that already leaves files out was written over without a word")
	}

	if said := gitIn(t, worktree, "sparse-checkout", "list"); !strings.Contains(said, "/src/") {
		t.Errorf("its own patterns were replaced: %q", said)
	}
}

func TestTheMainCheckoutIsNeverLeftOut(t *testing.T) {
	root := aRepositoryWith(t, map[string]string{".commandments/config.json": `{"paths": ["src"]}`})

	if err := LeaveOutOfCheckout(root, ".commandments"); err == nil {
		t.Fatal("the main checkout had its own rules left out of it")
	}

	if _, err := os.Stat(filepath.Join(root, ".commandments", "config.json")); err != nil {
		t.Errorf("the project's own rules are gone: %v", err)
	}
}

func aRepositoryWith(t *testing.T, files map[string]string) string {
	t.Helper()

	root, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	for path, text := range files {
		whole := filepath.Join(root, path)
		if err := os.MkdirAll(filepath.Dir(whole), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(whole, []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
	}

	gitIn(t, root, "init", "-q", "-b", "main")
	gitIn(t, root, "-c", "user.email=t@t", "-c", "user.name=t", "add", "-A")
	gitIn(t, root, "-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", "the project")

	return root
}

func gitIn(t *testing.T, dir string, args ...string) string {
	t.Helper()

	out, err := exec.Command("git", append([]string{"-C", dir}, args...)...).CombinedOutput()
	if err != nil {
		t.Fatalf("git %v in %s: %s", args, dir, out)
	}

	return string(out)
}

func TestTheBranchAndTheMergeBaseAreAskedOnceUntilAHeadMoves(t *testing.T) {
	root := aRepositoryWith(t, map[string]string{"src/order.py": "class Order:\n    pass\n"})

	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")
	gitIn(t, root, "worktree", "add", "-q", "-b", "a-worker", worktree)

	branch, mergeBase := CurrentBranch(root), MergeBase(worktree, "main")
	if branch != "main" || mergeBase == "" {
		t.Fatalf("the branch is %q and the merge base %q, so this test is measuring nothing", branch, mergeBase)
	}

	before := Asked()
	for at := 0; at < 20; at++ {
		if said := CurrentBranch(root); said != branch {
			t.Fatalf("the remembered branch is %q, want %q", said, branch)
		}
		if said := MergeBase(worktree, "main"); said != mergeBase {
			t.Fatalf("the remembered merge base is %q, want %q", said, mergeBase)
		}
	}

	if asked := Asked() - before; asked != 0 {
		t.Errorf("forty answers about an unmoved HEAD cost %d git processes; every one of them was already known", asked)
	}

	gitIn(t, root, "checkout", "-q", "-b", "somewhere-else")

	if said := CurrentBranch(root); said != "somewhere-else" {
		t.Errorf("the branch reads as %q after a switch; the cache answered about a branch nobody is on", said)
	}

	gitIn(t, worktree, "-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", "the worker's work", "--allow-empty")

	if said := MergeBase(worktree, "main"); said != mergeBase {
		t.Errorf("the merge base reads as %q after a commit on the worker's own branch, want %q, which is where "+
			"the two still last met", said, mergeBase)
	}
}

package git

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

// TestAFolderLeftOutOfAWorktreeIsStillCommittedWhole holds the one thing that makes this safe to do at all. A
// worktree carries a copy of .commandments on its branch; nothing reads it, since the config and the project's
// own rules always come from the main checkout, but anyone who opens the worktree takes it for the rules in
// force. So it is taken off that checkout's disk.
//
// NOT removed. The folder is tracked, and the journal commits a worker's changes when it hands in: a deletion
// would be committed and the merge would take the project's own rules with it on the main branch. So the test
// that matters is not that the file is gone — it is that a commit made afterwards still carries it.
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

	// The project's own copy is untouched, which is the whole point of doing it per checkout.
	if _, err := os.Stat(filepath.Join(root, ".commandments", "config.json")); err != nil {
		t.Errorf("the project's own rules went with it: %v", err)
	}

	// git sees no deletion, so a worker's hand-in commits nothing about it.
	if said := gitIn(t, worktree, "status", "--porcelain"); strings.Contains(said, ".commandments") {
		t.Errorf("git reports the folder as changed, so a hand-in would commit its deletion:\n%s", said)
	}

	// And the proof: the worker commits its own work, and the folder is still in the tree that commit names.
	if err := os.WriteFile(filepath.Join(worktree, "src", "order.py"), []byte("class Order:\n    paid = True\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	gitIn(t, worktree, "-c", "user.email=t@t", "-c", "user.name=t", "commit", "-aqm", "the worker's work")

	if said := gitIn(t, worktree, "ls-tree", "-r", "--name-only", "HEAD"); !strings.Contains(said, ".commandments/config.json") {
		t.Errorf("the worker's commit no longer holds the project's rules:\n%s", said)
	}
}

// TestACheckoutThatAlreadyLeavesFilesOutIsLeftAlone holds somebody else's choice. A checkout with sparse-checkout
// patterns of its own has them for a reason, and writing ours over them would take away files the work needs.
func TestACheckoutThatAlreadyLeavesFilesOutIsLeftAlone(t *testing.T) {
	root := aRepositoryWith(t, map[string]string{"src/order.py": "class Order:\n    pass\n"})

	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")
	gitIn(t, root, "worktree", "add", "-q", "-b", "a-worker", worktree)
	gitIn(t, worktree, "sparse-checkout", "set", "--no-cone", "/src/")

	if err := LeaveOutOfCheckout(worktree, ".commandments"); err == nil {
		t.Error("a checkout that already leaves files out was written over without a word")
	}

	// And its own patterns still stand.
	if said := gitIn(t, worktree, "sparse-checkout", "list"); !strings.Contains(said, "/src/") {
		t.Errorf("its own patterns were replaced: %q", said)
	}
}

// TestTheMainCheckoutIsNeverLeftOut holds the guard. The main checkout is where the rules really live, and
// leaving them out of it would judge the project by nothing at all.
func TestTheMainCheckoutIsNeverLeftOut(t *testing.T) {
	root := aRepositoryWith(t, map[string]string{".commandments/config.json": `{"paths": ["src"]}`})

	if err := LeaveOutOfCheckout(root, ".commandments"); err == nil {
		t.Fatal("the main checkout had its own rules left out of it")
	}

	if _, err := os.Stat(filepath.Join(root, ".commandments", "config.json")); err != nil {
		t.Errorf("the project's own rules are gone: %v", err)
	}
}

// aRepositoryWith is a committed repository holding those files.
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

// gitIn runs git in that folder and fails the test where git does.
func gitIn(t *testing.T, dir string, args ...string) string {
	t.Helper()

	out, err := exec.Command("git", append([]string{"-C", dir}, args...)...).CombinedOutput()
	if err != nil {
		t.Fatalf("git %v in %s: %s", args, dir, out)
	}

	return string(out)
}

// TestTheBranchAndTheMergeBaseAreAskedOnceUntilAHeadMoves holds the caching to what it is for. Asking git costs a
// process, and the warm path asks on every edit of every agent and twice over on every judging: which branch the
// project is on, and where a worktree's branch last met it. Neither answer can change unless a HEAD moves.
//
// Against the FILES that say where HEAD is, never against a clock: a cache that expires is either too short to
// buy anything or long enough to answer about a branch nobody is on.
func TestTheBranchAndTheMergeBaseAreAskedOnceUntilAHeadMoves(t *testing.T) {
	root := aRepositoryWith(t, map[string]string{"src/order.py": "class Order:\n    pass\n"})

	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")
	gitIn(t, root, "worktree", "add", "-q", "-b", "a-worker", worktree)

	// Asked once each, so what the answers are is on the record before any of them is counted.
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

	// And a HEAD that MOVES is asked again, which is the whole of what makes the cache safe to have.
	gitIn(t, root, "checkout", "-q", "-b", "somewhere-else")

	if said := CurrentBranch(root); said != "somewhere-else" {
		t.Errorf("the branch reads as %q after a switch; the cache answered about a branch nobody is on", said)
	}

	// A commit on the branch it is already on moves the ref and not HEAD, which the state must notice too.
	gitIn(t, worktree, "-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", "the worker's work", "--allow-empty")

	if said := MergeBase(worktree, "main"); said != mergeBase {
		t.Errorf("the merge base reads as %q after a commit on the worker's own branch, want %q, which is where "+
			"the two still last met", said, mergeBase)
	}
}

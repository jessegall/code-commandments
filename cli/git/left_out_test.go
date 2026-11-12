package git

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

func TestOnlyTheSparsePatternsAnOldReleaseWroteAreTurnedOff(t *testing.T) {
	root := aRepositoryWith(t, map[string]string{
		".commandments/config.json": `{"paths": ["src"]}`,
		"docs/a.md":                 "a\n",
	})

	ours := filepath.Join(root, ".journal", "worktrees", "worker-1")
	theirs := filepath.Join(root, ".journal", "worktrees", "worker-2")
	gitIn(t, root, "worktree", "add", "-q", "-b", "worker-1", ours)
	gitIn(t, root, "worktree", "add", "-q", "-b", "worker-2", theirs)
	gitIn(t, ours, "sparse-checkout", "set", "--no-cone", "/*", "!/.commandments/config.json")
	gitIn(t, theirs, "sparse-checkout", "set", "--no-cone", "/*", "!/docs/")

	if undone, err := UndoLeavingOut(ours, ".commandments/config.json"); !undone || err != nil {
		t.Fatalf("the patterns an old release wrote were left on: undone=%v, %v", undone, err)
	}

	if _, err := os.Stat(filepath.Join(ours, ".commandments", "config.json")); err != nil {
		t.Errorf("turning the patterns off did not bring the checkout back whole: %v", err)
	}

	if undone, _ := UndoLeavingOut(theirs, ".commandments/config.json"); undone {
		t.Error("patterns somebody else chose were turned off")
	}

	if said := gitIn(t, theirs, "sparse-checkout", "list"); !strings.Contains(said, "!/docs/") {
		t.Errorf("somebody's own patterns were changed: %q", said)
	}

	if undone, _ := UndoLeavingOut(ours, ".commandments/config.json"); undone {
		t.Error("a checkout already whole was undone a second time")
	}
}

func TestAFileKeptOffDiskIsNoDeletionAWorkerCommits(t *testing.T) {
	root := aRepositoryWith(t, map[string]string{
		".commandments/config.json": `{"paths": ["src"]}`,
		"src/order.py":              "class Order:\n    pass\n",
	})

	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")
	gitIn(t, root, "worktree", "add", "-q", "-b", "a-worker", worktree)

	if err := KeepOffDisk(worktree, ".commandments", func(string) bool { return false }); err != nil {
		t.Fatal(err)
	}
	if err := os.Remove(filepath.Join(worktree, ".commandments", "config.json")); err != nil {
		t.Fatal(err)
	}

	if said := gitIn(t, worktree, "status", "--porcelain"); said != "" {
		t.Errorf("git sees the removal, so a hand-in would commit it:\n%s", said)
	}

	if err := os.WriteFile(filepath.Join(worktree, "src", "order.py"), []byte("class Order:\n    paid = True\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	gitIn(t, worktree, "add", "-A")
	gitIn(t, worktree, "-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", "the worker's work")

	if said := gitIn(t, worktree, "show", "--name-status", "--format=", "HEAD"); strings.Contains(said, "config.json") {
		t.Errorf("the worker's commit carries the config:\n%s", said)
	}
}

func TestStoppingTrackingIsCommittedAloneAndLeavesTheIndexClean(t *testing.T) {
	root := aRepositoryWith(t, map[string]string{
		".commandments/config.json": `{"paths": ["src"]}`,
		"src/order.py":              "class Order:\n    pass\n",
	})
	gitIn(t, root, "config", "user.email", "t@t")
	gitIn(t, root, "config", "user.name", "t")
	if err := os.WriteFile(filepath.Join(root, ".commandments", ".gitignore"), []byte("*\n!.gitignore\n"), 0o644); err != nil {
		t.Fatal(err)
	}
	gitIn(t, root, "add", ".commandments/.gitignore")
	gitIn(t, root, "commit", "-qm", "ignore the state")

	if err := os.WriteFile(filepath.Join(root, "src", "order.py"), []byte("class Order:\n    paid = True\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	if done, err := CommitStoppedTracking(root, "stop tracking", []string{".commandments/config.json"}); !done || err != nil {
		t.Fatalf("the untracking was not committed: done=%v, %v", done, err)
	}

	if Tracks(root, ".commandments/config.json") {
		t.Error("the config is still tracked")
	}

	if said := gitIn(t, root, "show", "--name-status", "--format=", "HEAD"); strings.TrimSpace(said) != "D\t.commandments/config.json" {
		t.Errorf("the commit carries more than the untracking:\n%s", said)
	}

	if said := gitIn(t, root, "status", "--porcelain"); said != " M src/order.py\n" {
		t.Errorf("the checkout's own work or its index changed:\n%s", said)
	}

	if _, err := os.Stat(filepath.Join(root, ".commandments", "config.json")); err != nil {
		t.Errorf("the config left the main checkout's disk: %v", err)
	}
}

func TestAMergeInProgressOrAnUnbornBranchIsLeftAlone(t *testing.T) {
	root := aRepositoryWith(t, map[string]string{".commandments/config.json": `{"paths": ["src"]}`})
	head := Head(root)

	merging := filepath.Join(root, ".git", "MERGE_HEAD")
	if err := os.WriteFile(merging, []byte(head+"\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	if done, err := CommitStoppedTracking(root, "stop tracking", []string{".commandments/config.json"}); done || err != nil {
		t.Errorf("a checkout in the middle of a merge was committed to: done=%v, %v", done, err)
	}

	if Head(root) != head || !Tracks(root, ".commandments/config.json") {
		t.Error("the checkout changed while a merge was in progress")
	}

	unborn := t.TempDir()
	gitIn(t, unborn, "init", "-q", "-b", "main")

	if done, err := CommitStoppedTracking(unborn, "stop tracking", []string{".commandments/config.json"}); done || err != nil {
		t.Errorf("a branch with no commit was said to be committed to: done=%v, %v", done, err)
	}
}

func TestAPathIsTakenLiterallyNotAsAPattern(t *testing.T) {
	root := aRepositoryWith(t, map[string]string{
		".commandments/[a].json": "{}",
		".commandments/a.json":   "{}",
	})
	gitIn(t, root, "config", "user.email", "t@t")
	gitIn(t, root, "config", "user.name", "t")

	if done, err := CommitStoppedTracking(root, "stop tracking", []string{".commandments/[a].json"}); !done || err != nil {
		t.Fatalf("done=%v, %v", done, err)
	}

	if !Tracks(root, ".commandments/a.json") {
		t.Error("a file whose name matched the other's as a pattern was untracked too")
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

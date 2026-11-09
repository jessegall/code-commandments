package git

import (
	"os"
	"path/filepath"
	"testing"
)

func TestAMergeStillInProgressIsMeasuredFromWhatItBringsIn(t *testing.T) {
	root := aRepositoryWith(t, map[string]string{
		"src/mine.py":   "class Mine:\n    pass\n",
		"src/theirs.py": "class Theirs:\n    pass\n",
	})
	branch := CurrentBranch(root)

	worktree := filepath.Join(root, ".journal", "worktrees", "worker-1")
	gitIn(t, root, "worktree", "add", "-q", "-b", "a-worker", worktree)

	writeAndCommit(t, worktree, "src/mine.py", "class Mine:\n    worker = True\n")
	writeAndCommit(t, root, "src/theirs.py", "class Theirs:\n    merged = True\n")

	cut := MergeBase(worktree, branch)

	gitIn(t, worktree, "-c", "user.email=t@t", "-c", "user.name=t", "merge", "-q", "--no-commit", "--no-ff", branch)

	since := MergeBase(worktree, cut)
	if since == cut {
		t.Fatal("the merge in progress is still measured from the worker's old cut")
	}

	if ChangedLinesSince(worktree, since, "src/theirs.py").Covers(2) {
		t.Error("a line the merge brought in is counted as the worker's")
	}

	if !ChangedLinesSince(worktree, since, "src/mine.py").Covers(2) {
		t.Error("the worker's own line is no longer counted as the worker's")
	}
}

func writeAndCommit(t *testing.T, root, file, text string) {
	t.Helper()

	if err := os.WriteFile(filepath.Join(root, file), []byte(text), 0o644); err != nil {
		t.Fatal(err)
	}

	gitIn(t, root, "-c", "user.email=t@t", "-c", "user.name=t", "commit", "-aqm", "a change to "+file)
}

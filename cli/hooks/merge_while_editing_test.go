package hooks

import (
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/git"
)

func TestAMergeJudgesTheProjectAndNotTheWorkersCopy(t *testing.T) {
	root, before, _ := mergedProject(t)

	worktree := stressWorktreeIn(root, 24)
	cutInto(t, root, worktree, "feature/not-mine")

	wroteInto(t, filepath.Join(worktree, "src", "only_here.py"), olderSin)

	said := MergeLanded(mergeLine(root, "main", before, git.Head(root)))

	if strings.Contains(said, "only_here.py") {
		t.Errorf("the merge named a file that exists only in the worker's own checkout: %s", said)
	}

	if !strings.Contains(said, "brought.py") {
		t.Errorf("the merge does not name what it really brought in: %s", said)
	}
}

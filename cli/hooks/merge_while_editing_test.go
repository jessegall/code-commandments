package hooks

import (
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
)

func TestAMergeDoesNotDisturbTheWorkersNextLane(t *testing.T) {
	root, before, _ := mergedProject(t)

	worktree := stressWorktreeIn(root, 23)
	cutInto(t, root, worktree, "feature/next-task")

	under := filepath.Join("src", "next.py")
	file := filepath.Join(worktree, under)
	wroteInto(t, file, sinOnLineTwo)

	settings, _ := config.Load(root)

	kept.CutAt(worktree, "aaaaaaa")

	if said := linesToldFor(t, worktree, file, settings); !strings.Contains(said, ":2") {
		t.Fatalf("the worker's own edit in its next worktree is answered as %q", said)
	}

	held := kept.Over(worktree).Count()
	if held == 0 {
		t.Fatal("the worker's lane holds none of its files, so a merge cannot disturb it")
	}

	if landed := MergeLanded(mergeLine(root, "main", before, git.Head(root))); landed == "" {
		t.Fatal("the merge of the earlier task said nothing, so this test proves nothing about it")
	}

	if after := kept.Over(worktree).Count(); after != held {
		t.Errorf("the merge left the worker's lane holding %d file(s) where it held %d", after, held)
	}

	if cut := kept.CutOf(worktree); cut != "aaaaaaa" {
		t.Errorf("the merge moved the worker's lane to a cut of %q", cut)
	}

	if said := linesToldFor(t, worktree, file, settings); !strings.Contains(said, ":2") {
		t.Errorf("after the merge the worker's own edit is answered as %q, and its sin is still on line 2", said)
	}
}

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

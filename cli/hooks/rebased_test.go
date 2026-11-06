package hooks

import (
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/git"
)

func TestAWorkerThatRebasesItselfIsRefilledAndNeverAnsweredFromTheOldBase(t *testing.T) {
	root, _ := workerProject(t)
	worktree := stressWorktreeIn(root, 11)

	cutInto(t, root, worktree, "feature/rebased-by-hand")

	under := filepath.Join("src", "made.py")
	file := filepath.Join(worktree, under)
	wroteInto(t, file, sinOnLineTwo)

	settings, _ := config.Load(root)

	warmFor(t, root, settings)

	kept.CutAt(worktree, "aaaaaaa")

	if said := linesToldFor(t, worktree, file, settings); !strings.Contains(said, ":2") {
		t.Fatalf("the worker's own text is answered as %q, and its sin is on line 2", said)
	}

	head := git.Head(worktree)

	commitIn(t, worktree, "the worker's own commit")

	if git.Head(worktree) == head {
		t.Fatal("the commit did not move the worktree's head, so nothing of this test is being exercised")
	}

	if kept.Over(worktree).FilledAt(git.Head(worktree)) {
		t.Fatal("the overlay says it was filled at the head the worktree is on now, so a rebase by hand would go unnoticed")
	}

	wroteInto(t, file, sinOnLineFive)

	if said := linesToldFor(t, worktree, file, settings); !strings.Contains(said, ":6") {
		t.Errorf("after the worktree moved its own head, the answer is %q, and the sin is now on line 6", said)
	}
}

func commitIn(t *testing.T, root, message string) {
	t.Helper()

	for _, command := range [][]string{
		{"-c", "user.email=t@t", "-c", "user.name=t", "add", "-A"},
		{"-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", message},
	} {
		if out, err := exec.Command("git", append([]string{"-C", root}, command...)...).CombinedOutput(); err != nil {
			t.Fatalf("git %v: %s", command, out)
		}
	}
}

package hooks

import (
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/git"
	"github.com/jessegall/code-commandments/cli/workspace"
)

func mergedProject(t *testing.T) (root, before, after string) {
	t.Helper()

	root, _ = workerProject(t)

	before = git.Head(root)

	wroteInto(t, filepath.Join(root, "src", "brought.py"), olderSin)
	commitIn(t, root, "the work a worker merged")

	after = git.Head(root)

	if before == after {
		t.Fatal("the commit did not move the project's head, so there is no range to judge")
	}

	return root, before, after
}

func mergeLine(root, branch, before, after string) map[string]any {
	return map[string]any{
		"v": 1, "event": landedEvent, "agent": 1, "cwd": root,
		"data": map[string]any{
			"checkout": map[string]any{"project_root": root, "branch": branch},
			"row": map[string]any{
				"base_branch": branch, "commit_before_merge": before, "commit_after_merge": after,
				"session_agent_id": "worker-7", "task_id": "412", "folder": root + "-worker-7",
			},
		},
	}
}

func TestAMergeIsJudgedWhenItLandsAndNamesWhoDidTheWork(t *testing.T) {
	root, before, after := mergedProject(t)

	said := MergeLanded(mergeLine(root, "main", before, after))

	if said == "" {
		t.Fatal("a merge that brought in a sin was answered with nothing at all")
	}

	for _, names := range []string{"worker-7", "task 412", "brought.py", "fix at its source"} {
		if !strings.Contains(said, names) {
			t.Errorf("what the merge said does not name %q: %s", names, said)
		}
	}

	if strings.Contains(said, root) {
		t.Errorf("the merge names a file by a path outside the project: %s", said)
	}
}

func TestTheSameMergeDeliveredTwiceSaysNothingTheSecondTime(t *testing.T) {
	root, before, after := mergedProject(t)

	if first := MergeLanded(mergeLine(root, "main", before, after)); first == "" {
		t.Fatal("the first delivery said nothing, so a second saying nothing proves nothing")
	}

	if second := MergeLanded(mergeLine(root, "main", before, after)); second != "" {
		t.Errorf("the same merge delivered twice was judged twice:\n%s", second)
	}
}

func TestAMergeWhoseDeliveryWasLostIsPickedUpByTheNext(t *testing.T) {
	root, before, after := mergedProject(t)

	if err := MergedIn(workspace.At(root, "")).Judged("main", before); err != nil {
		t.Fatal(err)
	}

	wroteInto(t, filepath.Join(root, "src", "later.py"), sinOnLineFive)
	commitIn(t, root, "the work of a second merge")

	later := git.Head(root)

	said := MergeLanded(mergeLine(root, "main", after, later))

	if !strings.Contains(said, "later.py") {
		t.Errorf("the second merge does not name its own file: %s", said)
	}

	if !strings.Contains(said, "brought.py") {
		t.Errorf("the merge whose delivery was lost was never picked up: the second merge says %s", said)
	}
}

func TestTheFirstMergeWeEverSeeJudgesItsOwnRangeAlone(t *testing.T) {
	root, _, after := mergedProject(t)

	wroteInto(t, filepath.Join(root, "src", "later.py"), sinOnLineFive)
	commitIn(t, root, "the work of a second merge")

	said := MergeLanded(mergeLine(root, "main", after, git.Head(root)))

	if !strings.Contains(said, "later.py") {
		t.Errorf("the first merge we ever see does not name its own file: %s", said)
	}

	if strings.Contains(said, "brought.py") {
		t.Errorf("with no mark at all the first merge judged beyond its own range, which could be a whole history: %s", said)
	}
}

func TestAMergeNeverReadsTheWorktreeFolder(t *testing.T) {
	root, before, after := mergedProject(t)

	gone := root + "-worker-7"
	if out, err := exec.Command("test", "-d", gone).CombinedOutput(); err == nil {
		t.Fatalf("the worktree folder of this test exists, and it must not: %s", out)
	}

	if said := MergeLanded(mergeLine(root, "main", before, after)); said == "" {
		t.Error("a merge whose worktree folder is already gone was answered with nothing, and the folder is a race we lose")
	}
}

func TestTheMergeKeyNeverRefuses(t *testing.T) {
	root, before, after := mergedProject(t)

	for _, told := range []struct {
		what string
		line map[string]any
	}{
		{"a merge that brought in sins", mergeLine(root, "main", before, after)},
		{"a merge with no range at all", mergeLine(root, "main", "", "")},
		{"a merge whose commits are the same", mergeLine(root, "main", after, after)},
		{"a merge of a branch nobody knows", mergeLine(root, "feature/nobody", before, after)},
	} {
		said := MergeLanded(told.line)

		if strings.Contains(said, `"refuse"`) {
			t.Errorf("%s was refused, and a refusal from a commit script is a fault, five of which switch the plugin off: %s",
				told.what, said)
		}

		if said != "" && !strings.Contains(said, `"whisper"`) {
			t.Errorf("%s was answered with something that is not a tell: %s", told.what, said)
		}
	}
}

func TestTheMarkOutlivesTheSession(t *testing.T) {
	root, before, after := mergedProject(t)

	if said := MergeLanded(mergeLine(root, "main", before, after)); said == "" {
		t.Fatal("the first merge said nothing, so there is no mark to outlive anything")
	}

	space := workspace.At(root, "")

	if kept := MergedIn(space).Upto("main"); kept != after {
		t.Fatalf("the mark says the branch is judged up to %q, and the merge ended at %s", kept, after)
	}

	if path := MergedIn(space).file.Path(); strings.Contains(path, "/sessions/") {
		t.Errorf("the mark lives in a session's own folder (%s), and a merge made between sessions is the case it exists for", path)
	}
}

func TestAMarkThatIsNotAnAncestorIsSaidAndNotJudgedFrom(t *testing.T) {
	root, before, after := mergedProject(t)

	if said := MergeLanded(mergeLine(root, "main", before, after)); said == "" {
		t.Fatal("the first merge said nothing")
	}

	resetTo(t, root, before)

	wroteInto(t, filepath.Join(root, "src", "rewritten.py"), sinOnLineFive)
	commitIn(t, root, "a history rewritten under us")

	now := git.Head(root)

	said := MergeLanded(mergeLine(root, "main", before, now))

	if !strings.Contains(said, "rewritten.py") {
		t.Errorf("the merge after a reset does not name its own file: %s", said)
	}

	if strings.Contains(said, "brought.py") {
		t.Errorf("the merge judged from a mark that is no ancestor of what the branch is now: %s", said)
	}
}

func TestAJudgingThatCouldNotRunLeavesTheMarkWhereItWas(t *testing.T) {
	root, before, after := mergedProject(t)

	space := workspace.At(root, "")

	wroteInto(t, filepath.Join(root, ".commandments", "config.json"), "{ this is no json at all")

	MergeLanded(mergeLine(root, "main", before, after))

	if kept := MergedIn(space).Upto("main"); kept != "" {
		t.Errorf("a judging that could not run advanced the mark to %q, so the next merge would skip this range", kept)
	}
}

func resetTo(t *testing.T, root, commit string) {
	t.Helper()

	if out, err := exec.Command("git", "-C", root, "reset", "-q", "--hard", commit).CombinedOutput(); err != nil {
		t.Fatalf("git reset: %s", out)
	}
}

const sinOnLineFive = `def total(order) -> int:
    return order.total


def refund(order) -> int:
    if order.late:
        raise ValueError(order)
    else:
        return order.total
`

package hooks

import (
	"os/exec"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/git"
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

	_ = before
}

func TestAMergeIsNotJudgedWhenTheSettingIsOff(t *testing.T) {
	root, before, after := mergedProject(t)

	settingsOf(t, map[string]any{mergesKey: "false"})

	if said := MergeLanded(mergeLine(root, "main", before, after)); said != "" {
		t.Errorf("a merge was judged although the setting is off: %s", said)
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

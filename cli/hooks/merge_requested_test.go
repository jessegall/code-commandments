package hooks

import (
	"encoding/json"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

func requestedLine(root, onto, tip string) map[string]any {
	return map[string]any{
		"v": 1, "event": requestedEvent, "agent": 1, "cwd": root, "window_ms": 60000,
		"data": map[string]any{
			"checkout": map[string]any{"project_root": root, "branch": "main"},
			"row": map[string]any{
				"worker_id": "7", "branch": "feature/worker-7", "branch_commit": tip,
				"target_branch": "main", "target_commit": onto, "folder": root,
			},
		},
	}
}

func guardAnswer(t *testing.T, said string) map[string]any {
	t.Helper()

	if said == "" {
		return nil
	}

	var held map[string]any
	if err := json.Unmarshal([]byte(said), &held); err != nil {
		t.Fatalf("the guard answered %q, which is not a line the journal can read: %v", said, err)
	}

	return held
}

func TestAMergeThatWouldBringInASinIsRefusedBeforeItLands(t *testing.T) {
	root, before, after := mergedProject(t)

	t.Setenv(journalSettings, `{"judge_workers": "true", "judge_before_merge": "true"}`)

	said := guardAnswer(t, MergeRequested(requestedLine(root, before, after)))

	if said == nil {
		t.Fatal("the guard allowed a merge that brings in a sin, so nothing of this feature works")
	}

	if said["refuse"] == nil {
		t.Errorf("the guard answered %v where it must refuse, since allowing is what lets the sin land", said)
	}

	if reason, _ := said["refuse"].(string); !strings.Contains(reason, ":") {
		t.Errorf("the refusal names no file and line, and the worker gets this as its whole job: %q", reason)
	}
}

func TestWithTheSettingOffAMergeIsNeverHeld(t *testing.T) {
	root, before, after := mergedProject(t)

	t.Setenv(journalSettings, `{"judge_before_merge": "false"}`)

	if said := MergeRequested(requestedLine(root, before, after)); said != "" {
		t.Errorf("the guard answered %q with the setting off: a merge must not be held by a feature nobody "+
			"switched on", said)
	}
}

func TestWithNoSettingsAtAllAMergeIsNeverHeld(t *testing.T) {
	root, before, after := mergedProject(t)

	t.Setenv(journalSettings, "")

	if said := MergeRequested(requestedLine(root, before, after)); said != "" {
		t.Errorf("the guard answered %q where no settings were given at all, so a consumer who never chose this "+
			"has its merges held", said)
	}
}

func TestAMergeWithNoRangeToJudgeIsAllowed(t *testing.T) {
	root, before, _ := mergedProject(t)

	t.Setenv(journalSettings, `{"judge_workers": "true", "judge_before_merge": "true"}`)

	if said := MergeRequested(requestedLine(root, before, before)); said != "" {
		t.Errorf("a merge whose two ends are the same commit was answered %q, where there is nothing to judge", said)
	}
}

func TestTheGuardReadsBothEndsOfTheRangeAndTheWorktree(t *testing.T) {
	root := t.TempDir()

	if err := os.MkdirAll(filepath.Join(root, "src"), 0o755); err != nil {
		t.Fatal(err)
	}

	asked := requestedOf(requestedLine(root, "aaaaaaa", "bbbbbbb"))

	if asked.onto != "aaaaaaa" || asked.tip != "bbbbbbb" {
		t.Errorf("the guard read the range as %s..%s", asked.onto, asked.tip)
	}

	if asked.folder != root {
		t.Errorf("the guard reads the range in %s rather than the worker's worktree", asked.folder)
	}

	if asked.target != "main" || asked.branch != "feature/worker-7" {
		t.Errorf("the guard names the branches as %s and %s", asked.target, asked.branch)
	}

	if !asked.readable() {
		t.Error("a row with both ends and a folder reads as unreadable")
	}

	if requestedOf(requestedLine(root, "aaaaaaa", "aaaaaaa")).readable() {
		t.Error("a row whose ends are the same commit reads as having a range")
	}
}

func TestEveryKeyOfTheGuardIsTheSameEventWhicheverSpellingArrives(t *testing.T) {
	for _, arriving := range []string{requestedEvent, "merge_request.create"} {
		if said := namedAsTheKey(arriving); said != requestedEvent {
			t.Errorf("the guard's key arriving as %q reads as %q", arriving, said)
		}
	}
}

func TestTheWorkerIsNamedInTheRefusalSoItKnowsTheWorkIsItsOwn(t *testing.T) {
	root, before, after := mergedProject(t)

	t.Setenv(journalSettings, `{"judge_workers": "true", "judge_before_merge": "true"}`)

	said := guardAnswer(t, MergeRequested(requestedLine(root, before, after)))
	if said == nil {
		t.Fatal("the guard allowed a merge that brings in a sin")
	}

	reason, _ := said["refuse"].(string)

	if !strings.Contains(reason, "7") {
		t.Errorf("the refusal does not name the worker whose work it is: %q", reason)
	}

	if !strings.Contains(reason, "brought.py") {
		t.Errorf("the refusal does not name the file the sin is in, and the worker has to find it: %q", reason)
	}
}

func TestAMergeOfWorkWithNoSinsIsAllowedSilently(t *testing.T) {
	root, _ := workerProject(t)

	before := gitHeadOf(t, root)

	wroteInto(t, filepath.Join(root, "src", "clean.py"), "def total(order) -> int:\n    return order.total\n")
	commitIn(t, root, "work with nothing wrong in it")

	after := gitHeadOf(t, root)

	t.Setenv(journalSettings, `{"judge_workers": "true", "judge_before_merge": "true"}`)

	if said := MergeRequested(requestedLine(root, before, after)); said != "" {
		t.Errorf("a merge of clean work was answered %q: it must pass without a word, or every merge carries "+
			"noise and the words stop meaning anything", said)
	}
}

func gitHeadOf(t *testing.T, root string) string {
	t.Helper()

	out, err := exec.Command("git", "-C", root, "rev-parse", "HEAD").CombinedOutput()
	if err != nil {
		t.Fatalf("git rev-parse: %s", out)
	}

	return strings.TrimSpace(string(out))
}

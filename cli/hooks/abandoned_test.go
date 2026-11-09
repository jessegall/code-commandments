package hooks

import (
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

func TestAJudgingForAClosedLaneIsAbandonedAndQueuedForNobody(t *testing.T) {
	root, data := workerProject(t)
	worktree := filepath.Join(root, ".journal", "worktrees", "development", "worker-2")

	if out, err := exec.Command("git", "-C", root, "worktree", "add", "-q", "-b", "feature/worker-2", worktree).CombinedOutput(); err != nil {
		t.Fatalf("git worktree add: %s", out)
	}

	file := filepath.Join(worktree, "src", "made.py")
	if err := os.WriteFile(file, []byte(olderSin), 0o644); err != nil {
		t.Fatal(err)
	}

	line := map[string]any{
		"v": 1, "event": "file_edited", "session": "s4", "environment": "main", "agent": 4,
		"cwd": worktree,
		"data": map[string]any{
			"cwd": worktree, "kind": "create", "path": filepath.Join("src", "made.py"),
			"checkout": map[string]any{"base_commit": "aaaaaaa"},
		},
	}

	moment, known := Moment4Of(line)
	if !known {
		t.Fatal("the journal's own edit event is not a moment at all")
	}

	given := Stamped(moment)
	if cut := MomentOf(given).Cut; cut != "aaaaaaa" {
		t.Fatalf("the moment says it was cut from %q", cut)
	}

	lane := checkoutOf(root, file)

	kept.CutAt(lane, "aaaaaaa")
	kept.CutAt(lane, "bbbbbbb")

	if cut := kept.CutOf(lane); cut != "bbbbbbb" {
		t.Fatalf("the lane says it was cut from %q, so there is no closed lane to abandon a judging for", cut)
	}

	t.Setenv(pluginData, data)

	if _, queued := Queue4In(data); !queued {
		t.Fatalf("the queue the journal drains is not named, so nothing of this test is being exercised")
	}

	before := AbandonedJudgings()

	adviseAsked(asked{given: given, queued: true, first: true})

	if AbandonedJudgings() != before+1 {
		t.Fatal("a judging for a lane that had closed was not abandoned")
	}

	if queued := drained(t, data); len(queued) > 0 {
		t.Fatalf("it was queued for somebody all the same: %v", queued)
	}

	if said := recordedLines(t, data); !strings.Contains(said, "abandoned, because this checkout was cut again") {
		t.Errorf("the record does not say the judging was abandoned: %s", said)
	}
}

func recordedLines(t *testing.T, data string) string {
	t.Helper()

	said, err := os.ReadFile(filepath.Join(data, "judgings.log"))
	if err != nil {
		return ""
	}

	return string(said)
}

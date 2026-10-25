package hooks

import (
	"encoding/json"
	"os"
	"os/exec"
	"path/filepath"
	"strings"
	"testing"
)

// mergingOf is the event the journal holds just before a worker's branch is taken in, as it really sends it: the
// event's own fields under `data`, with the window it waits for the answer in.
func mergingOf(worktree string, window int, files ...string) map[string]any {
	named := make([]any, 0, len(files))
	for _, file := range files {
		named = append(named, file)
	}

	return map[string]any{
		"event": "worker_merging",
		"data": map[string]any{
			"worker": "12", "name": "Lady Lovelace", "task": "46",
			"branch": "worker/12-Lady-Lovelace", "worktree": worktree, "files": named,
			"window_ms": float64(window),
		},
	}
}

// generousWindow is a window that affords the whole tree, and shallowWindow one that affords the changed files
// alone, both as the event names them in milliseconds.
const (
	generousWindow = 180_000
	shallowWindow  = 10_000
)

// TestASinfulBranchHoldsItsOwnMerge holds a worker's work to the disciplines before it lands: the files it changed
// are judged, and a sin in them refuses the merge with what it found, so the proofs and the task stay as they were.
func TestASinfulBranchHoldsItsOwnMerge(t *testing.T) {
	root, _, _ := projectWithASin(t)

	held := MergeHeld(mergingOf(root, generousWindow, "src/refund.py"))
	if held == "" {
		t.Fatal("a branch carrying a sin was let through")
	}

	var answer map[string]any
	if err := json.Unmarshal([]byte(held), &answer); err != nil {
		t.Fatalf("the hold answered %q: %v", held, err)
	}

	if answer["answer"] != "refuse" {
		t.Errorf("the hold answered %v, want a refusal", answer["answer"])
	}

	reason := asText(answer["reason"])
	if !strings.Contains(reason, "Lady Lovelace") || !strings.Contains(reason, "1 sin") {
		t.Errorf("the reason reads %q, naming neither the worker nor what it carries", reason)
	}

	if !strings.Contains(reason, "redundant-python-else") {
		t.Errorf("the reason reads %q, naming no sin a reader could look up", reason)
	}
}

// TestAMergeIsHeldWhereverTheEventPutsItsFields holds the reading of the event itself: the journal sends an
// event's own fields under `data`, and a line that carries them at its top level, as a journal that knew no `data`
// sent them, is read the same way. A hold that reads only one of the two judges nothing at all.
func TestAMergeIsHeldWhereverTheEventPutsItsFields(t *testing.T) {
	root, _, _ := projectWithASin(t)

	flat := map[string]any{
		"event": "worker_merging", "worker": "12", "name": "Lady Lovelace", "task": "46",
		"branch": "worker/12-Lady-Lovelace", "worktree": root, "files": []any{"src/refund.py"},
		"window_ms": float64(generousWindow),
	}

	if MergeHeld(flat) == "" {
		t.Error("an event carrying its fields at its top level was not judged")
	}
}

// TestACleanBranchMergesUnheld holds the gate to sins alone: a branch whose changed files are clean answers
// nothing, which the journal reads as allow, and a file the project does not judge is not judged here either.
func TestACleanBranchMergesUnheld(t *testing.T) {
	root, file, _ := projectWithASin(t)

	righteous := "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    return order.total\n"
	if err := os.WriteFile(file, []byte(righteous), 0o644); err != nil {
		t.Fatal(err)
	}

	if held := MergeHeld(mergingOf(root, generousWindow, "src/refund.py")); held != "" {
		t.Errorf("a clean branch was held with %q", held)
	}

	notes := filepath.Join(root, "NOTES.md")
	if err := os.WriteFile(notes, []byte("# notes\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	if held := MergeHeld(mergingOf(root, generousWindow, "NOTES.md")); held != "" {
		t.Errorf("a branch of files the project does not judge was held with %q", held)
	}

	if held := MergeHeld(mergingOf("", generousWindow, "src/refund.py")); held != "" {
		t.Errorf("an event naming no worktree was answered with %q, and there is nothing it could have judged", held)
	}
}

// TestAWindowTooSmallToJudgeInJudgesNothing holds the one thing a hold may never do: answer late. The journal cuts
// a hold off at its window and records the fault, and a faulting plugin is switched off, so a window no judge fits
// in is answered with nothing — the branch is still there to judge, and the plugin lives to judge it.
func TestAWindowTooSmallToJudgeInJudgesNothing(t *testing.T) {
	root, _, _ := projectWithASin(t)

	for name, window := range map[string]int{"a window the journal does not name": 0, "the 50 ms a journal holds by default": 50} {
		t.Run(name, func(t *testing.T) {
			if held := MergeHeld(mergingOf(root, window, "src/refund.py")); held != "" {
				t.Errorf("a merge the journal gave no time to judge was answered with %q", held)
			}
		})
	}
}

// TestAShallowWindowStillJudgesTheChangedFiles holds the middle depth: a window of seconds is too short to read a
// whole tree, and the files the worker changed are still read on their own by the rules that need no more.
func TestAShallowWindowStillJudgesTheChangedFiles(t *testing.T) {
	root, _, _ := projectWithASin(t)

	if held := MergeHeld(mergingOf(root, shallowWindow, "src/refund.py")); held == "" {
		t.Error("a branch carrying a sin was let through on a window that affords its changed files")
	}
}

// TestAWorkerAnswersForItsOwnLinesOnly holds a worker to its own work: the whole worktree is read, so a rule that
// spans files has its context, and a sin the branch never wrote is the project's to fix, not this merge's to hold.
// The base is the branch the environment is on, which the main checkout answers for where the event names none.
func TestAWorkerAnswersForItsOwnLinesOnly(t *testing.T) {
	sinful := "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    else:\n        return order.total\n"
	worktree := workerBranchOf(t, map[string]string{
		".commandments/config.json": `{"paths": ["src"]}`,
		"src/refund.py":             sinful,
	}, map[string]string{
		"src/ship.py": "def ship(order) -> str:\n    return order.carrier\n",
	})

	if held := MergeHeld(mergingOf(worktree, generousWindow)); held != "" {
		t.Errorf("a worker was held for a sin its branch never wrote: %q", held)
	}

	if err := os.WriteFile(filepath.Join(worktree, "src", "collect.py"), []byte(sinful), 0o644); err != nil {
		t.Fatal(err)
	}

	if held := MergeHeld(mergingOf(worktree, generousWindow)); held == "" {
		t.Error("a sin the worker's own branch wrote was let through")
	}
}

// workerBranchOf is a project whose base branch holds the first files and a worker's worktree of it, on a branch
// of its own, that committed the second: the shape a merge is really judged in, with the main checkout left on the
// base branch, which is what the hold falls back to when the event names no base.
func workerBranchOf(t *testing.T, base, branch map[string]string) string {
	t.Helper()
	root, worktree := tempFolder(t), filepath.Join(tempFolder(t), "worker-12")
	t.Setenv("JOURNAL_ROOT", root)

	gitIn(t, root, "init", "-q", "-b", "trunk")
	gitIn(t, root, "config", "user.email", "worker@example.com")
	gitIn(t, root, "config", "user.name", "Lady Lovelace")
	wrote(t, root, base)
	gitIn(t, root, "add", "-A")
	gitIn(t, root, "commit", "-q", "-m", "the project as it stands")

	gitIn(t, root, "worktree", "add", "-q", "-b", "worker/12-Lady-Lovelace", worktree)
	wrote(t, worktree, branch)
	gitIn(t, worktree, "add", "-A")
	gitIn(t, worktree, "commit", "-q", "-m", "what the worker wrote")

	return worktree
}

// tempFolder is a folder of this test with its symlinks resolved, as every path the scan answers with is.
func tempFolder(t *testing.T) string {
	t.Helper()
	folder, err := filepath.EvalSymlinks(t.TempDir())
	if err != nil {
		t.Fatal(err)
	}

	return folder
}

func gitIn(t *testing.T, root string, args ...string) {
	t.Helper()
	if out, err := exec.Command("git", append([]string{"-C", root}, args...)...).CombinedOutput(); err != nil {
		t.Fatalf("git %s: %s", strings.Join(args, " "), out)
	}
}

func wrote(t *testing.T, root string, files map[string]string) {
	t.Helper()
	for path, text := range files {
		if err := os.MkdirAll(filepath.Dir(filepath.Join(root, path)), 0o755); err != nil {
			t.Fatal(err)
		}
		if err := os.WriteFile(filepath.Join(root, path), []byte(text), 0o644); err != nil {
			t.Fatal(err)
		}
	}
}

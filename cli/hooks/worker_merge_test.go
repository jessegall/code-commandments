package hooks

import (
	"encoding/json"
	"os"
	"os/exec"
	"path/filepath"
	"strconv"
	"strings"
	"testing"
	"time"
)

// MergeLanded is a landed merge's answer as a queued run tells it.
func MergeLanded(line map[string]any) string {
	said := landedSaid(line)
	if said == "" {
		return ""
	}

	return (JournalAnswer{Whisper: &said}).Line()
}

// heldAnswer is what a hand-in's judging came to, as the line that would have held it: a refusal naming the sins, a
// note when it was not judged in full, or nothing when it is clean.
func heldAnswer(line map[string]any) string {
	return handInAnswer(mergeOf(line))
}

func handInAnswer(work merging) string {
	began := time.Now()
	found := work.sins()
	work.records(found.reason(), len(found.marks), "waited "+strconv.FormatInt(time.Since(began).Milliseconds(), 10)+"ms", found.times)
	if found.got == gotWhole && len(found.marks) == 0 {
		return ""
	}
	refusal := mergeTold(work.worker, "", work.worktree, found.marks)
	if found.got == gotWhole {
		work.keeps(refusal)

		return (JournalAnswer{Refuse: &refusal}).Line()
	}
	said := Unjudged(work.worker+"'s hand-in was not judged in full", string(found.got), "")
	work.keeps(said)
	if len(found.marks) == 0 {
		return (JournalAnswer{Whisper: &said}).Line()
	}

	return (JournalAnswer{Refuse: &refusal, Whisper: &said}).Line()
}

func mergingOf(worktree string, files ...string) map[string]any {
	named := make([]any, 0, len(files))
	for _, file := range files {
		named = append(named, file)
	}

	return map[string]any{
		"event": "worker_merging",
		"data": map[string]any{
			"worker": "12", "name": "Lady Lovelace", "task": "46",
			"branch": "worker/12-Lady-Lovelace", "worktree": worktree, "files": named,
		},
	}
}

func TestASinfulBranchHoldsItsOwnMerge(t *testing.T) {
	root, _, _ := projectWithASin(t)

	held := heldAnswer(mergingOf(root, "src/refund.py"))
	if held == "" {
		t.Fatal("a branch carrying a sin was let through")
	}

	var answer map[string]any
	if err := json.Unmarshal([]byte(held), &answer); err != nil {
		t.Fatalf("the hold answered %q: %v", held, err)
	}

	if answer["refuse"] == nil {
		t.Errorf("the hold answered %v, want a refusal", answer)
	}

	reason := asText(answer["refuse"])
	if !strings.Contains(reason, "Lady Lovelace") || !strings.Contains(reason, "1 sin") {
		t.Errorf("the reason reads %q, naming neither the worker nor what it carries", reason)
	}

	if !strings.Contains(reason, "redundant-python-else") {
		t.Errorf("the reason reads %q, naming no sin a reader could look up", reason)
	}
}

func TestAMergeIsHeldWhereverTheEventPutsItsFields(t *testing.T) {
	root, _, _ := projectWithASin(t)

	flat := map[string]any{
		"event": "worker_merging", "worker": "12", "name": "Lady Lovelace", "task": "46",
		"branch": "worker/12-Lady-Lovelace", "worktree": root, "files": []any{"src/refund.py"},
	}

	if heldAnswer(flat) == "" {
		t.Error("an event carrying its fields at its top level was not judged")
	}
}

func TestACleanBranchMergesUnheld(t *testing.T) {
	root, file, _ := projectWithASin(t)

	righteous := "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    return order.total\n"
	if err := os.WriteFile(file, []byte(righteous), 0o644); err != nil {
		t.Fatal(err)
	}

	if held := heldAnswer(mergingOf(root, "src/refund.py")); refusedBy(held) {
		t.Errorf("a clean branch was held with %q", held)
	}

	notes := filepath.Join(root, "NOTES.md")
	if err := os.WriteFile(notes, []byte("# notes\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	if held := heldAnswer(mergingOf(root, "NOTES.md")); held != "" {
		t.Errorf("a branch of files the project does not judge was held with %q", held)
	}

	if held := heldAnswer(mergingOf("", "src/refund.py")); held != "" {
		t.Errorf("an event naming no worktree was answered with %q, and there is nothing it could have judged", held)
	}
}

func TestAShallowWindowStillJudgesTheChangedFiles(t *testing.T) {
	root, _, _ := projectWithASin(t)

	if held := heldAnswer(mergingOf(root, "src/refund.py")); held == "" {
		t.Error("a branch carrying a sin was let through on a window that affords its changed files")
	}
}

func TestAWorkerAnswersForItsOwnLinesOnly(t *testing.T) {
	sinful := "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    else:\n        return order.total\n"
	worktree := workerBranchOf(t, map[string]string{
		".commandments/config.json": `{"paths": ["src"]}`,
		"src/refund.py":             sinful,
	}, map[string]string{
		"src/ship.py": "def ship(order) -> str:\n    return order.carrier\n",
	})

	if held := heldAnswer(mergingOf(worktree)); refusedBy(held) {
		t.Errorf("a worker was held for a sin its branch never wrote: %q", held)
	}

	if err := os.WriteFile(filepath.Join(worktree, "src", "collect.py"), []byte(sinful), 0o644); err != nil {
		t.Fatal(err)
	}

	if held := heldAnswer(mergingOf(worktree)); held == "" {
		t.Error("a sin the worker's own branch wrote was let through")
	}
}

func workerBranchOf(t *testing.T, base, branch map[string]string) string {
	t.Helper()
	root := tempFolder(t)
	worktree := filepath.Join(root, ".journal", "worktrees", "12-Lady-Lovelace")
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

func deliveringOf(worktree string, files ...string) map[string]any {
	held := mergingOf(worktree, files...)
	held["event"] = "worker_delivering"

	return held
}

func TestAWorkerIsHeldBeforeItsWorkReachesTheAgent(t *testing.T) {
	root, _, _ := projectWithASin(t)

	held := heldAnswer(deliveringOf(root, "src/refund.py"))
	if held == "" {
		t.Fatal("a worker carrying a sin delivered its work unheld")
	}

	var answer map[string]any
	if err := json.Unmarshal([]byte(held), &answer); err != nil {
		t.Fatalf("the hold answered %q: %v", held, err)
	}

	if answer["refuse"] == nil {
		t.Errorf("the hold answered %v, want a refusal", answer)
	}
	if reason := asText(answer["refuse"]); !strings.Contains(reason, "Lady Lovelace") || !strings.Contains(reason, "1 sin") {
		t.Errorf("the reason reads %q, naming neither the worker nor what it carries", reason)
	}
}

func TestACleanWorkerDeliversUnheld(t *testing.T) {
	worktree := workerBranchOf(t, map[string]string{
		".commandments/config.json": `{"paths": ["src"]}`,
		"src/refund.py":             "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    else:\n        return order.total\n",
	}, map[string]string{
		"src/ship.py": "def ship(order) -> str:\n    return order.carrier\n",
	})

	if held := heldAnswer(deliveringOf(worktree)); refusedBy(held) {
		t.Errorf("a worker was held on delivery for a sin its branch never wrote: %q", held)
	}
}

func TestAWorkersEditIsJudgedInItsOwnWorktree(t *testing.T) {
	worktree := workerBranchOf(t, map[string]string{
		".commandments/config.json": `{"paths": ["src"]}`,
		"src/ship.py":               "def ship(order) -> str:\n    return order.carrier\n",
	}, map[string]string{
		"src/refund.py": "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    else:\n        return order.total\n",
	})

	moment, known := Moment4Of(map[string]any{
		"v": 1, "event": "file_edited", "session": "worker-1", "environment": "worker",
		"data": map[string]any{"path": filepath.Join(worktree, "src", "refund.py")},
	})
	if !known {
		t.Fatal("a file_edited event is no moment the plugin reads")
	}

	agent, _ := moment["agent"].(map[string]any)
	if asText(agent["cwd"]) != worktree {
		t.Errorf("the edit is judged in %q, not in the worktree the file sits in", asText(agent["cwd"]))
	}
}

func refusedBy(held string) bool {
	var answer map[string]any
	if json.Unmarshal([]byte(held), &answer) != nil {
		return false
	}

	return answer["refuse"] != nil
}

func TestAMergeRequestIsTheSameEventWhicheverSpellingArrives(t *testing.T) {
	for _, arriving := range []string{requestedEvent, "merge_request.create"} {
		if said := namedAsTheKey(arriving); said != requestedEvent {
			t.Errorf("a merge request arriving as %q reads as %q", arriving, said)
		}
	}
}

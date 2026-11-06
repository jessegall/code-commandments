package hooks

import (
	"encoding/json"
	"os"
	"os/exec"
	"path/filepath"
	"slices"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/config"
)

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

const (
	generousWindow = 180_000
	shallowWindow  = 10_000
)

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

func TestAShallowWindowStillJudgesTheChangedFiles(t *testing.T) {
	root, _, _ := projectWithASin(t)

	if held := MergeHeld(mergingOf(root, shallowWindow, "src/refund.py")); held == "" {
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

func TestTheJudgeIsGivenLessThanTheWholeWindow(t *testing.T) {
	for window, want := range map[time.Duration]time.Duration{
		180 * time.Second: 162 * time.Second,
		10 * time.Second:  9 * time.Second,
		time.Second:       800 * time.Millisecond,
	} {
		if given := (merging{window: window}).deadline(); given != want {
			t.Errorf("a window of %s gives the judge %s, want %s", window, given, want)
		}
	}

	if given := (merging{}).deadline(); given > 0 {
		t.Errorf("a window the journal never named gives the judge %s, and there is nothing to judge in it", given)
	}
}

func TestAMergeReadsTheFoldersTheProjectDeclares(t *testing.T) {
	worktree := tempFolder(t)
	held := merging{worktree: worktree}

	declared := held.roots(config.Config{Paths: []string{"src", "bridge/php/src"}})
	want := []string{filepath.Join(worktree, "src"), filepath.Join(worktree, "bridge/php/src")}
	if !slices.Equal(declared, want) {
		t.Errorf("the hold reads %v, not the folders the project declares", declared)
	}
	if slices.Contains(declared, worktree) {
		t.Error("the hold reads the worktree's own root, where the libraries are linked in")
	}

	if whole := held.roots(config.Config{}); !slices.Equal(whole, []string{worktree}) {
		t.Errorf("a project declaring no folder is read as %v, not as the worktree itself", whole)
	}
}

func deliveringOf(worktree string, window int, files ...string) map[string]any {
	held := mergingOf(worktree, window, files...)
	held["event"] = "worker_delivering"

	return held
}

func TestAWorkerIsHeldBeforeItsWorkReachesTheAgent(t *testing.T) {
	root, _, _ := projectWithASin(t)

	held := DeliveryHeld(deliveringOf(root, generousWindow, "src/refund.py"))
	if held == "" {
		t.Fatal("a worker carrying a sin delivered its work unheld")
	}

	var answer map[string]any
	if err := json.Unmarshal([]byte(held), &answer); err != nil {
		t.Fatalf("the hold answered %q: %v", held, err)
	}
	if answer["answer"] != "refuse" {
		t.Errorf("the hold answered %v, want a refusal", answer["answer"])
	}
	if reason := asText(answer["reason"]); !strings.Contains(reason, "Lady Lovelace") || !strings.Contains(reason, "1 sin") {
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

	if held := DeliveryHeld(deliveringOf(worktree, generousWindow)); held != "" {
		t.Errorf("a worker was held on delivery for a sin its branch never wrote: %q", held)
	}
}

func TestBothMomentsReadTheWorkTheSameWay(t *testing.T) {
	root, _, _ := projectWithASin(t)

	delivery := DeliveryHeld(deliveringOf(root, generousWindow, "src/refund.py"))
	merge := MergeHeld(mergingOf(root, generousWindow, "src/refund.py"))

	if delivery != merge {
		t.Errorf("the delivery answered %q and the merge %q", delivery, merge)
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

func TestAGateThatRunsOutOfTimeAnswersWithWhatItDidFind(t *testing.T) {
	root, _, _ := projectWithASin(t)

	held := MergeHeld(mergingOf(root, int(wholeTreeWindow/time.Millisecond)+1, "src/refund.py"))

	if held == "" {
		t.Error("a gate whose deep judging cannot fit its window answered with nothing, which reads exactly as " +
			"clean: the worker's work goes through unjudged and nobody is told the judging did not finish")
	}
}

func TestAGateThatRunsOutOfTimeAnswersWithWhatItFound(t *testing.T) {
	root, _, _ := projectWithASin(t)

	held := MergeHeld(mergingOf(root, int(wholeTreeWindow/time.Millisecond), "src/refund.py"))

	if held == "" {
		t.Error("a gate whose deep judging cannot fit its window answered with nothing, which reads exactly as " +
			"clean: the worker's work goes through unjudged and nobody is told the judging did not finish")
	}

	if said := MergeHeld(mergingOf(root, 50, "src/refund.py")); said != "" {
		t.Errorf("a gate given 50ms answered %q; there is no honest answer in that time", said)
	}
}

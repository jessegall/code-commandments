package hooks

import (
	"encoding/json"
	"os"
	"path/filepath"
	"strings"
	"testing"
)

// mergingOf is the event the journal holds just before a worker's branch is taken in, naming the files it changed
// as paths inside its worktree.
func mergingOf(worktree string, files ...string) map[string]any {
	named := make([]any, 0, len(files))
	for _, file := range files {
		named = append(named, file)
	}

	return map[string]any{
		"event": "worker_merging", "worker": "12", "name": "Lady Lovelace", "task": "46",
		"branch": "worker/12-Lady-Lovelace", "worktree": worktree, "files": named,
	}
}

// TestASinfulBranchHoldsItsOwnMerge holds a worker's work to the disciplines before it lands: the files it changed
// are judged, and a sin in them refuses the merge with what it found, so the proofs and the task stay as they were.
func TestASinfulBranchHoldsItsOwnMerge(t *testing.T) {
	root, _, _ := projectWithASin(t)

	held := MergeHeld(mergingOf(root, "src/refund.py"))
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

// TestACleanBranchMergesUnheld holds the gate to sins alone: a branch whose changed files are clean answers
// nothing, which the journal reads as allow, and a file the project does not judge is not judged here either.
func TestACleanBranchMergesUnheld(t *testing.T) {
	root, file, _ := projectWithASin(t)

	righteous := "def refund(order) -> int:\n    if order.late:\n        raise ValueError(order)\n    return order.total\n"
	if err := os.WriteFile(file, []byte(righteous), 0o644); err != nil {
		t.Fatal(err)
	}

	if held := MergeHeld(mergingOf(root, "src/refund.py")); held != "" {
		t.Errorf("a clean branch was held with %q", held)
	}

	notes := filepath.Join(root, "NOTES.md")
	if err := os.WriteFile(notes, []byte("# notes\n"), 0o644); err != nil {
		t.Fatal(err)
	}

	if held := MergeHeld(mergingOf(root, "NOTES.md")); held != "" {
		t.Errorf("a branch of files the project does not judge was held with %q", held)
	}

	if held := MergeHeld(map[string]any{"event": "worker_merging", "files": []any{"src/refund.py"}}); held != "" {
		t.Errorf("an event naming no worktree was answered with %q, and there is nothing it could have judged", held)
	}
}

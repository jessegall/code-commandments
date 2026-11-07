package hooks

import (
	"os"
	"path/filepath"
	"strings"
	"testing"
)

func TestAJudgedMergeReachesTheWorkerAndNotTheAgent(t *testing.T) {
	root, before, after := mergedProject(t)
	folder := journalThatSays(t, 0, "sent")

	said := MergeLanded(mergeLine(root, "main", before, after))

	asked, err := os.ReadFile(filepath.Join(folder, "asked"))
	if err != nil {
		t.Fatalf("the journal was never asked to send the worker anything: %v", err)
	}

	if !strings.Contains(string(asked), "worker send worker-7") {
		t.Errorf("the journal was asked %q, which does not send worker-7 its own merge", string(asked))
	}

	if said != "" {
		t.Errorf("the main agent was told as well, so the same merge is judged at it twice: %s", said)
	}
}

func TestAJudgedMergeFallsBackToTheAgentWhenTheWorkerIsGone(t *testing.T) {
	root, before, after := mergedProject(t)
	journalThatSays(t, 1, "worker worker-7 has no worktree")

	said := MergeLanded(mergeLine(root, "main", before, after))

	if said == "" {
		t.Fatal("the worker could not be told and nobody else was either, so the findings of a merge are lost")
	}

	if !strings.Contains(said, "no worktree") {
		t.Errorf("the agent is not told why it is hearing this: %s", said)
	}

	if !strings.Contains(said, "worker worker-7") {
		t.Errorf("the agent is not told whose work this was: %s", said)
	}
}

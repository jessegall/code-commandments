package hooks

import (
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/workspace"
)

func TestAGateWithNoAgentToTellKeepsItsWordsWhereAPersonReadsThem(t *testing.T) {
	root, _, _ := projectWithASin(t)

	said := workHeld(map[string]any{
		"event": deliveringEvent,
		"data":  map[string]any{"worktree": root, "name": "worker 7", "files": []any{"src/refund.py"}},
	})

	if said == "" {
		t.Fatal("a hand-in nobody could judge was answered with silence")
	}

	found := MergesIn(workspace.At(root, "")).Found()
	if len(found) == 0 {
		t.Fatal("the gate's words were kept nowhere, and the journal drops a line it has no agent to tell")
	}

	last := found[len(found)-1]

	if !strings.Contains(last, "was not judged in full") {
		t.Errorf("what is kept does not say the hand-in was not judged: %s", last)
	}

	if strings.Contains(last, "--changes") {
		t.Errorf("what is kept tells a worker to run the judge, and only the journal judges a worker's work: %s", last)
	}
}

func TestTheLanesVerbReadsTheKeptWordsBack(t *testing.T) {
	root, _ := workerProject(t)

	if err := MergesIn(workspace.At(root, "")).Keeps("main", "aaaaaaa", "Code Commandments: worker 7 carries 2 sins"); err != nil {
		t.Fatal(err)
	}

	said := mergesSaidIn(root)

	if !strings.Contains(said, "worker 7 carries 2 sins") {
		t.Errorf("the lanes verb does not read back what was kept: %s", said)
	}

	if !strings.Contains(said, "no agent has nobody to tell") {
		t.Errorf("the lanes verb does not say why anything is kept at all: %s", said)
	}
}

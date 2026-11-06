package hooks

import (
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/workspace"
)

func TestAGateWithNoAgentToTellKeepsItsWordsWhereAPersonReadsThem(t *testing.T) {
	root, _ := workerProject(t)

	said := workHeld(map[string]any{
		"event": deliveringEvent,
		"data": map[string]any{
			"worktree": root, "name": "worker 7", "window_ms": float64(time.Nanosecond / time.Millisecond),
		},
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

	if !strings.Contains(last, "Run `") {
		t.Errorf("what is kept does not say how to judge it properly: %s", last)
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

package hooks

import (
	"errors"
	"path/filepath"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/config"
	"github.com/jessegall/code-commandments/cli/dashboard"
	"github.com/jessegall/code-commandments/cli/workspace"
	"github.com/jessegall/code-commandments/engine"
)

func TestAHandInNobodyCouldJudgeIsVisibleAfterwards(t *testing.T) {
	forgetBookkeeping(t)

	root, data := workerProject(t)
	t.Setenv(pluginData, data)

	wroteInto(t, filepath.Join(root, "src", "slow.py"), olderSin)
	reading := readWhole
	readWhole = func(string, config.Config, []string) (*engine.Codebase, error) {
		return nil, errors.New("no bridge answered")
	}
	t.Cleanup(func() { readWhole = reading })

	said := heldAnswer(map[string]any{
		"event": deliveringEvent, "environment": "main", "agent": 7,
		"data": map[string]any{
			"worktree": root, "name": "worker 7",
		},
	})

	if said == "" {
		t.Fatal("the hand-in was answered with silence")
	}

	space := workspace.At(root, "")

	if !strings.Contains(said, "was not judged in full") {
		t.Errorf("the words the worker is told do not say it: %s", said)
	}

	held := dashboard.Unjudgeable(space)
	if len(held) == 0 {
		t.Error("the dashboard has no row for a hand-in nobody could judge")
	} else if !strings.Contains(held[len(held)-1], "was not judged in full") {
		t.Errorf("the dashboard's row does not say what happened: %s", held[len(held)-1])
	}

	if kept := MergesIn(space).Found(); len(kept) == 0 {
		t.Error("nothing of it is kept where a person reads it back")
	}

	if line := recordedLines(t, data); !strings.Contains(line, "the hand-in") {
		t.Errorf("the record has no line for it: %s", line)
	}
}

func TestOnlyTheLastFewUnjudgeableHandInsAreKept(t *testing.T) {
	root, _ := workerProject(t)
	space := workspace.At(root, "")

	for each := 0; each < mergesKept+4; each++ {
		held := dashboard.Unjudgeable(space)
		if len(held) >= mergesKept {
			held = held[len(held)-mergesKept+1:]
		}

		if err := dashboard.KeepsUnjudgeable(space, append(held, "a hand-in nobody could judge")); err != nil {
			t.Fatal(err)
		}
	}

	if held := dashboard.Unjudgeable(space); len(held) > mergesKept {
		t.Errorf("%d unjudgeable hand-ins are kept, and the file holds the last %d", len(held), mergesKept)
	}
}

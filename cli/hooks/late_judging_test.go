package hooks

import (
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/workspace"
)

func TestAJudgingThatOutlivedItsWindowStillTellsWhatItFound(t *testing.T) {
	root, data := workerProject(t)
	t.Setenv(pluginData, data)

	wroteInto(t, filepath.Join(root, "src", "slow.py"), olderSin)

	work := merging{
		worker: "worker 7", worktree: root, window: time.Millisecond,
		env: "main", agent: 7,
	}

	found := work.sinsWithin()

	if found.got == gotWhole {
		t.Skip("the judging finished inside a millisecond on this machine, so nothing outlived its window")
	}

	for until := time.Now().Add(30 * time.Second); time.Now().Before(until); time.Sleep(50 * time.Millisecond) {
		if len(drained(t, data)) > 0 {
			return
		}

		if kept := MergesIn(workspace.At(root, "")).Found(); len(kept) > 0 &&
			strings.Contains(kept[len(kept)-1], "judged after the journal stopped waiting") {
			return
		}
	}

	t.Error("the judging outlived its window and what it found reached nobody, neither the queue nor what a person reads back")
}

func TestALateJudgingSaysItWasLate(t *testing.T) {
	root, data := workerProject(t)
	t.Setenv(pluginData, data)

	wroteInto(t, filepath.Join(root, "src", "slow.py"), olderSin)

	answered := make(chan []SinMark, 1)
	answered <- []SinMark{}
	close(answered)

	merging{worker: "worker 7", worktree: root, env: "main", agent: 7}.tellsLater(answered)

	if queued := drained(t, data); len(queued) > 0 {
		t.Errorf("a late judging that found nothing told somebody anyway: %v", queued)
	}
}

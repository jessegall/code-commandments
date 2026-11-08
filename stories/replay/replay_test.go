//go:build story

package replay

import (
	"errors"
	"os"
	"testing"

	"github.com/jessegall/code-commandments/cli/ledger"
	"github.com/jessegall/code-commandments/story"
)

func recorded(t *testing.T, name string) story.Recording {
	t.Helper()

	journaled, err := os.ReadFile("recordings/" + name + ".tsv")
	if errors.Is(err, os.ErrNotExist) {
		t.Skip("the recording " + name + " is taken from a private project and kept only on the machine that took it")
	}

	if err != nil {
		t.Fatal(err)
	}

	return story.Recorded("/Users/jessegall/projects/agent-journal", string(journaled))
}

func TestAgentJournalsSixWorkersFromMidnightOnNovember8AreJudgedFromTheTreeTheirBranchHolds(t *testing.T) {
	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())

	judgings := s.Replays(recorded(t, "agent-journal-2026-11-08"))

	s.CostIs(ledger.TreeReadsStarted, 0)
	s.CostIs(ledger.JudgingsAnsweredSmaller, 0)
	s.CostIs(ledger.JudgingsDeclined, 0)
	s.CostAtLeast(ledger.JudgingsFromTheTree, judgings)
}

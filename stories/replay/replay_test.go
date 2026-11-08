//go:build story

package replay

import (
	"os"
	"testing"

	"github.com/jessegall/code-commandments/cli/ledger"
	"github.com/jessegall/code-commandments/story"
)

func recorded(t *testing.T, name string, workers ...string) story.Recording {
	t.Helper()

	events, err := os.ReadFile("testdata/" + name + ".ledger")
	if err != nil {
		t.Fatal(err)
	}

	commits := map[string]string{}
	for _, worker := range workers {
		log, err := os.ReadFile("testdata/" + name + "." + worker + ".log")
		if err != nil {
			t.Fatal(err)
		}

		commits[worker] = string(log)
	}

	return story.Recorded("/Users/jessegall/projects/agent-journal", string(events), commits)
}

func TestAgentJournalsTwoWorkersOnTheNightOfNovember8AreJudgedFromTheTreeTheirBranchHolds(t *testing.T) {
	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())

	judgings := s.Replays(recorded(t, "agent-journal-2026-11-08", "worker-699", "worker-707"))

	s.CostIs(ledger.TreeReadsStarted, 0)
	s.CostIs(ledger.JudgingsAnsweredSmaller, 0)
	s.CostIs(ledger.JudgingsDeclined, 0)
	s.CostAtLeast(ledger.JudgingsFromTheTree, judgings)
}

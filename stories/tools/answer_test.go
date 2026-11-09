//go:build story

package tools

import (
	"testing"

	"github.com/jessegall/code-commandments/cli/ledger"
	"github.com/jessegall/code-commandments/story"
)

func TestAnAnswerNamesWholePathsAndItsProgressIsRead(t *testing.T) {
	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())
	s.Writes(story.ElseAfterRaise, "whole.py")

	judged := s.AsksIn(s.TestCheckout(), "sins", "--changes")

	s.Exits(judged, 1)
	s.NamesOnlyWholePaths(judged, s.TestCheckout())

	if s.DetectorsCounted(judged) == 0 {
		s.Fails("the answer of the sins noun carries no 'detector a/b' progress line:\n" + judged.Text())
	}
}

func TestTheMemoryCountersMoveWithAJudging(t *testing.T) {
	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())
	s.Writes(story.ElseAfterRaise, "twice.py")

	s.Exits(s.AsksIn(s.TestCheckout(), "sins", "--changes", "--sin=redundant-python-else"), 1)
	s.CostAtLeast(ledger.DetectorFilesJudged, 1)

	s.Exits(s.AsksIn(s.TestCheckout(), "sins", "--changes", "--sin=redundant-python-else"), 1)
	s.CostAtLeast(ledger.DetectorFilesRemembered, 1)
}

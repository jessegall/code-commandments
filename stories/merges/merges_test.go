//go:build story

package merges

import (
	"testing"

	"github.com/jessegall/code-commandments/cli/ledger"
	"github.com/jessegall/code-commandments/story"
)

func ready(t *testing.T) (*story.Story, *story.Agent, *story.Agent) {
	t.Helper()

	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())

	return s, s.Main("Ada"), s.Worker("Grace")
}

func TestACleanMergeIsAllowedWithoutAWord(t *testing.T) {
	s, _, grace := ready(t)

	grace.Edits(story.Clean, "total.py")
	grace.HandsIn()

	merged := s.Merges(grace)

	s.Exits(merged, 0)
	s.Never(merged, "carries")
}

func TestAMergeOfSinfulWorkIsNotHeldWhenTheBeforeLandingSettingIsOff(t *testing.T) {
	s, _, grace := ready(t)

	grace.Edits(story.ElseAfterRaise, "order.py")
	grace.HandsIn()

	merged := s.Merges(grace)

	s.Exits(merged, 0)
	s.Never(merged, "carries")
}

func TestAMergeWithNothingToCompareIsAllowedWithoutJudging(t *testing.T) {
	s, _, grace := ready(t)

	merged := s.Merges(grace)

	s.Never(merged, "carries")
	s.CostIs(ledger.FilesReadIntoTrees, 0)
}

func TestAfterAMergeLandsTheWorkerThatWroteTheSinIsToldAndTheMainAgentIsNot(t *testing.T) {
	s, ada, grace := ready(t)

	grace.Edits(story.ElseAfterRaise, "order.py")
	grace.HandsIn()
	s.Merges(grace)

	grace.Hears("redundant-python-else")
	ada.StaysUntold("redundant-python-else")
}

func TestAWorkerThatIsGoneCannotBeToldSoTheMainAgentIs(t *testing.T) {
	s, ada, grace := ready(t)

	grace.Edits(story.SwallowedException, "plugins.py")
	grace.HandsIn()
	grace.Ends()
	s.Merges(grace)

	told := ada.Hears("python-swallowed-exception")

	s.Says(told, "could not be told")
}

func TestACleanLandingSaysNothingAndSendsNothing(t *testing.T) {
	s, ada, grace := ready(t)

	grace.Edits(story.ElseAfterRaiseFixed, "refund.py")
	grace.HandsIn()
	s.Merges(grace)

	grace.StaysUntold("sin")
	ada.StaysUntold("sin")
}

func TestTwoMergesCloseTogetherJudgeTheWholeRangeOnce(t *testing.T) {
	s, _, grace := ready(t)
	edsger := s.Worker("Edsger")

	grace.Edits(story.ElseAfterRaise, "first.py")
	edsger.Edits(story.SwallowedException, "second.py")
	grace.HandsIn()
	edsger.HandsIn()
	s.Merges(grace)
	s.Merges(edsger)

	grace.Hears("first.py")
	edsger.Hears("second.py")
}

func TestTheFirstMergeWeEverSeeJudgesItsOwnRangeAlone(t *testing.T) {
	s, _, grace := ready(t)

	grace.Edits(story.ElseAfterRaise, "first.py")
	grace.HandsIn()
	s.Merges(grace)

	grace.Hears("first.py")
	s.CostAtMost(ledger.FilesReadIntoTrees, 5)
}

func TestAMergeOfMoreFilesThanAnAnswerCarriesSaysSoAndJudgesNone(t *testing.T) {
	s, ada, grace := ready(t)

	grace.EditsMany(story.Clean, 201)
	grace.HandsIn()
	s.Merges(grace)

	told := ada.Hears("201 files")

	s.Says(told, "--branch")
	s.CostIs(ledger.FilesReadIntoTrees, 0)
}

func TestAMergeJustInsideTheCeilingIsJudged(t *testing.T) {
	s, _, grace := ready(t)

	grace.EditsMany(story.Clean, 199)
	grace.Edits(story.ElseAfterRaise, "last.py")
	grace.HandsIn()
	s.Merges(grace)

	grace.Hears("last.py")
}

func TestAMergeIsJudgedInTheProjectAndNeverInTheWorkersCopy(t *testing.T) {
	s, _, grace := ready(t)

	grace.Edits(story.ElseAfterRaise, "committed.py")
	grace.HandsIn()
	grace.Edits(story.SwallowedException, "uncommitted.py")
	s.Merges(grace)

	told := grace.Hears("committed.py")

	s.Never(told, "uncommitted.py")
}

func TestAJudgingThatCouldNotRunLeavesTheMarkWhereItWas(t *testing.T) {
	s, _, grace := ready(t)

	grace.Edits(story.BrokenSyntax, "broken.py")
	grace.HandsIn()
	s.Merges(grace)

	s.Exits(s.ReadsTheLanes(), 0)
}

func TestWhatMergesBroughtInIsKeptWhereAPersonReadsItTwentyAtMost(t *testing.T) {
	s, _, grace := ready(t)

	grace.Edits(story.MutableDefault, "kept.py")
	grace.HandsIn()
	s.Merges(grace)

	s.Says(s.ReadsTheLanes(), "merge")
}

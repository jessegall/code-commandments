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

	ada := s.Agent("Ada")

	return s, ada, s.DispatchedWorker("Grace", ada)
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

	grace.HandsIn()

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

func TestAMergeNamesNoSinInAFolderTheProjectExcludes(t *testing.T) {
	s, _, grace := ready(t)

	grace.Edits(story.ElseAfterRaise, "dist/bundle.py")
	grace.Edits(story.ElseAfterRaise, "order.py")
	grace.HandsIn()
	s.Merges(grace)

	s.Never(grace.Hears("redundant-python-else"), "bundle.py")
}

func TestAWorkerEditingAFolderTheProjectExcludesIsToldNothingAboutIt(t *testing.T) {
	_, _, grace := ready(t)

	grace.Edits(story.ElseAfterRaise, "order.py")
	grace.Edits(story.ElseAfterRaise, "dist/bundle.py")

	grace.Hears("order.py")
	grace.StaysUntold("bundle.py")
}

func TestJudgingAWorktreesChangesNeverReadsAFolderTheProjectExcludes(t *testing.T) {
	s, _, grace := ready(t)

	grace.Edits(story.ElseAfterRaise, "total.py")
	grace.Edits(story.ElseAfterRaise, "dist/bundle.py")

	judged := s.AsksIn(grace.Folder(), "sins", "--changes", "--no-checklist")

	s.Says(judged, "total.py")
	s.Never(judged, "bundle.py")
}

func TestACleanLandingSaysNothingAndSendsNothing(t *testing.T) {
	s, ada, grace := ready(t)

	grace.Edits(story.ElseAfterRaiseFixed, "refund.py")
	grace.HandsIn()
	s.Merges(grace)

	grace.StaysUntold("Code Commandments")
	ada.StaysUntold("Code Commandments")
}

func TestTwoMergesCloseTogetherJudgeTheWholeRangeOnce(t *testing.T) {
	s, ada, grace := ready(t)
	edsger := s.DispatchedWorker("Edsger", ada)

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
	s, _, grace := ready(t)

	grace.EditsMany(story.Clean, 201)
	grace.HandsIn()
	s.Merges(grace)

	kept := s.ReadsTheLanesOnceTheyShow("201 files")

	s.Says(kept, "--branch")
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

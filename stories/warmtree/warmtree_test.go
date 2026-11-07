//go:build story

package warmtree

import (
	"testing"

	"github.com/jessegall/code-commandments/cli/ledger"
	"github.com/jessegall/code-commandments/story"
)

var typescript = story.Sample{Under: "bridge/mypy", Label: "a typescript module with a coalesced default", Text: "export const total = (items?: number[]) => items ?? []\n"}

func held(t *testing.T) *story.Story {
	t.Helper()

	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())

	return s
}

func cold(t *testing.T) *story.Story {
	t.Helper()

	s := story.Begin(t)
	s.Given("a service whose lane of the test checkout was dropped", func() {
		s.GivenTheTreeIsHeldFor(s.TestCheckout())
		s.DropsTheLane(s.TestCheckout())
	})

	return s
}

func TestAFreshServiceMakesTheFirstJudgeWaitForTheTreeInsteadOfReadingTheProjectItself(t *testing.T) {
	s := cold(t)

	judged := s.AsksIn(s.TestCheckout(), "sins", "--changes")

	s.Says(judged, "waiting for the tree")
	s.Never(judged, "reads the whole tree itself")
	s.CostAtLeast(ledger.JudgingsToldTheTreeIsComing, 1)
}

func TestTwoJudgesAtOnceOnAColdServiceShareOneReadOfTheTree(t *testing.T) {
	s := cold(t)

	judged := s.AsksTogether(s.TestCheckout(), []string{"sins", "--changes"}, []string{"commandments-judge-changes"})

	s.Exits(judged[0], 0)
	s.CostAtMost(ledger.TreeReadsStarted, 1)
}

func TestOnceTheTreeIsHeldAJudgeReadsNothing(t *testing.T) {
	s := held(t)

	judged := s.AsksIn(s.TestCheckout(), "sins", "--changes")

	s.Never(judged, "reads the whole tree itself")
	s.CostIs(ledger.TreeReadsStarted, 0)
	s.CostIs(ledger.JudgingsDeclined, 0)
}

func TestAJudgeFromAWorktreeWhoseLaneIsBeingFilledIsToldSoAndWaits(t *testing.T) {
	s := held(t)
	grace := s.Worker("Grace")

	grace.Edits(story.ElseAfterRaise, "order.py")
	judged := s.AsksIn(grace.Folder(), "sins", "--changes")

	s.Never(judged, "reads the whole tree itself")
	s.CostIs(ledger.TreeReadsStarted, 0)
}

func TestAnEditDuringTheColdStartGetsASmallerAnswerAndTheReasonIsToldOnce(t *testing.T) {
	s := held(t)
	ada := s.Main("Ada")
	s.DropsTheLane(s.TestCheckout())

	ada.Edits(story.ElseAfterRaise, "first.py")
	first := ada.Hears("first.py")
	ada.Edits(story.ElseAfterRaise, "second.py")
	second := ada.Hears("second.py")

	s.Says(first, "tree")
	s.Never(second, "no tree of this checkout is held yet")
	s.CostAtLeast(ledger.JudgingsAnsweredSmaller, 1)
}

func TestTheSameEditOnceTheTreeHasArrivedGetsTheWholeAnswer(t *testing.T) {
	s := held(t)
	ada := s.Main("Ada")

	s.Writes(story.SettleOriginal, "payments.py")
	ada.Edits(story.SettleCopy, "refunds.py")

	told := ada.Hears("duplicate-python-function")

	s.Never(told, "no tree")
}

func TestGoingIdleKeepsTheTreeWarm(t *testing.T) {
	s := held(t)
	ada := s.Main("Ada")
	s.DropsTheLane(s.TestCheckout())

	ada.Stops()

	s.CostAtLeast(ledger.TreeReadsStarted, 1)
}

func TestSwitchingBranchInTheTestCheckoutDropsTheOldTreeAndReadsTheNewOne(t *testing.T) {
	s := held(t)
	ada := s.Main("Ada")
	s.SwitchesToAnotherBranch()

	ada.Stops()
	ada.Edits(story.ElseAfterRaise, "order.py")
	ada.Hears("order.py")

	s.CostAtLeast(ledger.TreeReadsStarted, 1)
}

func TestADetachedHeadHasNoTreeAndSaysSo(t *testing.T) {
	s := held(t)
	ada := s.Main("Ada")
	s.DetachesTheHead()

	ada.Stops()
	ada.Edits(story.ElseAfterRaise, "order.py")

	told := ada.Hears("order.py")

	s.Says(told, "no tree")
	s.CostIs(ledger.TreeReadsStarted, 0)
}

func TestAFileWhoseBytesMovedUnannouncedIsFoundBySweepAndReadAgain(t *testing.T) {
	s := held(t)
	ada := s.Main("Ada")

	s.Writes(story.ElseAfterRaise, "moved.py")
	s.LetsTheSweepFall()
	ada.Edits(story.Clean, "other.py")

	s.Says(ada.Hears("moved.py"), "redundant-python-else")
}

func TestAChangeNobodyAnnouncedIsStillJudged(t *testing.T) {
	s := held(t)

	s.Writes(story.ElseAfterRaise, "quiet.py")
	judged := s.AsksIn(s.TestCheckout(), "sins", "--changes")

	s.Says(judged, "quiet.py")
}

func TestAFileTouchedWithTheSameBytesIsNotReadAgain(t *testing.T) {
	s := held(t)
	ada := s.Main("Ada")

	s.Writes(story.Clean, "touched.py")
	s.LetsTheSweepFall()
	s.Writes(story.Clean, "touched.py")
	ada.Edits(story.Clean, "other.py")

	s.CostAtMost(ledger.FilesReadIntoTrees, 3)
}

func TestMoreFilesMovedThanOneViewCarriesLetsTheTreeGoAndItIsReadAgain(t *testing.T) {
	s := held(t)
	ada := s.Main("Ada")

	s.WritesMany(story.Clean, 1500)
	s.LetsTheSweepFall()
	ada.Edits(story.Clean, "other.py")
	ada.Hears("tree was let go")

	s.CostAtLeast(ledger.TreeReadsStarted, 1)
}

func TestAChangeTheSizeOfAMergeIsTakenIntoTheTreeInsteadOfGivenUp(t *testing.T) {
	s := held(t)
	ada := s.Main("Ada")

	s.WritesMany(story.Clean, 150)
	s.LetsTheSweepFall()
	ada.Edits(story.Clean, "other.py")

	s.CostIs(ledger.TreeReadsStarted, 0)
}

func TestPastAQuarterOfTheTreeAJudgeIsDeclinedAndAnsweredSmaller(t *testing.T) {
	s := held(t)

	s.WritesMany(story.Clean, 1200)
	judged := s.AsksIn(s.TestCheckout(), "sins", "--changes")

	s.Says(judged, "rules that answer from one file alone")
	s.CostAtLeast(ledger.JudgingsDeclined, 1)
}

func TestWithNoCeilingSetTheLanesAnswerSaysThereIsNone(t *testing.T) {
	s := held(t)

	lanes := s.ReadsTheLanes()

	s.Says(lanes, "lane")
}

func TestALanguageTheHeldTreeHasNoStreamForIsRefusedInWordsThatNameIt(t *testing.T) {
	s := held(t)
	ada := s.Main("Ada")

	ada.Edits(typescript, "total.ts")

	told := ada.Hears("total.ts")

	s.Says(told, "TypeScript")
}

func TestAFileThatCannotBeParsedNarrowsTheAnswerInWordsAndKeepsTheTree(t *testing.T) {
	s := held(t)
	ada := s.Main("Ada")

	ada.Edits(story.BrokenSyntax, "broken.py")

	told := ada.Hears("broken.py")

	s.Says(told, "could not be parsed")
	s.CostIs(ledger.TreeReadsStarted, 0)
}

func TestAFileDeletedFromTheProjectLeavesTheHeldView(t *testing.T) {
	s := held(t)
	ada := s.Main("Ada")

	s.Writes(story.SettleOriginal, "payments.py")
	s.LetsTheSweepFall()
	s.Removes(story.SettleOriginal, "payments.py")
	ada.Edits(story.SettleCopy, "refunds.py")
	ada.StaysUntold("duplicate-python-function")
}

func TestAWorkerIsServedFromTheTreeOfTheBranchItWasCutFrom(t *testing.T) {
	s := held(t)
	grace := s.Worker("Grace")
	s.SwitchesToAnotherBranch()

	grace.Edits(story.ElseAfterRaise, "order.py")
	grace.Hears("order.py")

	s.CostIs(ledger.TreeReadsStarted, 0)
}

func TestAWorkersCutWhileTheProjectHasNoTreeTellsHerTheTreeIsComing(t *testing.T) {
	s := cold(t)
	grace := s.Worker("Grace")

	grace.Edits(story.ElseAfterRaise, "order.py")
	told := grace.Hears("order.py")

	s.Says(told, "tree")
	s.CostAtLeast(ledger.JudgingsToldTheTreeIsComing, 1)
}

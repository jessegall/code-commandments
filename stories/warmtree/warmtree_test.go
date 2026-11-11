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
	s.Given("a service that let go of the tree of the test checkout", func() {
		s.GivenTheTreeIsHeldFor(s.TestCheckout())
		s.Writes(story.Clean, "changed.py")
		s.DropsTheTree(s.TestCheckout())
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

func TestAnEditIsJudgedOnItsOwnAndTheReasonIsToldOnce(t *testing.T) {
	s := held(t)
	ada := s.Agent("Ada")
	s.DropsTheTree(s.TestCheckout())

	ada.Edits(story.ElseAfterRaise, "first.py")
	first := ada.Hears("first.py")
	ada.Edits(story.MutableDefault, "first.py")
	second := ada.Hears("first.py")

	s.Says(first, "judged on its own")
	s.Never(second, "judged on its own")
	s.CostIs(ledger.TreeReadsStarted, 0)
}

func TestADuplicateAcrossTwoEditsIsFoundWhenTheChangesAreJudgedTogether(t *testing.T) {
	s := held(t)
	ada := s.Agent("Ada")

	ada.Edits(story.SettleOriginal, "payments.py")
	ada.Edits(story.SettleCopy, "refunds.py")
	ada.StaysUntold("duplicate-python-function")

	judged := s.AsksIn(s.TestCheckout(), "sins", "--changes")

	s.Says(judged, "duplicate-python-function")
}

func TestGoingIdleReadsNoTree(t *testing.T) {
	s := held(t)
	ada := s.Agent("Ada")
	s.DropsTheTree(s.TestCheckout())

	ada.Stops()

	s.Says(s.ReadsTheLanes(), "0 tree(s) held")
	s.CostIs(ledger.TreeReadsStarted, 0)
}

func TestSwitchingBranchInAWorktreeKeepsAnsweringFromTheTreeOfItsProject(t *testing.T) {
	s := held(t)
	ada := s.Agent("Ada")
	s.SwitchesToAnotherBranch()

	ada.Stops()
	ada.Edits(story.ElseAfterRaise, "order.py")

	s.Says(ada.Hears("order.py"), "redundant-python-else")
	s.CostIs(ledger.TreeReadsStarted, 0)
}

func TestADetachedWorktreeIsStillJudgedFromTheTreeOfItsProject(t *testing.T) {
	s := held(t)
	ada := s.Agent("Ada")
	s.DetachesTheHead()

	ada.Stops()
	ada.Edits(story.ElseAfterRaise, "order.py")

	told := ada.Hears("order.py")

	s.Says(told, "redundant-python-else")
	s.CostIs(ledger.TreeReadsStarted, 0)
}

func TestAChangeNobodyAnnouncedIsStillJudged(t *testing.T) {
	s := held(t)

	s.Writes(story.ElseAfterRaise, "quiet.py")
	judged := s.AsksIn(s.TestCheckout(), "sins", "--changes")

	s.Says(judged, "quiet.py")
}

func TestAFileTouchedWithTheSameBytesIsNotReadAgain(t *testing.T) {
	s := held(t)

	s.Writes(story.Clean, "touched.py")
	s.LetsTheSweepFall()
	s.Writes(story.Clean, "touched.py")
	s.AsksIn(s.TestCheckout(), "sins", "--changes")

	s.CostAtMost(ledger.FilesReadIntoTrees, 3)
}

func TestAChangeTheSizeOfAMergeIsTakenIntoTheTreeInsteadOfGivenUp(t *testing.T) {
	s := held(t)

	s.WritesMany(story.Clean, 150)
	s.LetsTheSweepFall()
	s.AsksIn(s.TestCheckout(), "sins", "--changes")

	s.CostIs(ledger.TreeReadsStarted, 0)
}

func TestPastAQuarterOfTheTreeAJudgeIsDeclinedAndReadsTheTreeItself(t *testing.T) {
	s := held(t)

	s.WritesMany(story.Clean, 1200)
	judged := s.AsksIn(s.TestCheckout(), "sins", "--changes")

	s.Never(judged, "refuses")
	s.CostAtLeast(ledger.JudgingsDeclined, 1)
}

func TestWithNoCeilingSetTheLanesAnswerSaysThereIsNone(t *testing.T) {
	s := held(t)

	lanes := s.ReadsTheLanes()

	s.Says(lanes, "lane")
}

func TestALanguageTheHeldTreeHasNoStreamForIsRefusedInWordsThatNameIt(t *testing.T) {
	s := held(t)
	ada := s.Agent("Ada")

	ada.Edits(typescript, "total.ts")

	told := ada.Hears("total.ts")

	s.Says(told, "TypeScript")
}

func TestAFileThatCannotBeParsedNarrowsTheAnswerInWordsAndKeepsTheTree(t *testing.T) {
	s := held(t)
	ada := s.Agent("Ada")

	ada.Edits(story.BrokenSyntax, "broken.py")

	told := ada.Hears("broken.py")

	s.Says(told, "could not be parsed")
	s.CostIs(ledger.TreeReadsStarted, 0)
}

func TestAFileDeletedFromTheProjectLeavesTheHeldView(t *testing.T) {
	s := held(t)
	ada := s.Agent("Ada")

	s.Writes(story.SettleOriginal, "payments.py")
	s.LetsTheSweepFall()
	s.Removes(story.SettleOriginal, "payments.py")
	ada.Edits(story.SettleCopy, "refunds.py")

	s.Never(s.AsksIn(s.TestCheckout(), "sins", "--changes"), "duplicate-python-function")
}

func TestAWorkerIsServedFromTheTreeOfTheBranchItWasCutFrom(t *testing.T) {
	s := held(t)
	grace := s.Worker("Grace")
	s.SwitchesToAnotherBranch()

	grace.Edits(story.ElseAfterRaise, "order.py")
	grace.Hears("order.py")

	s.CostIs(ledger.TreeReadsStarted, 0)
}

func TestAWorkersEditWhileTheProjectHasNoTreeIsJudgedOnItsOwn(t *testing.T) {
	s := cold(t)
	grace := s.Worker("Grace")

	grace.Edits(story.ElseAfterRaise, "order.py")
	told := grace.Hears("order.py")

	s.Says(told, "redundant-python-else")
	s.CostIs(ledger.TreeReadsStarted, 0)
}

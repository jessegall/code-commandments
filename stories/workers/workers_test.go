//go:build story

package workers

import (
	"testing"

	"github.com/jessegall/code-commandments/cli/ledger"
	"github.com/jessegall/code-commandments/story"
)

var (
	elseBesideAStrayConfig = story.Python("an else after a raise in a worktree that carries its own config", story.ElseAfterRaise.Text)

	elseBesideThePathOfASibling = story.Python("an else after a raise in a worktree cut beside the project", story.ElseAfterRaise.Text)
)

func held(t *testing.T) *story.Story {
	t.Helper()

	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())

	return s
}

func TestAWorkersFirstEditOpensALaneOfItsOwn(t *testing.T) {
	s := held(t)
	grace := s.Worker("Grace")
	grace.Edits(story.Clean, "order.py")

	lanes := s.ReadsTheLanes()

	s.Says(lanes, "story-engine-grace")
}

func TestAWorkerEditingTwiceOpensOneLane(t *testing.T) {
	s := held(t)
	edsger := s.Worker("Edsger")
	edsger.Edits(story.Clean, "order.py")
	edsger.Edits(story.Clean, "refunds.py")

	lanes := s.ReadsTheLanes()

	s.SaysOnce(lanes, "story-engine-edsger ")
}

func TestARemoveBeforeACutOpensNoLane(t *testing.T) {
	s := held(t)
	hopper := s.Worker("Hopper")
	hopper.Leaves()

	lanes := s.ReadsTheLanes()

	s.Never(lanes, "story-engine-hopper")
}

func TestAWorkersEditIsJudgedAgainstItsOwnWorktreeWithoutReadingTheProject(t *testing.T) {
	s := held(t)
	grace := s.Worker("Grace")

	grace.Edits(story.ElseAfterRaise, "order.py")
	told := grace.Hears("redundant-python-else")

	s.Says(told, "order.py", "commandments-python-flow")
	s.CostIs(ledger.TreeReadsStarted, 0)
	s.CostAtLeast(ledger.JudgingsFromTheTree, 1)
}

func TestAWorkersEarlierWorkIsInTheProgramItsNextEditIsJudgedAgainst(t *testing.T) {
	s := held(t)
	grace := s.Worker("Grace")

	grace.Edits(story.SettleOriginal, "helpers.py")
	grace.Edits(story.SettleCopy, "refunds.py")

	told := grace.Hears("duplicate-python-function")

	s.Says(told, "helpers.py")
}

func TestTwoWorktreesHoldingDifferentTextForOnePathAreEachAnsweredFromTheirOwn(t *testing.T) {
	s := held(t)
	grace := s.Worker("Grace")
	edsger := s.Worker("Edsger")

	grace.Edits(story.ElseAfterRaise, "order.py")
	edsger.Edits(story.Clean, "order.py")

	grace.Hears("redundant-python-else")
	edsger.StaysUntold("redundant-python-else")
}

func TestAFileOnlyTheProjectHasIsNotJudgedForAWorker(t *testing.T) {
	s := held(t)
	grace := s.Worker("Grace")

	s.Writes(story.ElseAfterRaise, "extra.py")
	grace.Edits(story.Clean, "order.py")
	grace.StaysUntold("extra.py")
}

func TestAFileTheProjectChangedSinceTheCutStaysInTheWorkersView(t *testing.T) {
	s := held(t)
	grace := s.Worker("Grace")

	s.Writes(story.SettleOriginal, "shared.py")
	grace.Edits(story.SettleCopy, "refunds.py")

	told := grace.Hears("duplicate-python-function")

	s.Says(told, "shared.py")
}

func TestAFileAWorkerDeletedIsNotInTheProgramItIsJudgedAgainst(t *testing.T) {
	s := held(t)
	grace := s.Worker("Grace")

	grace.Edits(story.SettleOriginal, "payments.py")
	grace.Deletes(story.SettleOriginal, "payments.py")
	grace.Edits(story.SettleCopy, "refunds.py")
	grace.StaysUntold("duplicate-python-function")
}

func TestAWorkerThatRebasesItselfIsRefilledAndNeverAnsweredFromTheOldBase(t *testing.T) {
	s := held(t)
	grace := s.Worker("Grace")

	grace.Edits(story.ElseAfterRaise, "order.py")
	grace.Hears("redundant-python-else")
	grace.Rebases()
	grace.Edits(story.ElseAfterRaiseFixed, "order.py")
	grace.Hears("order.py is clean")

	s.CostIs(ledger.TreeReadsStarted, 0)
}

func TestAWorkerWhoseBaseIsAdvancedKeepsNoStaleTextOfItsOwn(t *testing.T) {
	s := held(t)
	grace := s.Worker("Grace")

	grace.Edits(story.SwallowedException, "plugins.py")
	grace.Hears("python-swallowed-exception")
	s.Writes(story.SwallowedException, "plugins.py")
	grace.Rebases()
	grace.Edits(story.Clean, "other.py")
	grace.StaysUntold("plugins.py")
}

func TestAWorkerCutAgainIntoTheSameFolderMeetsNothingOfTheLast(t *testing.T) {
	s := held(t)
	first := s.Worker("Grace")

	first.Edits(story.ElseAfterRaise, "order.py")
	first.Hears("redundant-python-else")
	first.Leaves()

	second := s.Worker("Grace")
	second.Edits(story.Clean, "order.py")
	second.StaysUntold("redundant-python-else")
}

func TestAWorkersAnswerSaysWhichRulesWereAsked(t *testing.T) {
	s := held(t)
	grace := s.Worker("Grace")

	grace.Edits(story.MutableDefault, "marks.py")

	told := grace.Hears("python-mutable-default")

	s.Says(told, "marks.py")
}

func TestAWorktreesOwnConfigIsNeverRead(t *testing.T) {
	s := held(t)
	grace := s.Worker("Grace")

	grace.Edits(elseBesideAStrayConfig, "order.py")
	grace.Hears("redundant-python-else")
}

func TestAWorktreeCutBesideTheProjectIsStillItsOwnCheckout(t *testing.T) {
	s := held(t)
	grace := s.Worker("Grace")

	grace.Edits(elseBesideThePathOfASibling, "order.py")

	told := grace.Hears("redundant-python-else")

	s.Says(told, "order.py")
}

func TestARemovedWorktreeClosesItsLaneWhileTheOthersStay(t *testing.T) {
	s := held(t)
	grace := s.Worker("Grace")
	edsger := s.Worker("Edsger")
	grace.Edits(story.Clean, "order.py")
	edsger.Edits(story.Clean, "order.py")
	grace.Leaves()

	lanes := s.ReadsTheLanes()

	s.Never(lanes, "story-engine-grace")
	s.Says(lanes, "story-engine-edsger")
}

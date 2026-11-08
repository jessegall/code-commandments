//go:build story

package edits

import (
	"testing"

	"github.com/jessegall/code-commandments/cli/ledger"
	"github.com/jessegall/code-commandments/story"
)

func editing(t *testing.T) (*story.Story, *story.Agent) {
	t.Helper()

	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())

	return s, s.Agent("Ada")
}

func TestAnEditIsAnsweredAtOnceAndItsSinsArriveAtTheAgentsNextMoment(t *testing.T) {
	s, ada := editing(t)

	answered := ada.Edits(story.ElseAfterRaise, "order.py")
	told := ada.Hears("redundant-python-else")

	s.Never(answered, "redundant-python-else")
	s.Names(told, "order.py", "redundant-python-else")
	s.CostIs(ledger.TreeReadsStarted, 0)
	s.CostAtLeast(ledger.JudgingsFromTheTree, 1)
}

func TestACleanEditSaysNothingAndRaisesNothing(t *testing.T) {
	s, ada := editing(t)

	ada.Edits(story.Clean, "total.py")
	ada.StaysUntold("total.py")

	s.CostIs(ledger.JudgingsToldTheTreeIsComing, 0)
}

func TestFixingTheSinTurnsItsCardGreenOnTheEditThatFixesIt(t *testing.T) {
	_, ada := editing(t)

	ada.Edits(story.ElseAfterRaise, "order.py")
	ada.Hears("redundant-python-else")
	ada.Edits(story.ElseAfterRaiseFixed, "order.py")
	ada.StaysUntold("redundant-python-else")
}

func TestASinFixedByAShellCommandIsSettledAtTheNextShellMoment(t *testing.T) {
	s, ada := editing(t)

	ada.Edits(story.ElseAfterRaise, "order.py")
	ada.Hears("redundant-python-else")
	s.Writes(story.ElseAfterRaiseFixed, "order.py")
	ada.Runs("true")
	ada.StaysUntold("redundant-python-else")
}

func TestARuleThatReadsAcrossFilesIsAnsweredFromTheWholeTree(t *testing.T) {
	s, ada := editing(t)

	s.Writes(story.SettleOriginal, "payments.py")
	ada.Edits(story.SettleCopy, "refunds.py")

	told := ada.Hears("duplicate-python-function")

	s.Says(told, "payments.py", "refunds.py")
	s.Never(told, "smaller")
}

func TestASinInAFileTheAgentDidNotTouchIsNotTheEditsMark(t *testing.T) {
	s, ada := editing(t)

	s.Writes(story.ElseAfterRaise, "legacy.py")
	ada.Edits(story.Clean, "order.py")
	ada.StaysUntold("legacy.py")
}

func TestOnlyLinesTheEditChangedAreMarked(t *testing.T) {
	_, ada := editing(t)

	ada.Edits(story.ElseAfterRaise, "old.py")
	ada.Hears("old.py")
	ada.Edits(oldSinNewFunction, "old.py")
	ada.StaysUntold("old.py:14")
}

func TestTwoSinsOnOneLineAreBothTold(t *testing.T) {
	s, ada := editing(t)

	ada.Edits(story.TwoSinsInOneFile, "refund.py")

	told := ada.Hears("refund.py")

	s.Says(told, "redundant-python-else", "python-swallowed-exception")
}

func TestOneSinFoundManyTimesInAFileIsSaidOnceWithItsCount(t *testing.T) {
	s, ada := editing(t)

	ada.Edits(story.SixElses, "many.py")

	told := ada.Hears("redundant-python-else")

	s.SaysOnce(told, "redundant-python-else")
	s.Says(told, "6")
}

func TestAFileInAFolderTheProjectSkipsIsJudgedByNoRule(t *testing.T) {
	s, ada := editing(t)

	ada.Edits(inVendor, "order.py")
	ada.StaysUntold("redundant-python-else")

	s.Says(s.Asks("commandments", "why", "order.py"), "order.py")
}

func TestAFileOutsideTheFoldersTheProjectJudgesIsNotLookedAt(t *testing.T) {
	s, ada := editing(t)

	ada.Edits(inScripts, "order.py")
	ada.StaysUntold("redundant-python-else")

	s.Says(s.Asks("commandments", "why", "order.py"), "Nothing has judged")
}

func TestAFileOfATypeNoRuleJudgesIsNothingToJudge(t *testing.T) {
	s, ada := editing(t)

	answered := ada.Edits(note, "notes.txt")

	s.Never(answered, "redundant-python-else")
	ada.StaysUntold("notes.txt")
}

func TestASinTheAgentWasToldOfAndTheProjectReportedIsNotToldAgain(t *testing.T) {
	s, ada := editing(t)

	s.Asks("commandments", "report", "follow")
	ada.Edits(story.SwallowedException, "plugins.py")
	ada.StaysUntold("python-swallowed-exception")
}

func TestAFileThatIsNoLongerOnDiskIsRefusedInWords(t *testing.T) {
	s, ada := editing(t)

	ada.Edits(story.ElseAfterRaise, "gone.py")
	s.Removes(story.ElseAfterRaise, "gone.py")

	told := ada.Hears("gone.py")

	s.Says(told, "not on disk")
}

func TestARelativePathIsReadAgainstTheFolderTheJournalNames(t *testing.T) {
	s, ada := editing(t)

	ada.EditsByRelativePath(story.ElseAfterRaise, "order.py")

	told := ada.Hears("redundant-python-else")

	s.Says(told, "order.py")
}

func TestAToolThatChangedFiveFilesGetsOneAnswerEachAndAllAreJudged(t *testing.T) {
	s, ada := editing(t)

	for _, file := range []string{"a.py", "b.py", "c.py", "d.py", "e.py"} {
		ada.Edits(story.ElseAfterRaise, file)
	}

	told := ada.Hears("e.py")

	s.Says(told, "a.py", "b.py", "c.py", "d.py")
	s.CostAtLeast(ledger.JudgingsFromTheTree, 1)
}

func TestAFileThatMovedAgainBeforeItsJudgingIsNotJudgedFromTheOldText(t *testing.T) {
	_, ada := editing(t)

	ada.Edits(story.ElseAfterRaise, "moved.py")
	ada.Edits(story.ElseAfterRaiseFixed, "moved.py")
	ada.StaysUntold("redundant-python-else")
}

func TestAGeneratedFileIsSkipped(t *testing.T) {
	_, ada := editing(t)

	ada.Edits(story.GeneratedFile, "gen.py")
	ada.StaysUntold("gen.py")
}

func TestASinInAFileThatIsGoneIsRepented(t *testing.T) {
	s, ada := editing(t)

	ada.Edits(story.ElseAfterRaise, "short_lived.py")
	ada.Hears("redundant-python-else")
	s.Removes(story.ElseAfterRaise, "short_lived.py")
	ada.Stops()
	ada.StaysUntold("redundant-python-else")
}

func TestAWindowsStylePathInTheEventIsNamedAsTheAgentSeesIt(t *testing.T) {
	s, ada := editing(t)

	ada.EditsByWindowsPath(story.ElseAfterRaise, "order.py")

	told := ada.Hears("redundant-python-else")

	s.Never(told, `\`)
}

func TestOneEditArrivingUnderBothJournalsSpellingsIsAnsweredOnce(t *testing.T) {
	s, ada := editing(t)

	ada.EditsTwice(story.ElseAfterRaise, "twice.py")

	told := ada.Hears("redundant-python-else")

	s.SaysOnce(told, "redundant-python-else")
}

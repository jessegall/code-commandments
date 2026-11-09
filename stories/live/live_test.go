//go:build story

package live

import (
	"fmt"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli/ledger"
	"github.com/jessegall/code-commandments/story"
)

const (
	elseRule      = "redundant-python-else"
	duplicateRule = "duplicate-python-function"

	twoSins = `def first(a):
    if a:
        raise ValueError(a)
    else:
        return 1


def second(a):
    if a:
        raise ValueError(a)
    else:
        return 2
`

	twoSinsBeforeTheEdit = `def first(a):
    if a:
        raise ValueError(a)
    else:
        return 1


def second(a):
    if a > 1:
        raise ValueError(a)
    else:
        return 2
`

	secondSinLine = 9
)

func editing(t *testing.T) (*story.Story, *story.Agent) {
	t.Helper()

	s := story.Begin(t)

	var ada *story.Agent

	s.Given("the tree is held and an agent works in it", func() {
		s.GivenTheTreeIsHeldFor(s.TestCheckout())
		ada = s.Agent("Ada")
	})

	return s, ada
}

func judgesEverything(s *story.Story) {
	s.Exits(s.AsksIn(s.TestCheckout(), "sins", "--changes", "--no-checklist"), 1)
}

func sinful(s *story.Story, label string) story.Sample {
	return s.FreshPython(label, story.ElseAfterRaise.Text)
}

func TestAnEditBackToAJudgedTextIsToldFromMemory(t *testing.T) {
	s, ada := editing(t)
	judged := sinful(s, "a sinful text judged before an agent writes it")
	s.Writes(judged, "back.py")
	judgesEverything(s)
	s.Writes(s.FreshPython("another text in its place", story.Clean.Text), "back.py")
	s.CountsFromHere()

	ada.Edits(judged, "back.py")
	told := ada.Hears(elseRule)

	s.Names(told, "back.py", elseRule)
	s.Judged(0)
	s.CostAtLeast(ledger.DetectorFilesRemembered, 1)
}

func TestTwoEditsInOneWindowAreJudgedOnceAsAGroup(t *testing.T) {
	s, ada := editing(t)
	s.CountsFromHere()

	ada.Edits(sinful(s, "the first of two edits"), "first.py")
	ada.Edits(sinful(s, "the second of two edits"), "second.py")
	told := ada.Hears("first.py")

	s.Says(told, "second.py")
	s.CostIs(ledger.JudgingsFromTheTree, 1)
}

func TestARealEditCostsOnlyItsFile(t *testing.T) {
	s, ada := editing(t)
	s.Given("twelve different sinful files, judged", func() {
		for one := 1; one <= 12; one++ {
			s.Writes(sinful(s, fmt.Sprintf("sinful file %d of twelve", one)), fmt.Sprintf("file%d.py", one))
		}

		judgesEverything(s)
	})
	s.CountsFromHere()
	ada.Edits(sinful(s, "a new file to measure one file's cost"), "measured.py")
	ada.Hears("measured.py")
	oneFile := s.Cost(ledger.DetectorFilesJudged)
	remembered := s.Cost(ledger.DetectorFilesRemembered)
	s.CountsFromHere()

	ada.Edits(s.FreshPython("sinful file 7 rewritten", story.SixElses.Text), "file7.py")
	ada.Hears("file7.py")

	s.Judged(oneFile)
	s.Remembered(remembered)
}

func TestTwoAgentsWritingTheSameTextAreEachToldUnderTheirOwnPath(t *testing.T) {
	s, ada := editing(t)
	grace := s.Worker("Grace")
	s.GivenTheTreeIsHeldFor(grace.Folder())
	same := sinful(s, "a sinful text two agents write")
	ada.Edits(same, "same.py")
	s.Names(ada.Hears(elseRule), "same.py", elseRule)
	s.CountsFromHere()

	grace.Edits(same, "same.py")
	told := grace.Hears(elseRule)

	s.Names(told, "same.py", elseRule)
	s.NamesOnlyWholePaths(told, grace.Folder())
	s.Judged(0)
	s.CostAtLeast(ledger.DetectorFilesRemembered, 1)
}

func TestASinRevertedToIsToldAgainFromMemoryAtItsLine(t *testing.T) {
	s, ada := editing(t)
	sin := sinful(s, "a sin an agent fixes and brings back")
	ada.Edits(sin, "revert.py")
	ada.Hears(elseRule)
	ada.Edits(s.FreshPython("the sin fixed with its condition reworded", strings.Replace(story.ElseAfterRaiseFixed.Text, "> 30", ">= 31", 1)), "revert.py")
	ada.StaysUntold(elseRule)
	judgedSoFar := s.Cost(ledger.DetectorFilesJudged)
	rememberedSoFar := s.Cost(ledger.DetectorFilesRemembered)
	ada.ForgetsWhatItWasTold()

	ada.Edits(sin, "revert.py")
	told := ada.Hears(elseRule)

	s.Names(told, "revert.py:6", elseRule)
	s.CostIs(ledger.DetectorFilesJudged, judgedSoFar)
	s.CostAtLeast(ledger.DetectorFilesRemembered, rememberedSoFar+1)
}

func TestOnlyTheSinOnTheChangedLineOfARememberedFileIsMarked(t *testing.T) {
	s, ada := editing(t)
	before := s.FreshPython("two sins with the second line written otherwise", twoSinsBeforeTheEdit)
	after := s.FreshPython("two sins", twoSins)
	s.Given("both texts are judged and the first is on disk", func() {
		s.Writes(after, "two.py")
		judgesEverything(s)
		s.Writes(before, "two.py")
		judgesEverything(s)
	})
	s.CountsFromHere()

	ada.Edits(after, "two.py")
	told := ada.Hears(fmt.Sprintf("two.py:%d", secondSinLine))

	s.Never(told, "two.py:2")
	s.Judged(0)
}

func TestAPerFileSinAndACrossFileSinAreToldTogether(t *testing.T) {
	s, ada := editing(t)
	both := s.FreshPython("a file with an else after a raise and a function copied beside it", story.ElseAfterRaise.Text+"\n\n"+story.SettleOriginal.Text)
	ada.Edits(s.FreshPython("the copy beside it", story.SettleCopy.Text), "refunds.py")
	s.Writes(both, "payments.py")
	judgesEverything(s)
	s.Writes(s.FreshPython("the same file before its sins", story.Clean.Text), "payments.py")
	s.CountsFromHere()

	ada.Edits(both, "payments.py")
	ada.Hears(elseRule)
	told := ada.Hears(duplicateRule)

	s.Says(told, "payments.py", "refunds.py")
	s.Never(told, "smaller")
	s.Judged(0)
}

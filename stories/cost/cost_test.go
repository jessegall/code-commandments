//go:build story

package cost

import (
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/ledger"
	"github.com/jessegall/code-commandments/story"
)

const answersAtOnce = 10 * time.Second

var (
	inVendor = story.Sample{Under: "vendor", Label: "clean files in a skipped folder", Text: story.Clean.Text}

	inScripts = story.Sample{Under: "scripts", Label: "clean files outside the judged folders", Text: story.Clean.Text}
)

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

func TestTheOneReadOfAColdTreeIsCountedWithItsFilesAndItsTime(t *testing.T) {
	s := cold(t)

	judged := s.AsksIn(s.TestCheckout(), "sins", "--changes")

	s.Never(judged, "reads the whole tree itself")
	s.CostReaches(ledger.TreeReadsFinished, 1)
	s.CostAtLeast(ledger.FilesReadIntoTrees, 1)
}

func TestAJudgingOfACleanFolderFromTheHeldTreeReadsNoFile(t *testing.T) {
	s := held(t)

	s.AsksIn(s.TestCheckout(), "sins", "bridge/php/src")

	s.CostIs(ledger.TreeReadsStarted, 0)
	s.CostIs(ledger.FilesReadIntoTrees, 0)
}

func TestOnlyTwoJudgingsRunAtOnceAndTheRestSayTheyWaited(t *testing.T) {
	s := held(t)

	answers := s.AsksTogether(s.TestCheckout(),
		[]string{"sins", "--changes"}, []string{"sins", "--changes"}, []string{"sins", "--changes"},
		[]string{"sins", "--changes"}, []string{"sins", "--changes"})

	for _, answer := range answers {
		s.Exits(answer, 0)
	}

	s.CostIs(ledger.TreeReadsStarted, 0)
}

func TestASkippedFolderCostsNothingHoweverBigItIs(t *testing.T) {
	s := held(t)

	s.WritesMany(inVendor, 2000)
	s.AsksIn(s.TestCheckout(), "sins", "--changes")

	s.CostIs(ledger.FilesReadIntoTrees, 0)
}

func TestOnlyTheFoldersTheProjectJudgesAreRead(t *testing.T) {
	s := held(t)

	s.WritesMany(inScripts, 500)
	s.AsksIn(s.TestCheckout(), "sins", "--changes")

	s.CostIs(ledger.FilesReadIntoTrees, 0)
}

func TestAnEditOfAFileTheProjectDoesNotJudgeIsNotAnAnswerMadeSmaller(t *testing.T) {
	s := held(t)
	ada := s.Agent("Ada")

	ada.Edits(story.Sample{Under: "bridge/mypy", Label: "a note that is no source", Text: "a note\n"}, "notes.txt")
	ada.Stops()

	s.CostIs(ledger.JudgingsAnsweredSmaller, 0)
}

func TestAQuietMinuteCostsNothing(t *testing.T) {
	s := held(t)
	ada := s.Agent("Ada")

	ada.Moves()
	ada.Stops()

	s.CostIs(ledger.TreeReadsStarted, 0)
	s.CostIs(ledger.FilesReadIntoTrees, 0)
	s.CostIs(ledger.JudgingsFromTheTree, 0)
}

func TestAnEditCostsTheFileAndNothingOfTheRest(t *testing.T) {
	s := held(t)
	ada := s.Agent("Ada")

	ada.Edits(story.ElseAfterRaise, "order.py")
	ada.Hears("order.py")

	s.CostIs(ledger.TreeReadsStarted, 0)
	s.CostAtMost(ledger.FilesReadIntoTrees, 2)
}

func TestACrossFileQuestionReadsOnlyTheEditedFile(t *testing.T) {
	s := held(t)
	ada := s.Agent("Ada")

	ada.Edits(story.SettleOriginal, "payments.py")
	ada.Edits(story.SettleCopy, "refunds.py")
	ada.Hears("duplicate-python-function")

	s.CostAtMost(ledger.FilesReadIntoTrees, 3)
}

func TestAFileAlreadyReadIsNotReadAgainForTheNextJudging(t *testing.T) {
	s := held(t)
	ada := s.Agent("Ada")

	ada.Edits(story.SwallowedException, "plugins.py")
	ada.Hears("plugins.py")
	s.AsksIn(s.TestCheckout(), "sins", "--changes")

	s.CostAtMost(ledger.FilesReadIntoTrees, 3)
}

func TestASweepOfUntouchedFilesReadsNoneOfThem(t *testing.T) {
	s := held(t)
	ada := s.Agent("Ada")

	s.LetsTheSweepFall()
	ada.Edits(story.Clean, "total.py")
	ada.StaysUntold("total.py")

	s.CostAtMost(ledger.FilesReadIntoTrees, 2)
}

func TestAHeldEventIsAnsweredWithinItsWindowWhileAJudgingIsRunning(t *testing.T) {
	s := held(t)
	ada := s.Agent("Ada")

	ada.Edits(story.SixElses, "many.py")
	began := time.Now()
	ada.Runs("true")

	if took := time.Since(began); took > answersAtOnce {
		s.Fails("a command about to run waited " + took.String() + " for a judging, where the gate answers from what it knows")
	}
}

func TestAFileEditIsAnsweredAtOnceHoweverLongTheJudgingAfterItTakes(t *testing.T) {
	s := held(t)
	ada := s.Agent("Ada")

	began := time.Now()
	ada.Edits(story.MutableDefault, "marks.py")

	if took := time.Since(began); took > answersAtOnce {
		s.Fails("an edit waited " + took.String() + " for its judging, where it is answered at once")
	}
}

func TestAWorkersLaneIsFilledOnceAndThenCostsOnlyTheFile(t *testing.T) {
	s := held(t)
	grace := s.Worker("Grace")

	grace.Edits(story.ElseAfterRaise, "first.py")
	grace.Hears("first.py")
	grace.Edits(story.ElseAfterRaise, "second.py")
	grace.Hears("second.py")

	s.CostIs(ledger.TreeReadsStarted, 0)
	s.CostAtMost(ledger.FilesReadIntoTrees, 4)
}

func TestAMergeReadsOnlyTheFilesOfItsRange(t *testing.T) {
	s := held(t)
	s.GivenHandInsAreNotJudged()
	grace := s.DispatchedWorker("Grace", s.Agent("Ada"))

	grace.Edits(story.Clean, "one.py")
	grace.Edits(story.MutableDefault, "three.py")
	grace.HandsIn()
	s.Merges(grace)

	s.CostAtMost(ledger.FilesReadIntoTrees, 10)
	s.CostIs(ledger.TreeReadsStarted, 0)
}

func TestEveryJudgingWritesOneLineAPersonCanRead(t *testing.T) {
	s := held(t)
	ada := s.Agent("Ada")

	ada.Edits(story.ElseAfterRaise, "order.py")
	ada.Hears("order.py")

	s.Says(s.Asks("commandments", "why", "order.py"), "tree=held")
}

func TestAJudgingThatWasDeclinedSaysWhyInItsLine(t *testing.T) {
	s := held(t)
	ada := s.Agent("Ada")
	s.DropsTheLane(s.TestCheckout())

	ada.Edits(story.ElseAfterRaise, "declined.py")
	ada.Hears("declined.py")

	s.Says(s.Asks("commandments", "why", "declined.py"), "declined")
}

func TestTheAnswerSaysHowLongThePluginTook(t *testing.T) {
	s := held(t)
	ada := s.Agent("Ada")

	ada.Edits(story.MutableDefault, "timed.py")

	ada.Hears("timed.py")

	s.Says(s.Asks("commandments", "why", "timed.py"), "ms")
}

func TestAJudgingForALaneThatClosedIsAbandonedAndQueuedForNobody(t *testing.T) {
	s := held(t)
	grace := s.Worker("Grace")

	grace.Edits(story.ElseAfterRaise, "order.py")
	grace.Leaves()

	s.Never(s.ReadsTheLanes(), grace.Folder())
}

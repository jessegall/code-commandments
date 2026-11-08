//go:build story

package together

import (
	"fmt"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/ledger"
	"github.com/jessegall/code-commandments/story"
)

const answersAtOnce = 10 * time.Second

func busy(t *testing.T) *story.Story {
	t.Helper()

	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())

	return s
}

func TestTwoWorkersEditingAtOnceAreEachToldOnlyTheirOwnSins(t *testing.T) {
	s := busy(t)
	grace, edsger := s.Worker("Grace"), s.Worker("Edsger")

	grace.Edits(story.ElseAfterRaise, "graces.py")
	edsger.Edits(story.SwallowedException, "edsgers.py")

	s.Never(grace.Hears("graces.py"), "edsgers.py")
	s.Never(edsger.Hears("edsgers.py"), "graces.py")
}

func TestTwoAgentsInOneCheckoutEditingOneFileAreEachAnswered(t *testing.T) {
	s := busy(t)
	ada, barbara := s.Agent("Ada"), s.Agent("Barbara")

	ada.Edits(story.ElseAfterRaise, "shared.py")
	ada.Hears("shared.py")

	barbara.Edits(story.SwallowedException, "shared.py")
	barbara.Hears("shared.py")
}

func TestOneAgentsSlowJudgingNeverDelaysAnothersAnswer(t *testing.T) {
	s := busy(t)
	grace, edsger := s.Worker("Grace"), s.Worker("Edsger")

	grace.Edits(story.SixElses, "many.py")
	edsger.Edits(story.MutableDefault, "marks.py")
	began := time.Now()
	edsger.Hears("marks.py")

	if took := time.Since(began); took > 4*answersAtOnce {
		s.Fails("the second agent waited " + took.String() + " for the first agent's judging")
	}
}

func TestAnAgentsOwnMomentsAreWorkedOutInTheOrderTheyCame(t *testing.T) {
	s := busy(t)
	grace := s.Worker("Grace")

	grace.Edits(story.ElseAfterRaise, "order.py")
	grace.Edits(story.ElseAfterRaiseFixed, "order.py")
	grace.Edits(story.SwallowedException, "order.py")

	s.Says(grace.Hears("order.py"), "order.py")
}

func TestAnAgentsEditsAreWorkedOutBeforeItsStop(t *testing.T) {
	s := busy(t)
	grace := s.Worker("Grace")

	grace.Edits(story.MutableDefault, "marks.py")
	grace.Stops()

	s.Says(grace.Hears("marks.py"), "marks.py")
}

func TestAGateIsAnsweredWhileAnEarlierMomentsAdviceStillRuns(t *testing.T) {
	s := busy(t)
	grace := s.Worker("Grace")

	grace.Edits(story.SixElses, "many.py")
	began := time.Now()
	grace.Runs("true")

	if took := time.Since(began); took > answersAtOnce {
		s.Fails("a gate waited " + took.String() + " for an earlier moment's advice")
	}
}

func TestAQueuedAnswerNamesTheAgentItIsFor(t *testing.T) {
	s := busy(t)
	grace, edsger := s.Worker("Grace"), s.Worker("Edsger")

	grace.Edits(story.ElseAfterRaise, "first.py")
	edsger.Edits(story.ElseAfterRaise, "second.py")

	s.Never(grace.Hears("first.py"), "nothing of this edit was judged")
	s.Never(edsger.Hears("second.py"), "nothing of this edit was judged")
}

func TestOneWorkersTwoSimultaneousEditsFillItsLaneOnce(t *testing.T) {
	s := busy(t)
	grace := s.Worker("Grace")

	grace.EditsMany(story.Clean, 40)
	grace.Edits(story.ElseAfterRaise, "first.py")
	grace.Edits(story.ElseAfterRaise, "second.py")
	grace.Hears("second.py")

	s.CostAtMost(ledger.FilesReadIntoTrees, 45)
}

func TestTwoLandingsAtTheSameMomentAreJudgedOneAtATime(t *testing.T) {
	s := busy(t)
	s.GivenHandInsAreNotJudged()
	ada := s.Agent("Ada")
	grace, edsger := s.DispatchedWorker("Grace", ada), s.DispatchedWorker("Edsger", ada)

	grace.Edits(story.ElseAfterRaise, "first.py")
	edsger.Edits(story.SwallowedException, "second.py")
	grace.HandsIn()
	edsger.HandsIn()
	s.Merges(grace)
	s.Merges(edsger)

	s.Never(grace.Hears("first.py"), "second.py")
}

func TestALandingDoesNotDisturbAWorkersNextLane(t *testing.T) {
	s := busy(t)
	s.GivenHandInsAreNotJudged()
	grace, edsger := s.Worker("Grace"), s.DispatchedWorker("Edsger", s.Agent("Ada"))

	grace.Edits(story.ElseAfterRaise, "first.py")
	grace.Hears("first.py")
	edsger.Edits(story.Clean, "total.py")
	edsger.HandsIn()
	s.Merges(edsger)
	grace.Edits(story.SwallowedException, "second.py")
	grace.Hears("second.py")

	s.CostAtMost(ledger.FilesReadIntoTrees, 6)
}

func TestALandingWhileTheMainAgentEditsAnswersBothCorrectly(t *testing.T) {
	s := busy(t)
	s.GivenHandInsAreNotJudged()
	ada := s.Agent("Ada")
	grace := s.DispatchedWorker("Grace", ada)

	grace.Edits(story.SwallowedException, "plugins.py")
	grace.HandsIn()
	ada.Edits(story.ElseAfterRaise, "order.py")
	s.Merges(grace)

	s.Never(ada.Hears("order.py"), "plugins.py")
}

func TestTheMainAgentsFindingIsNeverToldToAWorkerEditingTheSamePath(t *testing.T) {
	s := busy(t)
	ada, grace := s.Agent("Ada"), s.Worker("Grace")

	ada.Edits(story.ElseAfterRaise, "order.py")
	grace.Edits(story.Clean, "order.py")

	ada.Hears("order.py")
	grace.StaysUntold("redundant-python-else")
}

func TestThreeWorkersCutAtOnceOnAColdServiceShareOneRead(t *testing.T) {
	s := busy(t)
	s.DropsTheLane(s.TestCheckout())
	grace, edsger, barbara := s.Worker("Grace"), s.Worker("Edsger"), s.Worker("Barbara")

	grace.Edits(story.Clean, "a.py")
	edsger.Edits(story.Clean, "b.py")
	barbara.Edits(story.Clean, "c.py")

	s.CostAtMost(ledger.TreeReadsStarted, 1)
}

func TestThreeWorkersEditingTogetherAreEachToldExactlyTheirOwnWithoutARead(t *testing.T) {
	s := busy(t)
	grace, edsger, barbara := s.Worker("Grace"), s.Worker("Edsger"), s.Worker("Barbara")

	grace.Edits(story.ElseAfterRaise, "graces.py")
	edsger.Edits(story.ElseAfterRaise, "edsgers.py")
	barbara.Edits(story.ElseAfterRaise, "barbaras.py")

	s.Never(grace.Hears("graces.py"), "edsgers.py", "barbaras.py")
	s.Never(edsger.Hears("edsgers.py"), "graces.py", "barbaras.py")
	s.Never(barbara.Hears("barbaras.py"), "graces.py", "edsgers.py")
	s.CostIs(ledger.TreeReadsStarted, 0)
}

func TestALaneClosedForOneWorkerLeavesTheOthersAlone(t *testing.T) {
	s := busy(t)
	grace, edsger := s.Worker("Grace"), s.Worker("Edsger")

	edsger.Edits(story.Clean, "a.py")
	grace.Leaves()
	edsger.Edits(story.ElseAfterRaise, "order.py")
	edsger.Hears("order.py")

	s.CostIs(ledger.TreeReadsStarted, 0)
}

func TestAWorkerThatRebasesLeavesAnotherWorkersAnswersUnchanged(t *testing.T) {
	s := busy(t)
	grace, edsger := s.Worker("Grace"), s.Worker("Edsger")

	grace.Rebases()
	edsger.Edits(story.SwallowedException, "plugins.py")

	s.Says(edsger.Hears("plugins.py"), "plugins.py")
}

func TestABurstOfTenAgentsMomentsMakesNobodysAnswerWaitOnAnothers(t *testing.T) {
	s := busy(t)
	agents := []*story.Agent{s.Agent("Ada")}

	for number := range 9 {
		agents = append(agents, s.Worker(fmt.Sprintf("Worker %d", number)))
	}

	for number, agent := range agents {
		agent.Edits(story.ElseAfterRaise, fmt.Sprintf("burst_%d.py", number))
	}

	for number, agent := range agents {
		agent.Hears(fmt.Sprintf("burst_%d.py", number))
	}
}

func TestFourAgentsStoppingTogetherSettleOnlyTheirOwnMarks(t *testing.T) {
	s := busy(t)
	ada, grace, edsger, barbara := s.Agent("Ada"), s.Worker("Grace"), s.Worker("Edsger"), s.Worker("Barbara")

	for number, agent := range []*story.Agent{ada, grace, edsger, barbara} {
		agent.Edits(story.ElseAfterRaise, fmt.Sprintf("own_%d.py", number))
	}

	ada.Stops()
	grace.Stops()
	edsger.Stops()
	barbara.Stops()

	s.Never(ada.Hears("own_0.py"), "own_1.py")
}

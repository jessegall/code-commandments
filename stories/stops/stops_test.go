//go:build story

package stops

import (
	"testing"

	"github.com/jessegall/code-commandments/story"
)

var goChanged = story.Sample{Under: "bridge/mypy", Label: "a changed go file for the stop nudge", Text: "package shop\n\nfunc Total(order *Order) int {\n\treturn order.Total()\n}\n"}

func working(t *testing.T) (*story.Story, *story.Agent) {
	t.Helper()

	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())

	return s, s.Agent("Ada")
}

func TestStoppingWithUnjudgedChangesNudgesOnceNamingTheCountAndTheCommand(t *testing.T) {
	s, ada := working(t)

	ada.Edits(story.Clean, "a.py")
	ada.Edits(story.Clean, "b.py")

	s.Says(ada.Stops(), "judged files since the last commit", "judge")
}

func TestTheSameBatchIsNudgedOnlyOnce(t *testing.T) {
	s, ada := working(t)

	ada.Edits(story.Clean, "a.py")
	ada.Stops()

	s.Never(ada.Stops(), "since the last commit")
}

func TestACommitCommandIsNudgedBeforeItRuns(t *testing.T) {
	s, ada := working(t)

	ada.Edits(story.Clean, "a.py")

	s.Says(ada.Runs("git commit -m story"), "before you commit")
}

func TestTheNudgeCountsOnlyTheFilesTheSessionChanged(t *testing.T) {
	s, ada := working(t)

	s.Writes(story.Clean, "other.py")
	ada.Edits(story.Clean, "mine.py")

	s.Never(ada.Stops(), "other.py")
}

func TestAFileChangedByAShellCommandCountsAsTheSessions(t *testing.T) {
	s, ada := working(t)

	s.Writes(story.Clean, "shell.py")
	ada.Runs("true")

	s.Says(ada.Stops(), "judged")
}

func TestAnOpenWorklistAtStopIsNamedBeforeAnythingElse(t *testing.T) {
	s, ada := working(t)

	s.Writes(story.ElseAfterRaise, "order.py")
	s.AsksIn(s.TestCheckout(), "sins", "--changes")

	s.Says(ada.Stops(), "OPEN worklist")
}

func TestTheWorklistNudgeRepeatsOnlyWhenTheListChanged(t *testing.T) {
	s, ada := working(t)

	s.Writes(story.ElseAfterRaise, "order.py")
	s.AsksIn(s.TestCheckout(), "sins", "--changes")
	ada.Stops()

	s.Never(ada.Stops(), "OPEN worklist")
}

func TestAnEmptyChecklistIsSilentAndForgotten(t *testing.T) {
	s, ada := working(t)

	s.Writes(story.ElseAfterRaiseFixed, "order.py")
	s.AsksIn(s.TestCheckout(), "sins", "--changes")

	s.Never(ada.Stops(), "OPEN worklist")
}

func TestTheChecklistGroupsSinsBySkill(t *testing.T) {
	s, _ := working(t)

	s.Writes(story.ElseAfterRaise, "order.py")
	s.Writes(story.SwallowedException, "plugins.py")

	judged := s.AsksIn(s.TestCheckout(), "sins", "--changes")

	s.Says(judged, "python/flow", "python/exceptions", "checklist written to")
}

func TestACleanRerunDeletesTheChecklist(t *testing.T) {
	s, _ := working(t)

	s.Writes(story.ElseAfterRaise, "order.py")
	s.AsksIn(s.TestCheckout(), "sins", "--changes")
	s.Writes(story.ElseAfterRaiseFixed, "order.py")
	rerun := s.AsksIn(s.TestCheckout(), "sins", "--changes")

	s.Exits(rerun, 0)
	s.Never(rerun, "checklist written to")
}

func TestAChangedGoFileCountsInTheSessionsWorklist(t *testing.T) {
	s, ada := working(t)

	ada.Edits(goChanged, "total.go")

	s.Says(ada.Stops(), "judged")
}

func TestAStopWithNothingChangedAndNoWorklistIsSilent(t *testing.T) {
	s, ada := working(t)

	s.Never(ada.Stops(), "OPEN worklist", "since the last commit")
}

func TestASinStillPresentIsNotSettledByAStop(t *testing.T) {
	s, ada := working(t)

	ada.Edits(story.ElseAfterRaise, "order.py")
	ada.Hears("order.py")

	s.Never(ada.Stops(), "order.py is clean")
}

func TestStoppingSettlesASinFixedBehindTheHooksBack(t *testing.T) {
	s, ada := working(t)

	ada.Edits(story.ElseAfterRaise, "order.py")
	ada.Hears("order.py")
	s.Writes(story.ElseAfterRaiseFixed, "order.py")
	ada.Stops()

	ada.StaysUntold("redundant-python-else")
}

func TestInAWorktreeTheNudgeNamesTheCommandForThatWorktree(t *testing.T) {
	s, _ := working(t)
	grace := s.Worker("Grace")

	grace.Edits(story.Clean, "a.py")

	s.Says(grace.Stops(), grace.Folder())
}

func TestASubagentsStopIsSilentAboutJudging(t *testing.T) {
	s, ada := working(t)

	ada.Edits(story.Clean, "a.py")

	s.Never(ada.SendsTheEvent("SubagentStop"), "since the last commit", "OPEN worklist")
}

func TestAPromptASessionStartAndACompactionAreSilentAboutJudging(t *testing.T) {
	s, ada := working(t)

	ada.Edits(story.Clean, "a.py")

	s.Never(ada.SendsTheEvent("UserPromptSubmit"), "since the last commit")
	s.Never(ada.SendsTheEvent("SessionStart"), "since the last commit")
	s.Never(ada.SendsTheEvent("PreCompact"), "since the last commit")
}

func TestTheSkillOfASinToldIsOwedOnceHoweverManySinsTeachIt(t *testing.T) {
	s, ada := working(t)

	ada.Edits(story.SixElses, "many.py")

	s.SaysOnce(ada.Hears("commandments-python-flow"), "commandments-python-flow")
}

func TestTheSkillOfASinIsOwedNotMerelyNamed(t *testing.T) {
	s, ada := working(t)

	ada.Edits(story.ElseAfterRaise, "order.py")

	s.Says(ada.Hears("commandments-python-flow"), "LOAD the skill")
}

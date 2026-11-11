//go:build story

package handins

import (
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/ledger"
	"github.com/jessegall/code-commandments/story"
)

const (
	serviceComesUp  = 90 * time.Second
	serviceGoes     = 20 * time.Second
	bridgeComesBack = 60 * time.Second
)

func ready(t *testing.T, fixture string) (*story.Story, *story.Agent, *story.Agent) {
	t.Helper()

	s := story.Begin(t)
	t.Cleanup(func() { s.WaitsForTheService("hooks", serviceComesUp) })

	var ada, grace *story.Agent

	s.Given(fixture, func() {
		s.GivenTheTreeIsHeldFor(s.TestCheckout())

		ada = s.Agent("Ada")
		grace = s.DispatchedWorker("Grace", ada)
	})

	return s, ada, grace
}

func sinful(s *story.Story, label string) story.Sample {
	return s.FreshPython(label, story.ElseAfterRaise.Text)
}

func restartsTheHooksService(s *story.Story) {
	service := s.TheService("journal-serve")

	s.Exits(s.StopsAService(service.Pid, story.Terminates), 0)
	s.EventuallyGone(service.Pid, serviceGoes)
	s.WaitsForTheService("hooks", serviceComesUp)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())
}

func TestAHandInDuringARestartWaitsAndIsRefusedNamingTheSin(t *testing.T) {
	s, _, grace := ready(t, "a worker ready to hand in during a restart")
	grace.Edits(sinful(s, "a sinful file handed in during a restart"), "duringrestart.py")
	service := s.TheService("journal-serve")
	s.Exits(s.StopsAService(service.Pid, story.Terminates), 0)

	refused := grace.TriesToHandIn()

	s.Refuses(refused)
	s.Says(refused, "duringrestart.py")
}

func TestAHandInWithItsLanguageServiceDownIsNeverCalledClean(t *testing.T) {
	s, _, grace := ready(t, "a worker ready to hand in with no bridge")
	grace.Edits(sinful(s, "a sinful file handed in with its bridge down"), "bridgedown.py")
	bridge := s.TheBridgeOf("mypy-serve")
	t.Cleanup(func() { s.ABridgeOfComesBack("mypy-serve", bridge.Pid, bridgeComesBack) })
	s.StopsAService(bridge.Pid, story.Kills)

	refused := grace.TriesToHandIn()

	s.Refuses(refused)
	s.Says(refused, "bridgedown.py")
	s.Never(refused, " clean")
}

func TestARenameOnlyHandInIsAnsweredFromMemory(t *testing.T) {
	s, _, grace := ready(t, "a worker ready to rename a file")
	renamed := s.FreshPython("a clean file renamed before a hand-in", story.ElseAfterRaiseFixed.Text)
	grace.Edits(renamed, "beforerename.py")
	s.AsksIn(grace.Folder(), "sins", "--changes")
	s.CountsFromHere()
	grace.Deletes(renamed, "beforerename.py")
	grace.Edits(renamed, "afterrename.py")

	grace.HandsIn()

	s.Judged(0)
	s.CostAtLeast(ledger.DetectorFilesRemembered, 1)
}

func TestADeletionOnlyHandInIsAllowed(t *testing.T) {
	s, ada, grace := ready(t, "a worker ready to add a file another deletes")
	doomed := s.FreshPython("a clean file a later worker deletes", story.ElseAfterRaiseFixed.Text)
	grace.Edits(doomed, "doomed.py")
	grace.HandsIn()
	s.Merges(grace)
	edsger := s.DispatchedWorker("Edsger", ada)

	edsger.Deletes(doomed, "doomed.py")

	edsger.HandsIn()
}

func TestAHandInRefusedThenFixedIsAllowed(t *testing.T) {
	s, _, grace := ready(t, "a worker ready to be refused and fix it")
	grace.Edits(sinful(s, "a sinful file refused at a hand-in"), "refusedfirst.py")
	refused := grace.TriesToHandIn()
	s.Refuses(refused)
	s.Says(refused, "refusedfirst.py")

	grace.Edits(s.FreshPython("the same file fixed", story.ElseAfterRaiseFixed.Text), "refusedfirst.py")

	grace.HandsIn()
}

func TestAMergeAfterARestartJudgesOnlyItsOwnRange(t *testing.T) {
	s, ada, grace := ready(t, "a worker ready to merge before a restart")
	s.GivenHandInsAreNotJudged()
	grace.Edits(sinful(s, "a sinful file merged before a restart"), "mergedfirst.py")
	grace.HandsIn()
	s.Merges(grace)
	told := strings.Count(grace.Hears("mergedfirst.py").Text(), "mergedfirst.py")
	restartsTheHooksService(s)
	edsger := s.DispatchedWorker("Edsger", ada)
	edsger.Edits(sinful(s, "a sinful file merged after a restart"), "mergedlater.py")
	edsger.HandsIn()

	s.Merges(edsger)

	edsger.Hears("mergedlater.py")

	if again := strings.Count(grace.Moves().Text(), "mergedfirst.py"); again != told {
		s.Fails("the second merge told the first merge's file again")
	}
}

func TestAMergeNamesNoSinInAFileTheWorkerDeleted(t *testing.T) {
	s, _, grace := ready(t, "a worker ready to delete a sinful file")
	s.GivenHandInsAreNotJudged()
	deleted := sinful(s, "a sinful file deleted before a merge")
	grace.Edits(deleted, "deletedfile.py")
	grace.Deletes(deleted, "deletedfile.py")
	grace.Edits(s.FreshPython("a clean file kept for a merge", story.ElseAfterRaiseFixed.Text), "keptfile.py")
	grace.HandsIn()
	grace.ForgetsWhatItWasTold()

	merged := s.Merges(grace)

	s.Never(merged, "deletedfile.py")
	grace.StaysUntold("deletedfile.py")
}

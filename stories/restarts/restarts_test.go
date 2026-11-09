//go:build story

package restarts

import (
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/ledger"
	"github.com/jessegall/code-commandments/story"
)

const (
	elseRule = "redundant-python-else"

	serviceComesUp  = 90 * time.Second
	bridgeComesBack = 60 * time.Second

	unreadableMemory = "are unreadable, so they start empty"
)

func settled(t *testing.T) (*story.Story, *story.MemoryFile, int) {
	t.Helper()

	s := story.Begin(t)
	t.Cleanup(func() { s.WaitsForTheService("hooks", serviceComesUp) })

	rest := 0

	s.Given("the tree is held and the rule has read it", func() {
		s.GivenTheTreeIsHeldFor(s.TestCheckout())
		judgesEverything(s)

		rest = s.Cost(ledger.DetectorFilesJudged) + s.Cost(ledger.DetectorFilesRemembered)
		s.CountsFromHere()
	})

	return s, s.MemoryFile(), rest
}

func comesBack(s *story.Story) {
	s.WaitsForTheService("hooks", serviceComesUp)
}

func judges(s *story.Story) story.Ran {
	return s.AsksIn(s.TestCheckout(), "sins", "--changes", "--sin="+elseRule)
}

func judgesEverything(s *story.Story) story.Ran {
	return s.AsksIn(s.TestCheckout(), "sins", s.TestCheckout(), "--sin="+elseRule)
}

func sinful(s *story.Story, label string) story.Sample {
	return s.FreshPython(label, story.ElseAfterRaise.Text)
}

func TestTheDetectorMemorySurvivesARestart(t *testing.T) {
	s, memory, rest := settled(t)
	s.Writes(sinful(s, "a sinful file judged before a restart"), "before.py")
	s.Exits(judges(s), 1)
	s.Judged(1)

	memory.StopsTheService()
	comesBack(s)
	s.CountsFromHere()

	s.Exits(judges(s), 1)
	s.Judged(0)
	s.Remembered(1 + rest)
}

func TestAHeldTreeComesBackFromDiskAfterARestart(t *testing.T) {
	s, memory, _ := settled(t)

	memory.StopsTheService()
	comesBack(s)
	judges(s)

	s.CostReaches(ledger.TreesLoadedFromDisk, 1)
	s.CostIs(ledger.FilesReadIntoTrees, 0)
}

func TestARestartedBridgeParsesOnlyTheEditedFile(t *testing.T) {
	s, _, _ := settled(t)
	bridge := s.TheBridgeOf("mypy-serve")
	s.StopsAService(bridge.Pid, story.Kills)
	s.ABridgeOfComesBack("mypy-serve", bridge.Pid, bridgeComesBack)
	s.CountsFromHere()
	s.Writes(sinful(s, "a sinful file edited after its bridge came back"), "edited.py")

	s.Exits(judges(s), 1)

	s.CostIs(ledger.FilesParsedByBridges, 1)
}

func TestAFileChangedWhileTheServiceWasDownIsTheOnlyOneJudged(t *testing.T) {
	s, memory, rest := settled(t)
	s.Given("the service is down before a file changes", memory.StopsTheService)
	s.Writes(sinful(s, "a sinful file written while the service was down"), "whiledown.py")

	comesBack(s)
	judged := judges(s)

	s.Exits(judged, 1)
	s.Judged(1)
	s.Remembered(rest)
	s.Says(judged, "whiledown.py")
}

func TestAMemoryFromAnotherBuildIsNotTrusted(t *testing.T) {
	s, memory, rest := settled(t)
	s.Given("the service is down before its memory is stamped", memory.StopsTheService)
	memory.Stamp("another build")

	comesBack(s)
	judgesEverything(s)

	s.Judged(rest)
	s.Remembered(0)
}

func TestACorruptMemoryStartsEmptyAndSaysSo(t *testing.T) {
	s, memory, rest := settled(t)
	s.Given("the service is down before its memory is corrupted", memory.StopsTheService)
	memory.Corrupt()

	comesBack(s)
	judgesEverything(s)

	s.Says(s.Asks("plugin", "service-log", "code-commandments", "hooks"), unreadableMemory)
	s.Judged(rest)
	s.Remembered(0)
}

func TestAWorktreeJudgesIntoTheOneMemoryFileOfTheProject(t *testing.T) {
	s, memory, _ := settled(t)
	worker := s.Worker("Grace")
	s.GivenTheTreeIsHeldFor(worker.Folder())
	memory.StopsTheService()
	before := memory.Reads().Entries
	comesBack(s)
	s.WritesIn(worker.Folder(), sinful(s, "a sinful file only a worktree holds"), "onlyhere.py")
	s.Exits(s.AsksIn(worker.Folder(), "sins", "--changes", "--sin="+elseRule), 1)

	memory.StopsTheService()

	if after := memory.Reads().Entries; after <= before {
		s.Fails("the worktree's judging did not reach the project's memory file")
	}
}

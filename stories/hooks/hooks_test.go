//go:build story

package hooks

import (
	"fmt"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli/ledger"
	"github.com/jessegall/code-commandments/story"
)

const (
	elseRule = "redundant-python-else"
	plugin   = "code-commandments"
	worklist = "OPEN worklist"

	serviceComesUp = 90 * time.Second
	serviceGoes    = 20 * time.Second
	answeredAtOnce = 3 * time.Second

	aMegabyte = 1 << 20
)

func working(t *testing.T) (*story.Story, *story.Agent) {
	t.Helper()

	s := story.Begin(t)
	t.Cleanup(func() { s.WaitsForTheService("hooks", serviceComesUp) })
	s.GivenTheTreeIsHeldFor(s.TestCheckout())

	return s, s.Agent("Ada")
}

func stopsTheHooksService(s *story.Story) {
	service := s.TheService("journal-serve")

	s.Exits(s.StopsAService(service.Pid, story.Terminates), 0)
	s.EventuallyGone(service.Pid, serviceGoes)
}

func restartsTheHooksService(s *story.Story) {
	stopsTheHooksService(s)
	s.WaitsForTheService("hooks", serviceComesUp)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())
}

func sinful(s *story.Story, label string) story.Sample {
	return s.FreshPython(label, story.ElseAfterRaise.Text)
}

func TestADeadServiceNeverBlocksAnAgent(t *testing.T) {
	s, ada := working(t)
	stopsTheHooksService(s)

	began := time.Now()
	answered := ada.Edits(sinful(s, "a sinful file edited with no service"), "dead.py")
	took := time.Since(began)

	s.Exits(answered, 0)
	s.Never(answered, "clean")
	if took > answeredAtOnce {
		s.Fails(fmt.Sprintf("an edit with the service down took %v to be answered", took))
	}

	s.WaitsForTheService("hooks", serviceComesUp)
	s.Names(ada.Hears(elseRule), "dead.py", elseRule)
}

func TestGarbageInIsAnsweredCleanlyAndTheServiceStaysUp(t *testing.T) {
	s, ada := working(t)
	running := s.TheService("journal-serve")

	for label, body := range map[string]string{
		"an empty body":           "",
		"a body that is not JSON": "{not json",
		"a body of a megabyte":    strings.Repeat("x", aMegabyte),
	} {
		s.Exits(ada.SendsARawBody(label, body), 0)
	}

	if after := s.TheService("journal-serve"); after.Pid != running.Pid {
		s.Fails("the service was replaced after garbage: " + running.Pid + " is now " + after.Pid)
	}
}

func TestOddFileNamesAreJudgedAndNamedAsWritten(t *testing.T) {
	s, ada := working(t)

	for _, name := range []string{"a b.py", "é.py"} {
		ada.Edits(sinful(s, "a sinful file named "+name), name)
		s.Says(ada.Hears(elseRule), name)
		ada.ForgetsWhatItWasTold()
	}

	dotted := sinful(s, "a sinful file in a folder ending in a dot")
	dotted.Under += "/dotted."

	ada.Edits(dotted, "indotted.py")
	s.Says(ada.Hears(elseRule), "dotted.", "indotted.py")
}

func TestTheFirstEditAfterARestartIsAnsweredFromTheLoadedTree(t *testing.T) {
	s, ada := working(t)
	restartsTheHooksService(s)
	s.CountsFromHere()

	ada.Edits(sinful(s, "a sinful file edited after a restart"), "afterrestart.py")

	s.Names(ada.Hears(elseRule), "afterrestart.py", elseRule)
	s.CostIs(ledger.FilesReadIntoTrees, 0)
}

func TestTheWorklistSurvivesARestart(t *testing.T) {
	s, ada := working(t)
	s.SwitchesThePluginSetting("workers_cli", "true", "false")
	s.Writes(sinful(s, "a sinful file on a worklist"), "listed.py")
	ada.Asks("sins", "--changes")

	restartsTheHooksService(s)
	ada.Stops()

	ada.Hears(worklist)
}

func TestADisabledPluginIsSilentAndLeavesNothingRunning(t *testing.T) {
	s, ada := working(t)
	t.Cleanup(func() { s.Asks("plugin", "enable", plugin, "--reason", "a story puts the plugin back") })

	s.Exits(s.Asks("plugin", "disable", plugin, "--reason", "a story proves a disabled plugin is silent"), 0)

	answered := ada.Edits(sinful(s, "a sinful file edited with the plugin off"), "off.py")

	s.Never(answered, elseRule)
	ada.StaysUntold(elseRule)
	s.Never(ada.Stops(), worklist)
	s.NoOrphans()
	for _, left := range s.Processes() {
		s.EventuallyGone(left.Pid, serviceGoes)
	}

	s.Exits(s.Asks("plugin", "enable", plugin, "--reason", "a story puts the plugin back"), 0)
	s.WaitsForTheService("hooks", serviceComesUp)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())
	ada.ForgetsWhatItWasTold()
	ada.SettlesIn()
	ada.Edits(sinful(s, "a sinful file edited with the plugin on again"), "onagain.py")

	s.Names(ada.Hears(elseRule), "onagain.py", elseRule)
}

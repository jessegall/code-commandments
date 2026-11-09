//go:build story

package bridges

import (
	"fmt"
	"path/filepath"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/story"
)

const (
	serviceComesUp   = 60 * time.Second
	bridgeGoneWithin = 20 * time.Second
	bridgeComesBack  = 60 * time.Second
	noBridgeWhile    = 8 * time.Second

	notAWholeAnswer = "not a clean answer"
)

func aKilledBridgeComesBackAndItsFilesNeverReadClean(t *testing.T, verb string, sample story.Sample, file string, other story.Sample, otherFile string) {
	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())
	written := s.Writes(sample, file)
	s.Writes(story.Sample{Under: sample.Under, Label: other.Label, Text: other.Text}, otherFile)
	bridge := s.TheBridgeOf(verb)

	s.StopsAService(bridge.Pid, story.Kills)
	s.EventuallyGone(bridge.Pid, bridgeGoneWithin)

	judged := s.AsksIn(s.TestCheckout(), "sins", filepath.Dir(written))

	s.Says(judged, otherFile)
	if !strings.Contains(judged.Text(), file) && !strings.Contains(judged.Text(), notAWholeAnswer) {
		s.Fails(fmt.Sprintf("with its bridge killed, %s is neither judged nor said to be missing:\n%s", file, judged.Text()))
	}

	s.ABridgeOfComesBack(verb, bridge.Pid, bridgeComesBack)
	s.Says(s.AsksIn(s.TestCheckout(), "sins", filepath.Dir(written)), file, otherFile)
	s.NoOrphans()
}

func TestAKilledPythonBridgeComesBack(t *testing.T) {
	aKilledBridgeComesBackAndItsFilesNeverReadClean(t, "mypy-serve", story.ElseAfterRaise, "refund.py", story.PHPDeepNesting, "Checkout.php")
}

func TestAKilledTypeScriptBridgeComesBack(t *testing.T) {
	aKilledBridgeComesBackAndItsFilesNeverReadClean(t, "frontend-serve", story.TSMutatedLoop, "order.ts", story.ElseAfterRaise, "refund.py")
}

func TestAKilledVueBridgeComesBack(t *testing.T) {
	aKilledBridgeComesBackAndItsFilesNeverReadClean(t, "frontend-serve", story.VueChain, "State.vue", story.ElseAfterRaise, "refund.py")
}

func TestAKilledPHPBridgeComesBack(t *testing.T) {
	aKilledBridgeComesBackAndItsFilesNeverReadClean(t, "php-serve", story.PHPDeepNesting, "Checkout.php", story.ElseAfterRaise, "refund.py")
}

func TestAProjectWithoutALanguageKeepsNoBridgeOfIt(t *testing.T) {
	s := story.Begin(t)
	s.WaitsForTheService("hooks", serviceComesUp)
	service := s.StartsABareServiceUnderAParent("roslyn")

	service.KeepsNoBridge(noBridgeWhile)

	service.Answers()
	service.Wrote("no C# to judge")
}

func TestAKilledCSharpBridgeComesBack(t *testing.T) {
	aKilledBridgeComesBackAndItsFilesNeverReadClean(t, "roslyn-serve", story.NullReturn, "Orders.cs", story.ElseAfterRaise, "refund.py")
}

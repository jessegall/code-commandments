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

var csharpElseAfterReturn = story.CSharp("an else after a return", `namespace Shop;

public class Orders
{
    public int Find(int id)
    {
        if (id > 0)
        {
            return id;
        }
        else
        {
            return 0;
        }
    }
}
`)

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
		s.Fails(fmt.Sprintf("with its bridge killed, %s is neither judged nor said to be missing:\n%s\nthe hooks service wrote:\n%s",
			file, judged.Text(), s.Asks("plugin", "service-log", "code-commandments", "hooks").Text()))
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

func TestACSharpFileIsJudgedWithAHealthyBridge(t *testing.T) {
	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())
	written := s.Writes(csharpElseAfterReturn, "Healthy.cs")

	s.Says(s.AsksIn(s.TestCheckout(), "sins", filepath.Dir(written)), "Healthy.cs")
}

func TestACSharpFileBesideAPythonFileIsJudgedWithIt(t *testing.T) {
	s := story.Begin(t)
	s.GivenTheTreeIsHeldFor(s.TestCheckout())
	written := s.Writes(csharpElseAfterReturn, "Beside.cs")
	s.Writes(story.Sample{Under: csharpElseAfterReturn.Under, Label: story.ElseAfterRaise.Label, Text: story.ElseAfterRaise.Text}, "beside.py")

	s.Says(s.AsksIn(s.TestCheckout(), "sins", filepath.Dir(written)), "Beside.cs", "beside.py")
}

func TestACSharpFileIsJudgedByAColdRead(t *testing.T) {
	s := story.Begin(t)
	project := s.ScratchProject("a sinful C# class", story.PlainFile{Path: "src/Orders.cs", Text: csharpElseAfterReturn.Text})

	s.Says(project.Judges("src", "--no-checklist"), "Orders.cs")
}

func TestAKilledCSharpBridgeComesBack(t *testing.T) {
	aKilledBridgeComesBackAndItsFilesNeverReadClean(t, "roslyn-serve", csharpElseAfterReturn, "Orders.cs", story.ElseAfterRaise, "refund.py")
}

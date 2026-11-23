package judge

import (
	"bytes"
	"io"
	"slices"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/rule"
	"github.com/jessegall/code-commandments/sins"
)

type WalksAgainDetector struct{ path string }

func (WalksAgainDetector) Sin() sins.Sin { return nil }

func (d WalksAgainDetector) Find(*engine.Codebase) []engine.Match {
	panic(rule.WentOverAFileAgain{Path: d.path, Nodes: 4200})
}

func TestARuleStoppedForGoingOverAFileAgainIsSkippedInOneLineAndTheRunGoesOn(t *testing.T) {
	var warned bytes.Buffer

	judgement := Run([]Task{{Detector: WalksAgainDetector{"/shop/app/Checkout.php"}}, {Detector: WalksAgainDetector{"/shop/app/Checkout.php"}}}, 1, cli.NewProgress(io.Discard), &warned)

	if !slices.Equal(judgement.Skipped, []string{"WalksAgainDetector", "WalksAgainDetector"}) {
		t.Fatalf("two stopped rules were skipped as %v, not each by its name with the run going on to the second", judgement.Skipped)
	}
	if said := warned.String(); strings.Count(said, "\n") != 2 || !strings.Contains(said, "WalksAgainDetector was stopped and skipped") || !strings.Contains(said, "/shop/app/Checkout.php") || strings.Contains(said, "goroutine") {
		t.Fatalf("a stopped rule is said in one line naming the rule and the file it kept going over, with no stack; this was said:\n%s", said)
	}
}

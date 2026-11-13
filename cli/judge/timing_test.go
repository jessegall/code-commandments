package judge

import (
	"bytes"
	"strings"
	"testing"
	"time"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/engine"
)

func TestEveryJudgingEndsSayingWhatItReadAndJudged(t *testing.T) {
	var said bytes.Buffer
	work := engine.Work{Parsed: 8713, Kept: 8673, Remembered: 1600, Judged: 76}
	cli.SayJudged(&said, 8600*time.Millisecond, 4900*time.Millisecond, 3, work)

	for _, wanted := range []string{"read 8713 files in 8.6s", "8673 from the cache", "40 by the bridges", "judged in 4.9s",
		"1600 rule answers from memory", "76 asked", "3 sins"} {
		if !strings.Contains(said.String(), wanted) {
			t.Errorf("the line does not carry %q: %q", wanted, said.String())
		}
	}
}

func TestTheWorkIsSaidOnlyWhenAsked(t *testing.T) {
	var unasked bytes.Buffer
	t.Setenv(cli.Timing, "")
	cli.SayWork(cli.Console{Out: &unasked, Err: &unasked}, engine.Work{Nodes: 70, Checks: 80, Analyses: 9})

	if unasked.Len() != 0 {
		t.Errorf("a run nobody asked says %q", unasked.String())
	}
}

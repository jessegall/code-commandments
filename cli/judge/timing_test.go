package judge

import (
	"bytes"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/engine"
)

func TestARunSaysWhereItsTimeWentOnlyWhenAsked(t *testing.T) {
	var asked bytes.Buffer
	t.Setenv(cli.Timing, "1")
	sayWhereTheTimeWent(1.5, 0.25, 12, engine.Work{Nodes: 70, Checks: 80, Analyses: 9}, cli.Console{Out: &asked, Err: &asked})

	said := asked.String()
	for _, wanted := range []string{"read in 1.50s", "judged in 0.25s", "12 rules", "work: 70 nodes, 80 checks, 9 analyses"} {
		if !strings.Contains(said, wanted) {
			t.Errorf("what it said does not carry %q: %q", wanted, said)
		}
	}

	var unasked bytes.Buffer
	t.Setenv(cli.Timing, "")
	sayWhereTheTimeWent(1.5, 0.25, 12, engine.Work{Nodes: 70, Checks: 80, Analyses: 9}, cli.Console{Out: &unasked, Err: &unasked})

	if unasked.Len() != 0 {
		t.Errorf("a run nobody asked says %q", unasked.String())
	}
}

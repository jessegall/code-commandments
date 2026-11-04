package judge

import (
	"bytes"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
)

// TestARunSaysWhereItsTimeWentOnlyWhenAsked holds the one split that has answered every question about this
// tool's speed, and holds it to costing nothing when nobody asks. A consumer timed 3,364 edits from their own
// side over nine hours to tell us a median of 2.8 seconds and a tail of 318, and we could not tell them which
// part of a run those seconds were in.
func TestARunSaysWhereItsTimeWentOnlyWhenAsked(t *testing.T) {
	var asked bytes.Buffer
	t.Setenv(cli.Timing, "1")
	sayWhereTheTimeWent(1.5, 0.25, 12, cli.Console{Out: &asked, Err: &asked})

	said := asked.String()
	for _, wanted := range []string{"read in 1.50s", "judged in 0.25s", "12 rules"} {
		if !strings.Contains(said, wanted) {
			t.Errorf("what it said does not carry %q: %q", wanted, said)
		}
	}

	var unasked bytes.Buffer
	t.Setenv(cli.Timing, "")
	sayWhereTheTimeWent(1.5, 0.25, 12, cli.Console{Out: &unasked, Err: &unasked})

	if unasked.Len() != 0 {
		t.Errorf("a run nobody asked says %q", unasked.String())
	}
}

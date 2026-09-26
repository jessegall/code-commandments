package repent

import (
	"errors"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/scribes"
)

func TestAScribeThatBrokeSaysWhyOnStandardError(t *testing.T) {
	var out, errs strings.Builder
	converged := scribes.Converged{Skipped: []scribes.Skipped{{Step: "SwitchCaseDetector", Err: errors.New("the frontend bridge failed: signal: killed")}}}

	if code := skipped(converged, cli.Console{Out: &out, Err: &errs}); code != aRuleCouldNotRun {
		t.Errorf("exit %d, want %d", code, aRuleCouldNotRun)
	}
	if want := "⚠ SwitchCaseDetector failed and was skipped — everything else still ran: the frontend bridge failed: signal: killed\n"; errs.String() != want {
		t.Errorf("stderr %q, want %q", errs.String(), want)
	}
	if !strings.Contains(out.String(), "SwitchCaseDetector") {
		t.Errorf("stdout names no broken rule: %q", out.String())
	}
}

package info

import (
	"bytes"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	_ "github.com/jessegall/code-commandments/registry"
)

func explained(args ...string) (code int, said string, warned string) {
	var out, errs bytes.Buffer
	code, _ = Command{}.Run(cli.FromArgs(args), cli.Console{Out: &out, Err: &errs})

	return code, out.String(), errs.String()
}

func TestASinIsExplainedByItsOwnNameAndItsDetectors(t *testing.T) {
	for name, named := range map[string][]string{
		"the sin itself":          {"info", "swallow-catch"},
		"the sin named by option": {"info", "--sin=swallow-catch"},
	} {
		t.Run(name, func(t *testing.T) {
			code, said, warned := explained(named...)

			if code != 0 {
				t.Fatalf("exits %d, saying %q", code, warned)
			}
			if !strings.Contains(said, "swallow-catch") {
				t.Errorf("what it printed does not name the sin:\n%s", said)
			}
			if !strings.Contains(said, "skill") && !strings.Contains(said, "Skill") {
				t.Errorf("what it printed does not name the skill that teaches the fix:\n%s", said)
			}
		})
	}
}

func TestANameThatMatchesNothingSaysSoAndExitsTwo(t *testing.T) {
	code, said, warned := explained("info", "zz-no-such-rule")

	if code == 0 {
		t.Errorf("a name nobody has was explained as if it were a rule:\n%s", said)
	}
	if !strings.Contains(warned+said, "zz-no-such-rule") {
		t.Errorf("it does not name what was asked for: %q", warned+said)
	}
}

func TestNoNameAtAllSaysWhatToType(t *testing.T) {
	code, _, warned := explained("info")

	if code != 2 {
		t.Errorf("exits %d, want 2", code)
	}
	if !strings.Contains(warned, "info") {
		t.Errorf("it says %q, which does not say what to type", warned)
	}
}

func TestAPartialNameNamesEveryRuleItCouldMean(t *testing.T) {
	code, said, warned := explained("info", "python-dict")

	if code == 0 && !strings.Contains(said, "python-dict") {
		t.Errorf("a partial name was answered without naming what it could mean:\n%s", said)
	}
	if code != 0 && !strings.Contains(warned+said, "python-dict") {
		t.Errorf("a partial name was refused without naming what it could mean: %q", warned+said)
	}
}

func TestTheWholePrincipleIsPrintedOnlyWhenAsked(t *testing.T) {
	_, short, _ := explained("info", "swallow-catch")
	_, full, _ := explained("info", "swallow-catch", "--full")

	if len(full) <= len(short) {
		t.Errorf("--full printed %d characters and the short answer %d, so it asked for nothing", len(full), len(short))
	}
}

package info

import (
	"bytes"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	_ "github.com/jessegall/code-commandments/registry"
)

// What `info` prints was proved only by the recorded parity cases, which run the built binary against the removed
// PHP tool's output. The command reads the registered rules and writes to a console, so it answers in process, and
// what it owes a reader is held here: the rule it was asked about, the skill that teaches the fix, and a plain
// answer when the name matches nothing or more than one thing.

// explained is what `info` wrote for the arguments, and the code it ended on.
func explained(args ...string) (code int, said string, warned string) {
	var out, errs bytes.Buffer
	code, _ = Command{}.Run(cli.FromArgs(args), cli.Console{Out: &out, Err: &errs})

	return code, out.String(), errs.String()
}

// TestASinIsExplainedByItsOwnNameAndItsDetectors holds the two ways a reader arrives: off a checklist, which names
// the sin, and off a finding, which names the detector. Both must reach the same rule, since the reader does not
// know which of the two they are holding.
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

// TestANameThatMatchesNothingSaysSoAndExitsTwo holds the answer to a name nobody has: a reader who mistypes a sin
// off a checklist gets told, rather than an empty screen that reads as "no such problem".
func TestANameThatMatchesNothingSaysSoAndExitsTwo(t *testing.T) {
	code, said, warned := explained("info", "zz-no-such-rule")

	if code == 0 {
		t.Errorf("a name nobody has was explained as if it were a rule:\n%s", said)
	}
	if !strings.Contains(warned+said, "zz-no-such-rule") {
		t.Errorf("it does not name what was asked for: %q", warned+said)
	}
}

// TestNoNameAtAllSaysWhatToType holds the usage path, which is the only thing a reader sees when they type the
// verb alone.
func TestNoNameAtAllSaysWhatToType(t *testing.T) {
	code, _, warned := explained("info")

	if code != 2 {
		t.Errorf("exits %d, want 2", code)
	}
	if !strings.Contains(warned, "info") {
		t.Errorf("it says %q, which does not say what to type", warned)
	}
}

// TestAPartialNameNamesEveryRuleItCouldMean holds what `info python-dict` owes: a name that is the start of several
// rules is ambiguous, and the answer is the list of them rather than one of them chosen silently.
func TestAPartialNameNamesEveryRuleItCouldMean(t *testing.T) {
	code, said, warned := explained("info", "python-dict")

	if code == 0 && !strings.Contains(said, "python-dict") {
		t.Errorf("a partial name was answered without naming what it could mean:\n%s", said)
	}
	if code != 0 && !strings.Contains(warned+said, "python-dict") {
		t.Errorf("a partial name was refused without naming what it could mean: %q", warned+said)
	}
}

// TestTheWholePrincipleIsPrintedOnlyWhenAsked holds --full to being more than the short answer: the opening of the
// skill is what a reader holding a finding wants, and the whole principle is what they ask for when it is not enough.
func TestTheWholePrincipleIsPrintedOnlyWhenAsked(t *testing.T) {
	_, short, _ := explained("info", "swallow-catch")
	_, full, _ := explained("info", "swallow-catch", "--full")

	if len(full) <= len(short) {
		t.Errorf("--full printed %d characters and the short answer %d, so it asked for nothing", len(full), len(short))
	}
}

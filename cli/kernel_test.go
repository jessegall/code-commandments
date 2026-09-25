package cli

import (
	"bytes"
	"errors"
	"slices"
	"testing"

	"github.com/jessegall/code-commandments/cli/help"
)

type toy struct {
	names []string
	err   error
	ran   *Input
}

func (c *toy) Names() []string { return c.names }

func (c *toy) Help() help.Help {
	return help.Of("A toy.").Form(c.names[0]).Option("--dry-run[=FILE]", "preview")
}

func (c *toy) Run(in *Input, console Console) (int, error) {
	c.ran = in

	return console.Say("ran"), c.err
}

func run(kernel *Kernel, args ...string) (int, string, string) {
	var out, err bytes.Buffer
	code := kernel.Run(args, Console{Out: &out, Err: &err})

	return code, out.String(), err.String()
}

func TestNoVerbRunsJudge(t *testing.T) {
	judge := &toy{names: []string{"judge"}}

	if code, out, _ := run(NewKernel("dev", judge)); code != 0 || out != "ran\n" || judge.ran == nil {
		t.Errorf("exit %d, out %q", code, out)
	}
}

func TestAnUndeclaredFlagIsRefusedNamingTheVerb(t *testing.T) {
	code, _, err := run(NewKernel("dev", &toy{names: []string{"make"}}), "make", "--bogus")

	if code != 2 || err != "Unknown option --bogus for `make`. Try: commandments make --help\n" {
		t.Errorf("exit %d, err %q", code, err)
	}
}

func TestAnOptionalValuedFlagIsDeclaredByItsName(t *testing.T) {
	if code, _, err := run(NewKernel("dev", &toy{names: []string{"make"}}), "make", "--dry-run=out.diff"); code != 0 {
		t.Errorf("exit %d, err %q", code, err)
	}
}

func TestHelpAfterAVerbPrintsItsPageAndAnAliasFindsIt(t *testing.T) {
	kernel := NewKernel("dev", &toy{names: []string{"disable", "enable"}})
	_, page, _ := run(kernel, "disable", "--help")

	for _, args := range [][]string{{"help", "enable"}, {"enable", "-h"}} {
		if _, out, _ := run(kernel, args...); out != page {
			t.Errorf("%v printed %q", args, out)
		}
	}
}

func TestAnInvalidConfigurationIsSaidInASentenceWithExitTwo(t *testing.T) {
	kernel := NewKernel("dev", &toy{names: []string{"judge"}, err: &InvalidConfiguration{Reason: "no such sin"}})

	if code, _, err := run(kernel, "judge"); code != 2 || err != "✗ .commandments/config.php: no such sin\n" {
		t.Errorf("exit %d, err %q", code, err)
	}
}

func TestAnyOtherFailureIsSaidWithExitOne(t *testing.T) {
	kernel := NewKernel("dev", &toy{names: []string{"judge"}, err: errors.New("boom")})

	if code, _, err := run(kernel, "judge"); code != 1 || err != "✗ boom\n" {
		t.Errorf("exit %d, err %q", code, err)
	}
}

func TestInputSortsTheTail(t *testing.T) {
	in := FromArgs([]string{"report", "src", "--ref=a:1", "--global", "--ref=b:2", "--exclude=a,,0,b"})
	value, _ := in.Option("ref")
	_, valued, given := in.Optional("global")

	switch {
	case in.Command() != "report":
		t.Errorf("command %q", in.Command())
	case !slices.Equal(in.Arguments(), []string{"src"}):
		t.Errorf("arguments %v", in.Arguments())
	case value != "b:2" || !slices.Equal(in.Repeated("ref"), []string{"a:1", "b:2"}):
		t.Errorf("ref %q, %v", value, in.Repeated("ref"))
	case valued || !given:
		t.Errorf("--global valued %v given %v", valued, given)
	case !slices.Equal(in.List("exclude"), []string{"a", "b"}):
		t.Errorf("exclude %v", in.List("exclude"))
	case !slices.Equal(in.Given(), []string{"ref", "exclude", "global"}):
		t.Errorf("given %v", in.Given())
	}
}

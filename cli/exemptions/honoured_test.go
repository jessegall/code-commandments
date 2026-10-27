package exemptions

import (
	"bytes"
	"strings"
	"testing"

	"github.com/jessegall/code-commandments/cli"
	"github.com/jessegall/code-commandments/engine/php/packages"
	_ "github.com/jessegall/code-commandments/registry"
)

// An exemption is how a package quiets a general rule on its own boundary types, and what this command prints is
// the only place a reader sees the whole set. cli/exemptions had no test at all: the six recorded parity cases ran
// the built binary.

// listed is what the command printed for the arguments, and the code it ended on.
func listed(t *testing.T, args ...string) (code int, said string) {
	t.Helper()
	var out, errs bytes.Buffer
	code, err := Command{}.Run(cli.InputOf("exemptions", args...), cli.Console{Out: &out, Err: &errs})
	if err != nil {
		t.Fatalf("the command failed: %v", err)
	}

	return code, out.String() + errs.String()
}

// TestEveryRegisteredTagIsListed holds the whole set: a tag a package registers and the list does not print is an
// exemption nobody can discover, and the rule it quiets then reads as simply not firing.
func TestEveryRegisteredTagIsListed(t *testing.T) {
	if len(packages.Tags) == 0 {
		t.Fatal("no package registers an exemption, so this list has nothing to be right about")
	}

	code, said := listed(t)

	if code != 0 {
		t.Fatalf("exits %d, saying %q", code, said)
	}

	for _, tag := range packages.Tags {
		if !strings.Contains(said, tag.Slug) {
			t.Errorf("the list does not name the %s exemption", tag.Slug)
		}
	}
}

// TestANameNobodyHasIsRefusedAndSaysWhereToLook holds the mistyped rule: the reader is told, rather than shown an
// empty answer that reads as a rule honouring nothing.
func TestANameNobodyHasIsRefusedAndSaysWhereToLook(t *testing.T) {
	code, said := listed(t, "zz-no-such-rule")

	if code != 2 {
		t.Errorf("exits %d, want 2", code)
	}
	if !strings.Contains(said, "zz-no-such-rule") {
		t.Errorf("it says %q, which does not name what was asked for", said)
	}
	if !strings.Contains(said, "--list") {
		t.Errorf("it says %q, which does not say where to look", said)
	}
}

// TestARuleThatHonoursNoneSaysSoRatherThanNothing holds the difference a reader depends on: a rule that honours no
// exemption says so, where an empty answer reads as a question that was never understood.
func TestARuleThatHonoursNoneSaysSoRatherThanNothing(t *testing.T) {
	code, said := listed(t, "deep-nesting")

	if code != 0 {
		t.Fatalf("exits %d, saying %q", code, said)
	}
	if strings.TrimSpace(said) == "" {
		t.Fatal("it printed nothing at all")
	}
	if !strings.Contains(said, "honours") {
		t.Errorf("it says %q, which does not say what the rule honours", said)
	}
}

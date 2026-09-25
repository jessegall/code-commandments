package help

import (
	"bytes"
	"os"
	"testing"
)

type command struct {
	names []string
	help  Help
}

func (c command) Names() []string { return c.names }
func (c command) Help() Help      { return c.help }

// widget and bare are the commands testdata/page.txt and testdata/overview.txt were rendered from by the
// PHP HelpScreen.
var (
	widget = command{[]string{"widget", "widgets"}, Of("Tend the widgets — every one of them, in the order they were made, with a summary long enough to wrap past the width.").
		Form("widget", "the list").
		Form(`widget add "<title>" --under=<id> [--priority=NUMBER] --and-a-very-long-form`, "queue one — a subtask when --under names its parent, which carries its number").
		Form("widget show <id>").
		Option("--under=<id>", "the parent").
		Option("--dry-run[=FILE]", "preview a unified diff instead of applying it; with FILE, write the diff there rather than to the terminal").
		Note("A widget is a small thing. This note is long enough that it has to wrap onto a second line when the screen lays it out at the classic terminal width.").
		Note("Short note.")}
	bare = command{[]string{"bare"}, Of("Nothing else.").Form("bare").In(Hooks)}
)

func TestAPageLaysOutAsThePhpScreenDid(t *testing.T) {
	assertRendered(t, "testdata/page.txt", NewScreen([]Documented{widget, bare}).Page(widget))
}

func TestTheOverviewLaysOutAsThePhpScreenDid(t *testing.T) {
	assertRendered(t, "testdata/overview.txt", NewScreen([]Documented{widget, bare}).Overview())
}

func TestUsageSaysWhyThenThePageOnErrWithExitTwo(t *testing.T) {
	var err bytes.Buffer

	if code := Usage(&err, bare, "no widget named"); code != 2 {
		t.Errorf("exit %d", code)
	}

	if want := "✗ no widget named\n\n" + NewScreen(nil).Page(bare); err.String() != want {
		t.Errorf("usage:\n%s", err.String())
	}
}

func TestAFlagIsNamedAsTyped(t *testing.T) {
	for spec, want := range map[string]string{"--branch[=BASE]": "branch", "--last=N": "last", "--list": "list"} {
		if got := NameOf(spec); got != want {
			t.Errorf("NameOf(%q) = %q", spec, got)
		}
	}
}

func assertRendered(t *testing.T, golden, got string) {
	t.Helper()

	want, err := os.ReadFile(golden)
	if err != nil {
		t.Fatal(err)
	}

	if got != string(want) {
		t.Errorf("differs from %s\n--- want\n%s\n--- got\n%s", golden, want, got)
	}
}

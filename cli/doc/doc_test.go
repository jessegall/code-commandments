package doc

import (
	"errors"
	"os"
	"testing"

	"github.com/jessegall/code-commandments/cli/commands"
)

func TestTheSkillsCommandBlocksAreWhatTheGoHelpProjects(t *testing.T) {
	for _, path := range []string{"../../skills/writing-detectors/SKILL.md"} {
		raw, err := os.ReadFile(path)
		if err != nil {
			t.Fatal(err)
		}

		refreshed, err := Refresh(string(raw), commands.Kernel("dev"))

		if err != nil || refreshed != string(raw) {
			t.Errorf("%s would change when projected from the Go help (err %v)", path, err)
		}
	}
}

func TestAMarkerThatCannotBeTrustedIsRefused(t *testing.T) {
	for document, reason := range map[string]string{
		"<!-- END: commands:x -->\n":                                 "it has an END marker with no BEGIN",
		"<!-- BEGIN: commands:x (a) -->\n":                           "it has a BEGIN marker with no END",
		"<!-- END: commands:x -->\n<!-- BEGIN: commands:x (a) -->\n": "its END marker stands above its BEGIN",
	} {
		_, _, err := Replace(document, "commands:x", "y")

		var malformed *Malformed
		if !errors.As(err, &malformed) || malformed.Reason != reason {
			t.Errorf("%q: %v", document, err)
		}
	}
}

// overviewPending names what the README's command table still waits on from the Go binary.
const overviewPending = "ticket 8's repent and hints, ticket 10's sync, install and hook commands"

func TestTheReadmeCommandTableIsWhatTheGoHelpProjects(t *testing.T) {
	raw, err := os.ReadFile("../../README.md")
	if err != nil {
		t.Fatal(err)
	}

	projected, found, err := Replace(string(raw), "commands-table", "\n"+Overview(commands.Kernel("dev"))+"\n")
	if err != nil || !found {
		t.Fatalf("no commands-table block: %v", err)
	}

	if projected == string(raw) {
		t.Fatalf("the README table matches the Go help now: drop overviewPending (%s)", overviewPending)
	}

	t.Skipf("pending: %s", overviewPending)
}

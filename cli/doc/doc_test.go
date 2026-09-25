package doc

import (
	"errors"
	"github.com/jessegall/code-commandments/cli/block"
	"os"
	"testing"

	"github.com/jessegall/code-commandments/cli/commands"
)

func TestTheSkillsCommandBlocksAreWhatTheGoHelpProjects(t *testing.T) {
	documents, err := DocumentsIn("../../skills")
	if err != nil || len(documents) == 0 {
		t.Fatalf("no skill documents: %v", err)
	}

	for _, path := range documents {
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
		_, _, err := block.Replace(document, "commands:x", "y")

		var malformed *block.Malformed
		if !errors.As(err, &malformed) || malformed.Reason != reason {
			t.Errorf("%q: %v", document, err)
		}
	}
}

func TestTheReadmeCommandTableIsWhatTheGoHelpProjects(t *testing.T) {
	raw, err := os.ReadFile("../../README.md")
	if err != nil {
		t.Fatal(err)
	}

	projected, found, err := block.Replace(string(raw), "commands-table", "\n"+Overview(commands.Kernel("dev")))
	if err != nil || !found {
		t.Fatalf("no commands-table block: %v", err)
	}

	if projected != string(raw) {
		t.Error("the README's command table differs from the Go help's")
	}
}

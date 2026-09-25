package doc

import (
	"errors"
	"os"
	"slices"
	"strings"
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
		_, _, err := Replace(document, "commands:x", "y")

		var malformed *Malformed
		if !errors.As(err, &malformed) || malformed.Reason != reason {
			t.Errorf("%q: %v", document, err)
		}
	}
}

// awaited are the verbs the README's table lists that ticket 10 brings to the Go binary: sync, install and
// the hook commands.
var awaited = []string{
	"sync", "install", "judge-reminder", "hooks", "journal-hook", "journal-serve", "journal-config",
	"journal-scan", "journal-skills", "hook",
}

func TestTheReadmeCommandTableIsWhatTheGoHelpProjects(t *testing.T) {
	raw, err := os.ReadFile("../../README.md")
	if err != nil {
		t.Fatal(err)
	}

	overview := Overview(commands.Kernel("dev"))

	for _, verb := range awaited {
		if strings.Contains(overview, "| `commandments "+verb+"`") || strings.Contains(overview, "| `commandments "+verb+" ") {
			t.Errorf("the Go binary documents %s now: drop it from awaited", verb)
		}
	}

	projected, found, err := Replace(withoutAwaited(string(raw)), "commands-table", "\n"+overview)
	if err != nil || !found {
		t.Fatalf("no commands-table block: %v", err)
	}

	if projected != withoutAwaited(string(raw)) {
		t.Error("the README's command table differs from the Go help's, beyond ticket 10's verbs")
	}
}

// withoutAwaited is the document less the table rows of the verbs ticket 10 brings.
func withoutAwaited(document string) string {
	var kept []string

	for _, line := range strings.Split(document, "\n") {
		if !slices.ContainsFunc(awaited, func(verb string) bool {
			return strings.HasPrefix(line, "| `commandments "+verb+"`") || strings.HasPrefix(line, "| `commandments "+verb+" ")
		}) {
			kept = append(kept, line)
		}
	}

	return strings.Join(kept, "\n")
}

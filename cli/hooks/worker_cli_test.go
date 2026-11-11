package hooks

import (
	"testing"
)

func TestAWorkerDeniedTheCLIIsNeverToldToRunIt(t *testing.T) {
	denied := Event{Root: t.TempDir(), WithoutCLI: true}

	if said := denied.wholeAnswerBy(func(string) string { return "journal sins" }); said != "" {
		t.Errorf("a worker denied the CLI is told to run %q", said)
	}

	if said, due := Reminder(denied, "before you wrap up"); due {
		t.Errorf("a worker denied the CLI is reminded to judge: %q", said)
	}

	allowed := Event{Root: t.TempDir()}

	if said := allowed.wholeAnswerBy(func(string) string { return "journal sins" }); said != "journal sins" {
		t.Errorf("an agent allowed the CLI is told to run %q", said)
	}
}

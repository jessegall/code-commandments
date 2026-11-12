package hooks

import (
	"strings"
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

func TestAWorkerIsRefusedTheCLIOnlyWhenTheSettingDeniesIt(t *testing.T) {
	cli := "bin/commandments info redundant-else"

	for name, each := range map[string]struct {
		role, command string
		settings      map[string]any
		refused       bool
	}{
		"the setting is left alone":        {role: "worker", command: cli, refused: true},
		"the setting allows it":            {role: "worker", command: cli, settings: map[string]any{workerCLIKey: true}},
		"the setting denies it":            {role: "worker", command: cli, settings: map[string]any{workerCLIKey: false}, refused: true},
		"the setting says false as words":  {role: "senior", command: cli, settings: map[string]any{workerCLIKey: "false"}, refused: true},
		"the main agent is not affected":   {role: "main", command: cli, settings: map[string]any{workerCLIKey: false}},
		"another command is not affected":  {role: "worker", command: "git status", settings: map[string]any{workerCLIKey: false}},
		"judging through the journal":      {role: "worker", command: "journal sins --changes", settings: map[string]any{workerCLIKey: false}, refused: true},
		"after an environment and a chain": {role: "worker", command: "cd x && FOO=1 .journal/plugins/code-commandments/bin/commandments-release judge", settings: map[string]any{workerCLIKey: false}, refused: true},
		"through the journal's alias":      {role: "worker", command: "journal commandments info x", settings: map[string]any{workerCLIKey: false}, refused: true},
		"a project's own rules":            {role: "worker", command: "journal rules try x", settings: map[string]any{workerCLIKey: false}, refused: true},
		"only named in an argument":        {role: "worker", command: "grep commandments README.md", settings: map[string]any{workerCLIKey: false}},
	} {
		t.Run(name, func(t *testing.T) {
			command := each.command
			refusal := cliRefusedTo(Moment{Role: each.role, Command: &command, Settings: each.settings})

			if each.refused != (refusal != "") {
				t.Fatalf("refused with %q, want refused=%v", refusal, each.refused)
			}

			if each.refused && !strings.Contains(refusal, workerCLIKey) {
				t.Errorf("the refusal does not name the setting that made it: %q", refusal)
			}
		})
	}
}

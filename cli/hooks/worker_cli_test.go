package hooks

import (
	"strings"
	"testing"
)

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

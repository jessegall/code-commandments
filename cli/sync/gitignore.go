package sync

import (
	"os"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/cli/agents"
	"github.com/jessegall/code-commandments/cli/atomic"
	"github.com/jessegall/code-commandments/cli/library"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// retiredCommandsIgnored are the root .gitignore lines that once ignored commands the tool no longer ships.
var retiredCommandsIgnored = []string{
	"# code-commandments published slash commands (regenerated on composer update)",
	".claude/commands/until.md",
	".claude/commands/stop-condition.md",
}

// staleRules are ignore rules of ours in a form since retired, removed by exact line so a rule the user
// wrote is never taken for one. A trailing slash on the published-skills rule stopped matching once the
// skills became links, which git records as files.
var staleRules = append([]string{
	".commandments/", ".commandments/*", "!.commandments/config.php", "!.commandments/repent.php",
	".claude/skills/commandments/", ".claude/skills/commandments-*/",
}, retiredCommandsIgnored...)

// ensureGitignored adds to the project's .gitignore every rule the library and the agents need that it
// does not already carry, each under its comment, after dropping our retired forms.
func ensureGitignored(path string, kept []agents.Agent) error {
	text, _ := os.ReadFile(path)

	existing := strings.Join(slices.DeleteFunc(strings.Split(string(text), "\n"), func(line string) bool {
		return slices.Contains(staleRules, strings.TrimSpace(line))
	}), "\n")

	entries := []agents.Ignored{{Comment: "# code-commandments skill library (regenerated on composer update)", Rule: library.Dir + "/commandments-*/"}}
	for _, agent := range kept {
		entries = append(entries, agent.Ignored()...)
	}

	for _, entry := range entries {
		if strings.Contains(existing, entry.Rule) {
			continue
		}

		if existing != "" && !strings.HasSuffix(existing, "\n") {
			existing += "\n"
		}

		existing += "\n" + entry.Comment + "\n" + entry.Rule + "\n"
	}

	if existing == "" {
		return nil
	}

	return atomic.Write(path, existing)
}

// retiredLines are lines the tool once wrote into .commandments/.gitignore, dropped whenever it is rewritten.
var retiredLines = []string{
	"# A session's PLAN is tracked; everything else in its folder is this run's own state.",
	"!sessions/*/plan/",
	"!sessions/*/plan/**",
	"!orchestrator/",
	"!orchestrator/**",
}

// commandmentsLines ignore everything the tool generates in .commandments while keeping the project's own
// there tracked: the config in either form, its custom rules and each session's tasks.
func commandmentsLines() []string {
	return []string{
		"# code-commandments generated state; the lines below stay tracked.",
		"# Add your own exceptions underneath — they are preserved across a sync.",
		"*",
		"!.gitignore",
		"!config.php",
		"!config.json",
		"!" + workspace.Custom + "/",
		"!" + workspace.Custom + "/**",
		"",
		"# A session's TASKS are tracked; everything else in its folder is this run's own state.",
		"# Un-ignoring something nested takes a line per level, so these four are one rule.",
		"!" + workspace.Sessions + "/",
		"!" + workspace.Sessions + "/*/",
		"!" + workspace.Sessions + "/*/tasks/",
		"!" + workspace.Sessions + "/*/tasks/**",
	}
}

// ensureCommandmentsGitignore writes .commandments/.gitignore: our lines, then every line the project added.
func ensureCommandmentsGitignore(root string) error {
	path := workspace.At(root, "").Shared(".gitignore")
	content := strings.Join(append(commandmentsLines(), linesTheProjectAdded(path)...), "\n") + "\n"

	if current, err := os.ReadFile(path); err == nil && string(current) == content {
		return nil
	}

	return atomic.Write(path, content)
}

func linesTheProjectAdded(path string) []string {
	text, err := os.ReadFile(path)
	if err != nil {
		return nil
	}

	var theirs []string

	for _, line := range strings.Split(string(text), "\n") {
		kept := strings.TrimSpace(line)

		if kept == "" || isOurLine(kept) {
			continue
		}

		theirs = append(theirs, kept)
	}

	return theirs
}

func isOurLine(line string) bool {
	return slices.Contains(commandmentsLines(), line) || slices.Contains(retiredLines, line) ||
		strings.HasPrefix(line, "# code-commandments")
}

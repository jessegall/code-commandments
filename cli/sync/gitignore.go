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

var retiredCommandsIgnored = []string{
	"# code-commandments published slash commands (regenerated on composer update)",
	".claude/commands/until.md",
	".claude/commands/stop-condition.md",
}

var staleRules = append([]string{
	".commandments/", ".commandments/*", "!.commandments/config.php", "!.commandments/repent.php",
	"!.commandments/config.json",
	".claude/skills/commandments/", ".claude/skills/commandments-*/",
}, retiredCommandsIgnored...)

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

var retiredLines = []string{
	"# A session's PLAN is tracked; everything else in its folder is this run's own state.",
	"!sessions/*/plan/",
	"!sessions/*/plan/**",
	"!orchestrator/",
	"!orchestrator/**",
	"!reports.json",
	"# A session's TASKS are tracked; everything else in its folder is this run's own state.",
	"# Un-ignoring something nested takes a line per level, so these four are one rule.",
	"!sessions/",
	"!sessions/*/",
	"!sessions/*/tasks/",
	"!sessions/*/tasks/**",
	"!config.php",
	"!config.json",
}

func commandmentsLines() []string {
	return []string{
		"# code-commandments generated state; only the detectors in custom/ stay tracked.",
		"# The config and the cache are read at the project root; no checkout carries a copy.",
		"# Add your own exceptions underneath — they are preserved across a sync.",
		"*",
		"!.gitignore",
		"!" + workspace.Custom + "/",
		"!" + workspace.Custom + "/**",
	}
}

func detectorsStayTracked() []string {
	return []string{
		"",
		"# code-commandments: the detectors in custom/ stay tracked whatever the lines above say.",
		"!" + workspace.Custom + "/",
		"!" + workspace.Custom + "/**",
	}
}

func ensureCommandmentsGitignore(root string) error {
	path := workspace.At(root, "").Shared(".gitignore")
	lines := append(append(commandmentsLines(), linesTheProjectAdded(path)...), detectorsStayTracked()...)
	content := strings.Join(lines, "\n") + "\n"

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

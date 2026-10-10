package agents

import (
	_ "embed"
	"strings"

	"github.com/jessegall/code-commandments/cli/hooks"
	"github.com/jessegall/code-commandments/cli/workspace"
)

// ClaudeBlock names the block sync keeps in CLAUDE.md.
const ClaudeBlock = "code-commandments skills"

//go:embed claude_block.md
var claudeInstructions string

// enforcedByHooks says what enforces the disciplines where the package's own hooks are wired.
//
//go:embed claude_enforced_hooks.md
var enforcedByHooks string

// enforcedByJournal says it where the agent journal's plugin runs them and the package's hooks step aside.
//
//go:embed claude_enforced_journal.md
var enforcedByJournal string

// Claude is Claude Code: per-skill links in .claude/skills, a CLAUDE.md that imports AGENTS.md, and the
// hooks, which make it the one agent whose disciplines are enforced rather than only written down.
type Claude struct {
	base
}

func (Claude) Class() string { return "ClaudeAgent" }
func (Claude) Name() string  { return "Claude Code" }
func (Claude) Summary() string {
	return "skills, `CLAUDE.md` (imports `AGENTS.md`), and the hooks — the only agent whose disciplines are enforced rather than only written down"
}
func (Claude) SkillsDir() string        { return ".claude/skills" }
func (Claude) CommandsDir() string      { return ".claude/commands" }
func (Claude) InstructionsFile() string { return "CLAUDE.md" }
func (Claude) BlockName() string        { return ClaudeBlock }
func (Claude) Instructions(root string) string {
	enforced := enforcedByHooks
	if workspace.At(root, "").IsJournalDriven() {
		enforced = enforcedByJournal
	}

	return strings.Replace(claudeInstructions, "{{enforced}}", enforced, 1)
}
func (Claude) Enforces() bool           { return true }

func (Claude) Ignored() []Ignored {
	return []Ignored{{"# code-commandments published skills (regenerated on composer update)", ".claude/skills/commandments-*"}}
}

func (Claude) Wire(root string) (bool, error) {
	return hooks.Wire(root)
}

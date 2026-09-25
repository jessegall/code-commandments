package agents

import _ "embed"

// ClaudeBlock names the block sync keeps in CLAUDE.md.
const ClaudeBlock = "code-commandments skills"

//go:embed claude_block.md
var claudeInstructions string

// WireHooks wires the hook suite into a project for Claude Code; the hook package installs it.
var WireHooks = func(root string) bool { return false }

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
func (Claude) Instructions() string     { return claudeInstructions }
func (Claude) Enforces() bool           { return true }

func (Claude) Ignored() []Ignored {
	return []Ignored{{"# code-commandments published skills (regenerated on composer update)", ".claude/skills/commandments-*"}}
}

func (Claude) Wire(root string) bool {
	return WireHooks(root)
}

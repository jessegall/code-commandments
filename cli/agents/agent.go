// Package agents points every assistant a project uses at the one skill library: an Agent states only what
// actually differs between them, the folder it discovers skills in, the file it reads instructions from,
// and whether it enforces the disciplines through hooks.
package agents

import (
	"slices"

	"github.com/jessegall/code-commandments/cli/config"
)

// Agent is one assistant sync wires the project for.
type Agent interface {
	// Class is the agent's name as a config turns it off by.
	Class() string
	// Name is the agent as a person names it.
	Name() string
	// Summary is what sync gives it, in a phrase.
	Summary() string
	// SkillsDir is the folder it discovers skills in, under the project; empty when it reads the library.
	SkillsDir() string
	// CommandsDir is the folder it reads commands from, under the project; empty when it has none.
	CommandsDir() string
	// InstructionsFile is the file it reads instructions from; empty when AGENTS.md is its own.
	InstructionsFile() string
	// BlockName names the block sync keeps in that file.
	BlockName() string
	// Instructions are what that block says.
	Instructions() string
	// Ignored are the lines it adds to the project's .gitignore, each under its comment.
	Ignored() []Ignored
	// Enforces says whether it checks the disciplines through hooks rather than only reading them.
	Enforces() bool
	// Wire wires what else the agent needs into the project; false when nothing changed.
	Wire(root string) (bool, error)
}

// Ignored is one .gitignore rule under the comment that says why it is there.
type Ignored struct {
	Comment, Rule string
}

// All is every agent the tool ships, in the order their classes sort.
func All() []Agent {
	return []Agent{Claude{}, Codex{}}
}

// ForProject is every agent the project keeps: the shipped ones less those its config turns off.
func ForProject(project config.Config) []Agent {
	return slices.DeleteFunc(All(), func(agent Agent) bool {
		return project.Disables(config.Rule{Kind: config.Agent, Name: agent.Class()})
	})
}

// base answers what an agent that differs in nothing answers.
type base struct{}

func (base) SkillsDir() string         { return "" }
func (base) CommandsDir() string       { return "" }
func (base) InstructionsFile() string  { return "" }
func (base) BlockName() string         { return "" }
func (base) Instructions() string      { return "" }
func (base) Ignored() []Ignored        { return nil }
func (base) Enforces() bool            { return false }
func (base) Wire(string) (bool, error) { return false, nil }

// Codex reads the library and AGENTS.md where they already live.
type Codex struct {
	base
}

func (Codex) Class() string { return "CodexAgent" }
func (Codex) Name() string  { return "Codex" }
func (Codex) Summary() string {
	return "skills and `AGENTS.md`, both read where they already live — no links, no hooks"
}

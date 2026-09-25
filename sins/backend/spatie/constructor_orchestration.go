package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// ConstructorOrchestration is the constructor-orchestration sin.
type ConstructorOrchestration struct{}

func init() { sins.Register(catalog.Backend, ConstructorOrchestration{}) }

// Definition is what the sin states about itself.
func (ConstructorOrchestration) Definition() sins.Definition {
	return sins.Definition{
		Name:        "constructor-orchestration",
		Skill:       spatieskills.PageObjects{},
		Description: `A page object fills a public slot imperatively in the constructor (` + "`" + `$this->x = $this->projector->…()` + "`" + `) where a ` + "`" + `#[Computed]` + "`" + ` property hook would describe it in place`,
		Rule:        `Project each self-contained page-object slot in a ` + "`" + `#[Computed]` + "`" + ` get-hook, not an imperative constructor assignment.`,
		Suggestion:  `Replace ` + "`" + `$this->x = expr;` + "`" + ` with ` + "`" + `#[Computed] public T $x { get => expr; }` + "`" + `. Pin a deliberately-eager slot (one that must capture request-scoped state at build time) with ` + "`" + `#[Eager]` + "`" + ` — the scaffolded escape hatch.`,
		Requires:    requiresSpatieData,
	}
}

// Scaffolds are the helpers the fix reaches for, which scaffold generates into the project.
func (ConstructorOrchestration) Scaffolds() []sins.Scaffold {
	return []sins.Scaffold{
		{Path: "Support/Eager.php", Stub: "Eager.php.stub"},
	}
}

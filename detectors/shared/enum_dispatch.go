package shared

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

// SideTableTrait finds a separate list repeats which cases have a trait.
type SideTableTrait struct{}

func (SideTableTrait) Find(codebase *engine.Codebase) []engine.Match {
	return engine.SideTables(codebase)
}
func (SideTableTrait) Sin() Text {
	return Text{Name: "side-table-trait", Skill: skills.Enums{}, Description: "A separate list repeats which cases have a trait", Rule: "Keep the trait beside the cases it describes.", Suggestion: "Keep the trait beside the cases it describes.", Formerly: map[catalog.Engine]Former{catalog.CSharp: {Sin: "csharp-side-table-trait"}, catalog.Python: {Sin: "python-side-table-trait"}, catalog.TypeScript: {Sin: "typescript-side-table-trait"}}}
}
func (SideTableTrait) CrossFile() {}

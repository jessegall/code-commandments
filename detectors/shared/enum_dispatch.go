package shared

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

// TypeSwitch finds dispatch branches test the same value against several concrete types.
type TypeSwitch struct{}

func (TypeSwitch) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(engine.Match.SwitchesOnType).Get()
}
func (TypeSwitch) Sin() Text {
	return Text{Name: "type-switch", Skill: skills.Enums{}, Description: "Dispatch branches test the same value against several concrete types", Rule: "Put the behavior on the type that owns it.", Suggestion: "Put the behavior on the type that owns it.", Formerly: map[catalog.Engine]Former{catalog.CSharp: {Sin: "csharp-type-switch"}, catalog.Python: {Sin: "python-type-switch"}}}
}
func (TypeSwitch) CrossFile() {}

// ConstClassEnum finds a class of constants defines a closed set of cases.
type ConstClassEnum struct{}

func (ConstClassEnum) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereTypeDeclaration().Where(engine.Match.IsConstClassEnum).Get()
}
func (ConstClassEnum) Sin() Text {
	return Text{Name: "const-class-enum", Skill: skills.Enums{}, Description: "A class of constants defines a closed set of cases", Rule: "Declare the cases as an enum.", Suggestion: "Declare the cases as an enum.", Formerly: map[catalog.Engine]Former{catalog.CSharp: {Sin: "csharp-const-class-enum"}, catalog.Python: {Sin: "python-const-class-enum"}}}
}
func (ConstClassEnum) CrossFile() {}

// SideTableTrait finds a separate list repeats which cases have a trait.
type SideTableTrait struct{}

func (SideTableTrait) Find(codebase *engine.Codebase) []engine.Match {
	return engine.SideTables(codebase)
}
func (SideTableTrait) Sin() Text {
	return Text{Name: "side-table-trait", Skill: skills.Enums{}, Description: "A separate list repeats which cases have a trait", Rule: "Keep the trait beside the cases it describes.", Suggestion: "Keep the trait beside the cases it describes.", Formerly: map[catalog.Engine]Former{catalog.CSharp: {Sin: "csharp-side-table-trait"}, catalog.Python: {Sin: "python-side-table-trait"}, catalog.TypeScript: {Sin: "typescript-side-table-trait"}}}
}
func (SideTableTrait) CrossFile() {}

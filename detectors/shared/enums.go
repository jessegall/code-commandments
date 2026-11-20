package shared

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

func mirrorsEnum(literals []string, codebase *engine.Codebase) bool {
	return len(literals) >= 2 && engine.EnumsOf(codebase).MirroredBy(literals)
}

// EnumCaseOrChain finds an or-chain asks about several cases of one enum.
type EnumCaseOrChain struct{}

func (EnumCaseOrChain) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(func(match engine.Match) bool { return match.EnumChainOwner() != "" }).Get()
}
func (EnumCaseOrChain) Sin() Text {
	return Text{Name: "enum-case-or-chain", Skill: skills.Enums{}, Description: "An or-chain asks about several cases of one enum", Rule: "Give that group of cases a named test on the enum.", Suggestion: "Give that group of cases a named test on the enum.", Formerly: map[catalog.Engine]Former{catalog.Python: {Sin: "python-enum-case-or-chain"}, catalog.CSharp: {Sin: "csharp-enum-case-or-chain"}}}
}
func (EnumCaseOrChain) CrossFile() {}

// InArrayMirrorsEnum finds a literal membership check repeats declared enum values.
type InArrayMirrorsEnum struct{}

func (InArrayMirrorsEnum) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(func(match engine.Match) bool { return mirrorsEnum(match.EnumMembership(), codebase) }).Get()
}
func (InArrayMirrorsEnum) Sin() Text {
	return Text{Name: "in-array-mirrors-enum", Skill: skills.Enums{}, Description: "A literal membership check repeats declared enum values", Rule: "Use the named cases or a named group of cases.", Suggestion: "Use the named cases or a named group of cases.", Formerly: map[catalog.Engine]Former{catalog.Python: {Sin: "python-in-literals-mirrors-enum", Detector: "InLiteralsMirrorsEnumDetector"}, catalog.CSharp: {Sin: "csharp-in-array-mirrors-enum"}}}
}
func (InArrayMirrorsEnum) CrossFile() {}

// StringMatchMirrorsEnum finds a switch repeats literal values already declared as cases.
type StringMatchMirrorsEnum struct{}

func (StringMatchMirrorsEnum) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.Where(func(match engine.Match) bool { return mirrorsEnum(match.EnumArms(), codebase) }).Get()
}
func (StringMatchMirrorsEnum) Sin() Text {
	return Text{Name: "string-match-mirrors-enum", Skill: skills.Enums{}, Description: "A switch repeats literal values already declared as cases", Rule: "Dispatch on the declared type and its named cases.", Suggestion: "Dispatch on the declared type and its named cases.", Formerly: map[catalog.Engine]Former{catalog.Python: {Sin: "python-string-match-mirrors-enum"}, catalog.CSharp: {Sin: "csharp-string-match-mirrors-enum"}}}
}
func (StringMatchMirrorsEnum) CrossFile() {}

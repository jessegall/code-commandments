package shared

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

// ConvertedArgument finds callers that repeatedly convert a value for the same parameter.
type ConvertedArgument struct{}

func (ConvertedArgument) Find(codebase *engine.Codebase) []engine.Match {
	calls := codebase.Where(func(match engine.Match) bool { return match.Is(engine.Call) || match.Is(engine.Construction) }).Get()
	return engine.ConvertedCalls(calls, engine.Match.ConversionEvidence)
}

func (ConvertedArgument) GroupKey(call engine.Match) (string, bool) {
	key := call.ConversionEvidence().Key
	return key, key != ""
}

func (ConvertedArgument) Sin() Text {
	return Text{
		Name:        "converted-argument",
		Skill:       skills.Duplication{},
		Description: "Most callers convert a value the same way for one parameter",
		Rule:        "Accept the value the callers already have and let the callee own the conversion.",
		Suggestion:  "Take the original typed value and convert it where it is used.",
		Formerly: map[catalog.Engine]Former{
			catalog.CSharp: {Sin: "csharp-converted-argument"},
			catalog.Python: {Sin: "python-converted-argument"},
		},
	}
}

func (ConvertedArgument) CrossFile() {}

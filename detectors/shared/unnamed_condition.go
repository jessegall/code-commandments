package shared

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

// UnnamedCondition finds a condition that asks several things of several objects without naming the decision.
type UnnamedCondition struct{}

func (UnnamedCondition) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereIs(engine.Conjunction).
		Where(engine.TestsAnUnnamedConcept).
		Get()
}

func (UnnamedCondition) Sin() Text {
	return Text{
		Name:        "unnamed-condition",
		Skill:       skills.Flow{},
		Description: "A condition of three or more parts over two or more objects whose decision has no name",
		Rule:        "Give a compound condition a name that says what it decides.",
		Suggestion:  "Put the condition on the type that owns the decision, or give a template condition a named computed value.",
		Formerly: map[catalog.Engine]Former{
			catalog.CSharp: {Sin: "csharp-unnamed-condition"},
			catalog.Python: {Sin: "python-unnamed-condition"},
		},
	}
}

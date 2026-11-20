package shared

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

// DivergentTwin finds two functions that do the same job but take different steps.
type DivergentTwin struct{}

func (DivergentTwin) Find(codebase *engine.Codebase) []engine.Match {
	return engine.TwinsOf(codebase).Findings()
}

func (DivergentTwin) GroupKey(match engine.Match) (string, bool) {
	return match.TwinGroup()
}

func (DivergentTwin) Sin() Text {
	return Text{
		Name:        "divergent-twin",
		Skill:       skills.Duplication{},
		Description: "Two functions share most of their uncommon steps, but one misses a step the other takes",
		Rule:        "Put the steps that define one job in a single function.",
		Suggestion:  "Extract the shared work, pass the differences as inputs, and call it from both sites.",
		Formerly: map[catalog.Engine]Former{
			catalog.Python: {Sin: "python-divergent-twin"},
		},
	}
}

func (DivergentTwin) CrossFile() {}

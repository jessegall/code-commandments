package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// ladderRungs is how many rungs make a ladder.
const ladderRungs = 4

// SubjectLadderDetector finds an `if` ladder of four or more rungs comparing one subject with a constant: a `switch` written as a ladder.
type SubjectLadderDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, SubjectLadderDetector{})
}

// Sin is the sin the detector finds.
func (SubjectLadderDetector) Sin() sins.Sin {
	return cssins.SubjectLadder{}
}

// Find is every place the sin is committed.
func (SubjectLadderDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		WhereStatement().
		Where(func(held engine.Match) bool { return engine.SubjectLadderLength(held) >= ladderRungs }).
		Get()
}

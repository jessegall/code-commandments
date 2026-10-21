package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// UnnamedConditionDetector finds a condition standing for a concept nobody named.
type UnnamedConditionDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, UnnamedConditionDetector{})
}

// Sin is the sin the detector finds.
func (UnnamedConditionDetector) Sin() sins.Sin {
	return cssins.UnnamedCondition{}
}

// Find is every logical chain a branch decides by that asks something of two or more objects.
func (UnnamedConditionDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		WhereIs(engine.Conjunction).
		Where(engine.TestsAnUnnamedConcept).
		Get()
}

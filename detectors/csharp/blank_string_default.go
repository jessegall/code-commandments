package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// BlankStringDefaultDetector finds a parameter or property defaulted to a blank string that its own scope then asks whether it is blank: a blank standing in for absence.
type BlankStringDefaultDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, BlankStringDefaultDetector{})
}

// Sin is the sin the detector finds.
func (BlankStringDefaultDetector) Sin() sins.Sin {
	return cssins.BlankStringDefault{}
}

// Find is every place the sin is committed.
func (BlankStringDefaultDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		Where(engine.As(cs.Node.IsBlankStringDefault)).
		Where(engine.As(cs.Node.DefaultedNameTestedForBlankness)).
		Get()
}

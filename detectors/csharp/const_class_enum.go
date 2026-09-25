package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// ConstClassEnumDetector finds a class of nothing but one-line string or number constants, compared as cases somewhere: a closed set written as constants.
type ConstClassEnumDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, ConstClassEnumDetector{})
}

// Sin is the sin the detector finds.
func (ConstClassEnumDetector) Sin() sins.Sin {
	return cssins.ConstClassEnum{}
}

// Find is every place the sin is committed.
func (ConstClassEnumDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := cs.In(codebase).Program

	return cs.In(codebase).
		Where(engine.As(cs.Node.IsConstClassEnum)).
		Where(engine.As(func(n cs.Node) bool { return program.ComparesAsACase(n.Symbol()) })).
		Get()
}

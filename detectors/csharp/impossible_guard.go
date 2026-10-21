package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// ImpossibleGuardDetector finds a comparison with null on a value whose own declared type has ruled it out.
type ImpossibleGuardDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, ImpossibleGuardDetector{})
}

// Sin is the sin the detector finds.
func (ImpossibleGuardDetector) Sin() sins.Sin {
	return cssins.ImpossibleGuard{}
}

// Find is every guard a declared type has already ruled out.
func (ImpossibleGuardDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := cs.In(codebase).Program

	return cs.In(codebase).
		Where(engine.As(func(n cs.Node) bool { return n.GuardsWhatTheTypeRulesOut(program.DeclaresType) })).
		Get()
}

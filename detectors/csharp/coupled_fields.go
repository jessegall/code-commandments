package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// CoupledFieldsDetector finds a type holding one value as several of its own fields: value fields assembled or null-checked together, or a field mirroring a sibling's.
type CoupledFieldsDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, CoupledFieldsDetector{})
}

// Sin is the sin the detector finds.
func (CoupledFieldsDetector) Sin() sins.Sin {
	return cssins.CoupledFields{}
}

// Find is every place the sin is committed.
func (CoupledFieldsDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := cs.In(codebase).Program

	return cs.In(codebase).
		WhereType().
		Where(engine.As(func(n cs.Node) bool { return n.HoldsCoupledFields(program) })).
		Get()
}

// WholeTree says the verdict reads beyond the file it judges, so a per-file check must not ask it.
func (CoupledFieldsDetector) WholeTree() {}

package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// FlagArgumentDetector finds a method whose whole body is a two-way branch on one of its own `bool` parameters: two methods sharing one name.
type FlagArgumentDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, FlagArgumentDetector{})
}

// Sin is the sin the detector finds.
func (FlagArgumentDetector) Sin() sins.Sin {
	return cssins.FlagArgument{}
}

// Find is every place the sin is committed.
func (FlagArgumentDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		WhereKind("MethodDeclaration", "LocalFunctionStatement").
		Where(engine.As(cs.Node.SwitchesOnAFlag)).
		Reject(engine.As(cs.Node.IsInherited)).
		Reject(engine.As(cs.Node.IsInTest)).
		Get()
}

package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// GenericThrowDetector finds a `throw` of an exception that names no failure, described in a message written at the throw, outside the tests.
type GenericThrowDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, GenericThrowDetector{})
}

// Sin is the sin the detector finds.
func (GenericThrowDetector) Sin() sins.Sin {
	return cssins.GenericThrow{}
}

// Find is every place the sin is committed.
func (GenericThrowDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		Where(engine.As(cs.Node.IsGenericThrowWithMessage)).
		Reject(engine.As(cs.Node.IsInTest)).
		Get()
}

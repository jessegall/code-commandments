package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// HandedOutCollectionDetector finds a getter that returns the type's own mutable collection, untouched.
type HandedOutCollectionDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, HandedOutCollectionDetector{})
}

func (HandedOutCollectionDetector) Sin() sins.Sin {
	return cssins.HandedOutCollection{}
}

func (HandedOutCollectionDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		Where(engine.As(cs.Node.HandsOutItsOwnCollection)).
		Get()
}

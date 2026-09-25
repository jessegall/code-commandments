package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// NamespaceCycleDetector finds two namespaces that refer to each other, neither nesting the other, at the thinner side of the pair.
type NamespaceCycleDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, NamespaceCycleDetector{})
}

// Sin is the sin the detector finds.
func (NamespaceCycleDetector) Sin() sins.Sin {
	return cssins.NamespaceCycle{}
}

// Find is every place the sin is committed.
func (NamespaceCycleDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.Namespaces(codebase).IndependentArrows().ClosingAMutualPair()
}

// CrossFile says the PHP tool finds it reaching beyond the file it judges, so a per-file check must not ask it.
func (NamespaceCycleDetector) CrossFile() {}

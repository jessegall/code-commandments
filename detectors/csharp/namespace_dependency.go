package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// NamespaceDependencyDetector finds a reference out of a declared layer into a namespace it may not use. The project
// declares its layers; with none declared, it finds nothing.
type NamespaceDependencyDetector struct {
	layers map[string][]string
}

func init() {
	detectors.Register(catalog.CSharp, NamespaceDependencyDetector{})
}

// Layer declares one layer, a namespace, and the namespaces it may use besides itself and what it holds.
func (d NamespaceDependencyDetector) Layer(name string, mayUse ...string) NamespaceDependencyDetector {
	layers := map[string][]string{name: mayUse}
	for declared, uses := range d.layers {
		if declared != name {
			layers[declared] = uses
		}
	}

	return NamespaceDependencyDetector{layers: layers}
}

// Sin is the sin the detector finds.
func (NamespaceDependencyDetector) Sin() sins.Sin {
	return cssins.NamespaceDependency{}
}

// Find is every place the sin is committed: one per file and namespace it reaches, at its first reference.
func (d NamespaceDependencyDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.Namespaces(codebase).LayerViolations(engine.Layers(d.layers, ".", true))
}

// WholeTree says the verdict reads beyond the file it judges, so a per-file check must not ask it.
func (NamespaceDependencyDetector) WholeTree() {}

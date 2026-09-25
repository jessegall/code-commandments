package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// NamespaceDependencyDetector finds an import out of a declared layer into a package it may not use. The project
// declares its layers; with none declared, it finds nothing.
type NamespaceDependencyDetector struct {
	layers map[string][]string
}

func init() {
	detectors.Register(catalog.Python, NamespaceDependencyDetector{})
}

// Layer declares one layer, a dotted package, and the packages it may use besides itself and what it holds.
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
	return pysins.NamespaceDependency{}
}

// Find is every place the sin is committed: one per module and module it reaches, at its first import.
func (d NamespaceDependencyDetector) Find(codebase *engine.Codebase) []engine.Match {
	return matches(py.In(codebase).Program.LayerViolations(engine.Layers(d.layers, ".", true)))
}

// WholeTree says the verdict reads beyond the file it judges, so a per-file check must not ask it.
func (NamespaceDependencyDetector) WholeTree() {}

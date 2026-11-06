package python

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	py "github.com/jessegall/code-commandments/engine/python"
	"github.com/jessegall/code-commandments/sins"
	pysins "github.com/jessegall/code-commandments/sins/python"
)

// NamespaceDependencyDetector finds an import out of a declared layer into a package it may not use.
type NamespaceDependencyDetector struct {
	layers map[string][]string
}

func init() {
	detectors.Register(catalog.Python, NamespaceDependencyDetector{})
}

func (d NamespaceDependencyDetector) Layer(name string, mayUse ...string) NamespaceDependencyDetector {
	layers := map[string][]string{name: mayUse}
	for declared, uses := range d.layers {
		if declared != name {
			layers[declared] = uses
		}
	}

	return NamespaceDependencyDetector{layers: layers}
}

func (NamespaceDependencyDetector) Sin() sins.Sin {
	return pysins.NamespaceDependency{}
}

func (d NamespaceDependencyDetector) Find(codebase *engine.Codebase) []engine.Match {
	return matches(py.In(codebase).Program.LayerViolations(engine.Layers(d.layers, ".", true)))
}

func (NamespaceDependencyDetector) CrossFile() {}

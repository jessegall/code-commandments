package backend

import (
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/namespaces"
	"github.com/jessegall/code-commandments/sins"
	backendsins "github.com/jessegall/code-commandments/sins/backend"
)

// NamespaceDependencyDetector finds a reference out of a declared layer to a layer it did not declare it may use.
type NamespaceDependencyDetector struct {
	layers map[string][]string
}

func init() { detectors.Register(catalog.Backend, NamespaceDependencyDetector{}) }

// Sin is the sin the detector finds.
func (NamespaceDependencyDetector) Sin() sins.Sin { return backendsins.NamespaceDependency{} }

// Layer is the detector with the namespace declared a layer that may use the given ones, as a project's config
// declares its stack.
func (d NamespaceDependencyDetector) Layer(namespace string, mayUse ...string) NamespaceDependencyDetector {
	layers := map[string][]string{strings.Trim(namespace, `\`): mayUse}
	for layer, used := range d.layers {
		if _, redeclared := layers[layer]; !redeclared {
			layers[layer] = used
		}
	}
	d.layers = layers

	return d
}

// Find is one reference per class and class it names, imports aside, from a declared layer into a declared class of
// a layer it may not use; nothing when no stack is declared.
func (d NamespaceDependencyDetector) Find(codebase *engine.Codebase) []engine.Match {
	stack := engine.Layers(d.layers, `\`, false)
	if stack.IsEmpty() {
		return nil
	}
	program := php.ProgramOf(codebase)
	crossings := php.In(codebase).
		Where(engine.As(php.Node.IsClassReference)).
		Where(func(n engine.Match) bool {
			namespace, target := (php.Node{Match: n}).NamespaceName(), n.Name()
			if _, declared := program.Declaration(target); namespace == "" || !declared || stack.LayerOf(target) == "" {
				return false
			}
			from := stack.LayerOf(namespace)

			return from != "" && !stack.MayReference(from, target)
		}).
		Get()

	return namespaces.Distinct(crossings)
}

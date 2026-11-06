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

func init() { detectors.Register(catalog.PHP, NamespaceDependencyDetector{}) }

func (NamespaceDependencyDetector) Sin() sins.Sin { return backendsins.NamespaceDependency{} }

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

func (NamespaceDependencyDetector) CrossFile() {}

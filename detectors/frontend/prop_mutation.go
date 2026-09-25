package frontend

import (
	"slices"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/sins"
	frontend "github.com/jessegall/code-commandments/sins/frontend"
	"github.com/jessegall/code-commandments/typescript"
	"github.com/jessegall/code-commandments/vue"
)

func init() {
	detectors.Register(catalog.Frontend, PropMutationDetector{})
}

// PropMutationDetector finds a template writing one of its component's own props, through a v-model or an
// assignment in a handler. Only a bare prop is a write: a script local of the same name shadows it.
type PropMutationDetector struct{}

func (PropMutationDetector) Sin() sins.Sin {
	return frontend.PropMutation{}
}

func (PropMutationDetector) Find(codebase *engine.Codebase) []engine.Match {
	writable := map[string][]string{}
	propsOf := func(element vue.Element) []string {
		if props, ok := writable[element.File()]; ok {
			return props
		}
		component := vue.ComponentOf(element.Match)
		var props []string
		for _, prop := range component.Props(codebase) {
			if !slices.Contains(component.LocalNames(), prop.Name()) {
				props = append(props, prop.Name())
			}
		}
		writable[element.File()] = props

		return props
	}

	return vue.In(codebase).
		WhereElement().
		Where(engine.As(func(element vue.Element) bool { return writesOneOf(element, propsOf(element)) })).
		Get()
}

// writesOneOf says whether the element writes one of the names: a v-model's target, or an assignment's.
func writesOneOf(element vue.Element, names []string) bool {
	var targets []typescript.Node
	for _, directive := range element.Directives() {
		if directive.Named(vue.Model) {
			targets = append(targets, typescript.Of(directive.Value()))
		}
	}
	for _, expression := range element.Expressions() {
		if expression.Kind() == "ExpressionStatement" {
			expression = typescript.Of(expression.Child("expression"))
		}
		if expression.Is(engine.Assignment) {
			targets = append(targets, expression.Left())
		}
	}

	return slices.ContainsFunc(targets, func(target typescript.Node) bool {
		chain, ok := target.Chain()

		return ok && len(chain) == 1 && slices.Contains(names, chain[0])
	})
}

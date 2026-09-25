package vue

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/typescript"
)

// Forward is a prop a component hands on bare to a child component, under the child's own name for it.
type Forward struct {
	Element Element
	As      string
}

// Elements is every element of the component's template, in order.
func (c Component) Elements() []Element {
	var elements []Element
	for _, node := range c.Template().Descendants() {
		if element := Of(node); element.IsElement() {
			elements = append(elements, element)
		}
	}

	return elements
}

// ModelRoots is every name a v-model in the component's template binds from: the form's own state.
func (c Component) ModelRoots() []string {
	var roots []string
	for _, element := range c.Elements() {
		for _, directive := range element.Directives() {
			if directive.Named(Model) {
				roots = append(roots, typescript.Of(directive.Value()).Roots()...)
			}
		}
	}

	return roots
}

// PassThroughProps is each prop the component only hands on: bound bare to a child component, and read
// nowhere else, not in another expression, not as a script local of its name, not as props.x in the script.
// Each carries the places it is handed on.
func (c Component) PassThroughProps(codebase *engine.Codebase) map[string][]Forward {
	var props []string
	for _, prop := range c.Props(codebase) {
		props = append(props, prop.Name())
	}
	reads := map[string]int{}
	forwards := map[string][]Forward{}
	for _, element := range c.Elements() {
		for _, root := range element.ReadRoots() {
			if slices.Contains(props, root) {
				reads[root]++
			}
		}
		for _, forward := range element.bareForwards(props) {
			forwards[forward.name] = append(forwards[forward.name], forward.Forward)
		}
	}
	holder := c.PropsVariable()
	passed := map[string][]Forward{}
	for prop, sites := range forwards {
		if reads[prop] != len(sites) || slices.Contains(c.LocalNames(), prop) {
			continue
		}
		if holder != "" && c.ReadsMember(holder, prop) {
			continue
		}
		passed[prop] = sites
	}

	return passed
}

// named is a forward and the name the component knows the forwarded value by.
type named struct {
	Forward
	name string
}

// bareForwards is each of the names the element, a component, binds bare to one of its props.
func (e Element) bareForwards(names []string) []named {
	if !e.IsComponent() {
		return nil
	}
	var found []named
	for as, binding := range e.Bindings() {
		chain, ok := typescript.Of(binding.Value()).Chain()
		if ok && len(chain) == 1 && slices.Contains(names, chain[0]) {
			found = append(found, named{Forward: Forward{Element: e, As: as}, name: chain[0]})
		}
	}
	slices.SortFunc(found, func(a, b named) int { return strings.Compare(a.As, b.As) })

	return found
}

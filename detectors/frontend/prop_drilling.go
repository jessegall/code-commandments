package frontend

import (
	"maps"
	"slices"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/sins"
	frontend "github.com/jessegall/code-commandments/sins/frontend"
	"github.com/jessegall/code-commandments/typescript"
	"github.com/jessegall/code-commandments/vue"
)

func init() {
	detectors.Register(catalog.Frontend, PropDrillingDetector{})
}

// PropDrillingDetector finds a prop a component only passes on, bare, to a child that itself only passes it
// on: a value drilled through components that never use it. One forward is composition; it is drilling once
// the child is a conduit for the prop too.
type PropDrillingDetector struct{}

// forward is a prop handed on bare to a child component, under the child's own prop name.
type forward struct {
	element vue.Element
	as      string
}

func (PropDrillingDetector) Sin() sins.Sin {
	return frontend.PropDrilling{}
}

func (PropDrillingDetector) Find(codebase *engine.Codebase) []engine.Match {
	drilling := conduits{codebase: codebase, found: map[string]map[string][]forward{}}
	var findings []engine.Match
	seen := map[*contract.Node]bool{}
	for _, file := range vue.In(codebase).Files() {
		found := drilling.of(vue.ComponentOf(file.Match(0)))
		props := slices.Sorted(maps.Keys(found))
		for _, prop := range props {
			for _, each := range found[prop] {
				if seen[each.element.Node()] || !drilling.passesOn(each) {
					continue
				}
				seen[each.element.Node()] = true
				findings = append(findings, each.element.Match)
			}
		}
	}

	return findings
}

// conduits finds, and keeps, each component's conduit props.
type conduits struct {
	codebase *engine.Codebase
	found    map[string]map[string][]forward
}

// passesOn says whether the child a forward reaches is a conduit for the prop it receives.
func (c conduits) passesOn(each forward) bool {
	child := each.element.Resolves()
	if child == "" {
		return false
	}
	for _, file := range vue.In(c.codebase).Files() {
		if file.Path == child {
			_, conduit := c.of(vue.ComponentOf(file.Match(0)))[each.as]

			return conduit
		}
	}

	return false
}

// of is the component's conduit props: each forwarded bare to a child component and read nowhere else, not
// in another binding or interpolation, not as a local of the same name, not as props.x in the script.
func (c conduits) of(component vue.Component) map[string][]forward {
	if found, ok := c.found[component.File()]; ok {
		return found
	}
	var props []string
	for _, prop := range component.Props(c.codebase) {
		props = append(props, prop.Name())
	}
	reads := map[string]int{}
	forwards := map[string][]forward{}
	for _, node := range component.Template().Descendants() {
		element := vue.Of(node)
		if !element.IsElement() {
			continue
		}
		for _, root := range readRoots(element) {
			if slices.Contains(props, root) {
				reads[root]++
			}
		}
		if !element.IsComponent() {
			continue
		}
		for as, binding := range element.Bindings() {
			chain, ok := typescript.Of(binding.Value()).Chain()
			if ok && len(chain) == 1 && slices.Contains(props, chain[0]) {
				forwards[chain[0]] = append(forwards[chain[0]], forward{element: element, as: as})
			}
		}
	}
	found := map[string][]forward{}
	holder := component.PropsVariable()
	for prop, sites := range forwards {
		if reads[prop] != len(sites) || slices.Contains(component.LocalNames(), prop) {
			continue
		}
		if holder != "" && component.ReadsMember(holder, prop) {
			continue
		}
		found[prop] = sites
	}
	c.found[component.File()] = found

	return found
}

// readRoots is each name every expression of the element reads from, once per expression; its v-for's
// iterable counts as a read too.
func readRoots(element vue.Element) []string {
	var roots []string
	for _, expression := range element.Expressions() {
		roots = append(roots, expression.Roots()...)
	}

	return append(roots, typescript.Of(element.Directive(vue.For).Iterable()).Roots()...)
}

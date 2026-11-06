// Package vue is the engine's view of a Vue template: an element knows its tag, its directives and the elements around it, read from the generic tree the frontend bridge writes.
package vue

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/typescript"
)

// Element is a template element: a Match whose kind is Element.
type Element struct {
	engine.Match
}

func (Element) Decorate(m engine.Match) Element {
	return Element{m}
}

func Of(m engine.Match) Element {
	return Element{m}
}

func (e Element) IsElement() bool {
	return e.Kind() == "Element"
}

func (e Element) Tag() string {
	return e.Name()
}

func (e Element) IsComponent() bool {
	return e.IsElement() && e.HasFlag("component")
}

func (e Element) Writes(name string) bool {
	_, plain := e.WrittenAttribute(name)

	return e.IsElement() && (plain || e.Binding(name).Exists())
}

var formControls = []string{"input", "select", "textarea"}

func (e Element) HoldsState() bool {
	for _, element := range append([]Element{e}, e.DescendantElements()...) {
		if element.IsComponent() || element.Tag() == "slot" || slices.Contains(formControls, element.Tag()) {
			return true
		}
		if _, ref := element.WrittenAttribute("ref"); ref || element.Has(Model) || element.Binding("ref").Exists() {
			return true
		}
	}

	return false
}

func (e Element) Directives() []Directive {
	var directives []Directive
	for _, child := range e.Children() {
		if child.Kind() == "Directive" {
			directives = append(directives, Directive{child})
		}
	}

	return directives
}

func (e Element) Directive(name Name) Directive {
	for _, directive := range e.Directives() {
		if directive.Named(name) {
			return directive
		}
	}

	return Directive{}
}

func (e Element) Has(name Name) bool {
	return e.Directive(name).Exists()
}

func (e Element) HasAny(names ...Name) bool {
	return slices.ContainsFunc(names, e.Has)
}

func (e Element) Attributes() []engine.Match {
	var attributes []engine.Match
	for _, child := range e.Children() {
		if child.Kind() == "Attribute" {
			attributes = append(attributes, child)
		}
	}

	return attributes
}

func (e Element) Elements() []Element {
	var elements []Element
	for _, child := range e.Children() {
		if child.Kind() == "Element" {
			elements = append(elements, Element{child})
		}
	}

	return elements
}

func (e Element) Parent() Element {
	if parent := e.Match.Parent(); parent.Kind() == "Element" {
		return Element{parent}
	}

	return Element{}
}

func (e Element) Siblings() []Element {
	var siblings []Element
	for _, child := range e.Match.Parent().Children() {
		if child.Kind() == "Element" {
			siblings = append(siblings, Element{child})
		}
	}

	return siblings
}

func (e Element) Following() []Element {
	siblings := e.Siblings()
	for index, sibling := range siblings {
		if sibling.Node() == e.Node() {
			return siblings[index+1:]
		}
	}

	return nil
}

func (e Element) IsTransitionChild() bool {
	return slices.Contains([]string{"Transition", "TransitionGroup", "transition", "transition-group"}, e.Parent().Tag())
}

func (e Element) Binding(prop string) Directive {
	for _, directive := range e.Directives() {
		if directive.Named(Bind) && directive.Target() == prop {
			return directive
		}
	}

	return Directive{}
}

func (e Element) Depth() int {
	depth := 0
	for at := e; at.Exists(); at = at.Parent() {
		depth++
	}

	return depth
}

func (e Element) Height() int {
	tallest := 0
	for _, child := range e.Elements() {
		tallest = max(tallest, child.Height())
	}

	return tallest + 1
}

func (e Element) Size() int {
	size := 1
	for _, child := range e.Elements() {
		size += child.Size()
	}

	return size
}

func (e Element) Rendered() int {
	rendered := 0
	if !e.IsTemplate() {
		rendered = 1
	}
	for _, child := range e.Elements() {
		rendered += child.Rendered()
	}

	return rendered
}

func (e Element) Expressions() []typescript.Node {
	var expressions []typescript.Node
	for _, child := range e.Children() {
		switch child.Kind() {
		case "Directive":
			if name := (Directive{child}).Name(); name == For || name == Slot {
				continue
			}
			fallthrough
		case "Interpolation":
			for _, value := range child.ChildrenIn("value") {
				expressions = append(expressions, typescript.Of(value))
			}
		}
	}

	return expressions
}

func (e Element) DescendantElements() []Element {
	var below []Element
	for _, child := range e.Elements() {
		below = append(below, child)
		below = append(below, child.DescendantElements()...)
	}

	return below
}

func (e Element) CompoundParts() []Element {
	var parts []Element
	for _, element := range e.DescendantElements() {
		if element.IsComponent() && element.Tag() != e.Tag() && strings.HasPrefix(element.Tag(), e.Tag()) {
			parts = append(parts, element)
		}
	}

	return parts
}

func (e Element) IsTemplateRoot() bool {
	return e.Depth() == 1 && len(e.Siblings()) == 1
}

func (e Element) Bindings() map[string]Directive {
	bindings := map[string]Directive{}
	for _, directive := range e.Directives() {
		if directive.Named(Bind) && directive.Target() != "" {
			bindings[camelized(directive.Target())] = directive
		}
	}

	return bindings
}

func camelized(name string) string {
	parts := strings.Split(name, "-")
	for index := 1; index < len(parts); index++ {
		if parts[index] != "" {
			parts[index] = strings.ToUpper(parts[index][:1]) + parts[index][1:]
		}
	}

	return strings.Join(parts, "")
}

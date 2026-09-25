// Package vue is the engine's view of a Vue template: an element knows its tag, its directives and the
// elements around it, read from the generic tree the frontend bridge writes.
package vue

import (
	"slices"
	"unicode"

	"github.com/jessegall/code-commandments/engine"
)

// Element is a template element: a Match whose kind is Element.
type Element struct {
	engine.Match
}

// Decorate views a match as an element.
func (Element) Decorate(m engine.Match) Element {
	return Element{m}
}

// Of views a match as an element.
func Of(m engine.Match) Element {
	return Element{m}
}

// IsElement says whether the node is a template element, not text, an attribute or a block.
func (e Element) IsElement() bool {
	return e.Kind() == "Element"
}

// Tag is the element's tag as written.
func (e Element) Tag() string {
	return e.Name()
}

// IsComponent says whether the tag names a component: an element whose tag starts upper-case.
func (e Element) IsComponent() bool {
	tag := []rune(e.Tag())

	return e.IsElement() && len(tag) > 0 && unicode.IsUpper(tag[0])
}

// Directives is every directive written on the element, in order.
func (e Element) Directives() []Directive {
	var directives []Directive
	for _, child := range e.Children() {
		if child.Kind() == "Directive" {
			directives = append(directives, Directive{child})
		}
	}

	return directives
}

// Directive is the first directive of the name written on the element; no node when there is none.
func (e Element) Directive(name Name) Directive {
	for _, directive := range e.Directives() {
		if directive.Named(name) {
			return directive
		}
	}

	return Directive{}
}

// Has says whether the element carries a directive of the name, whatever its argument or modifiers.
func (e Element) Has(name Name) bool {
	return e.Directive(name).Exists()
}

// HasAny says whether the element carries any of the directives.
func (e Element) HasAny(names ...Name) bool {
	return slices.ContainsFunc(names, e.Has)
}

// Attributes is every static attribute written on the element, in order.
func (e Element) Attributes() []engine.Match {
	var attributes []engine.Match
	for _, child := range e.Children() {
		if child.Kind() == "Attribute" {
			attributes = append(attributes, child)
		}
	}

	return attributes
}

// Elements is the element's child elements, in order: text, interpolations and attributes left out.
func (e Element) Elements() []Element {
	var elements []Element
	for _, child := range e.Children() {
		if child.Kind() == "Element" {
			elements = append(elements, Element{child})
		}
	}

	return elements
}

// Parent is the element the element sits in; no node at the template's top.
func (e Element) Parent() Element {
	if parent := e.Match.Parent(); parent.Kind() == "Element" {
		return Element{parent}
	}

	return Element{}
}

// Siblings is the elements beside it under the same parent, itself included, in order.
func (e Element) Siblings() []Element {
	var siblings []Element
	for _, child := range e.Match.Parent().Children() {
		if child.Kind() == "Element" {
			siblings = append(siblings, Element{child})
		}
	}

	return siblings
}

// Following is the sibling elements after it, in order.
func (e Element) Following() []Element {
	siblings := e.Siblings()
	for index, sibling := range siblings {
		if sibling.Node() == e.Node() {
			return siblings[index+1:]
		}
	}

	return nil
}

// IsTransitionChild says whether the element sits directly in a <Transition> or <TransitionGroup>, which
// needs its conditional on the element itself.
func (e Element) IsTransitionChild() bool {
	return slices.Contains([]string{"Transition", "TransitionGroup", "transition", "transition-group"}, e.Parent().Tag())
}

// Binding is the element's v-bind of one prop, `:key` or `v-bind:key`; no node when it binds none.
func (e Element) Binding(prop string) Directive {
	for _, directive := range e.Directives() {
		if directive.Named(Bind) && directive.Argument().Name() == prop {
			return directive
		}
	}

	return Directive{}
}

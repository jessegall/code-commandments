// Package vue is the engine's view of a Vue template: an element knows its tag, its directives and the
// elements around it, read from the generic tree the frontend bridge writes.
package vue

import (
	"slices"
	"strings"
	"unicode"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/typescript"
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

// Depth is how many elements deep the element sits, itself counted: a top-level element is 1.
func (e Element) Depth() int {
	depth := 0
	for at := e; at.Exists(); at = at.Parent() {
		depth++
	}

	return depth
}

// Height is how many element levels its subtree holds: a leaf is 1, a parent one more than its tallest child.
func (e Element) Height() int {
	tallest := 0
	for _, child := range e.Elements() {
		tallest = max(tallest, child.Height())
	}

	return tallest + 1
}

// Size is how many elements its subtree holds, itself included.
func (e Element) Size() int {
	size := 1
	for _, child := range e.Elements() {
		size += child.Size()
	}

	return size
}

// Expressions is the data the element itself reads: every directive's value but a v-for's and a slot's,
// and each interpolation among its children. A statement-bodied handler is each of its statements.
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

// DescendantElements is every element below it, in pre-order.
func (e Element) DescendantElements() []Element {
	var below []Element
	for _, child := range e.Elements() {
		below = append(below, child)
		below = append(below, child.DescendantElements()...)
	}

	return below
}

// CompoundParts is the components below it whose tag is its own plus a suffix, DialogContent and
// DialogTitle under Dialog: the parts of a library compound, read from the tags themselves.
func (e Element) CompoundParts() []Element {
	var parts []Element
	for _, element := range e.DescendantElements() {
		if element.IsComponent() && element.Tag() != e.Tag() && strings.HasPrefix(element.Tag(), e.Tag()) {
			parts = append(parts, element)
		}
	}

	return parts
}

// IsTemplateRoot says whether the element is the template's only top-level element.
func (e Element) IsTemplateRoot() bool {
	return e.Depth() == 1 && len(e.Siblings()) == 1
}

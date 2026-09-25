package vue

import (
	"slices"

	"github.com/jessegall/code-commandments/engine/typescript"
)

// Reads is every expression the element reads data through: its own expressions and its v-for's iterable.
func (e Element) Reads() []typescript.Node {
	return append(e.Expressions(), typescript.Of(e.Directive(For).Iterable()))
}

// Chains is every member chain of two or more names the element reads.
func (e Element) Chains() [][]string {
	var chains [][]string
	for _, read := range e.Reads() {
		chains = append(chains, read.Chains()...)
	}

	return chains
}

// ReadRoots is each name the element reads data from, once for every expression that reads it.
func (e Element) ReadRoots() []string {
	var roots []string
	for _, read := range e.Reads() {
		roots = append(roots, read.Roots()...)
	}

	return roots
}

// Ancestry is the element and every element above it, innermost first.
func (e Element) Ancestry() []Element {
	var spine []Element
	for at := e; at.Exists(); at = at.Parent() {
		spine = append(spine, at)
	}

	return spine
}

// CommonAncestor is the deepest element holding every one of the elements; no node when only the template
// holds them all.
func CommonAncestor(elements []Element) Element {
	if len(elements) == 0 {
		return Element{}
	}
	for _, candidate := range elements[0].Ancestry() {
		holdsAll := !slices.ContainsFunc(elements[1:], func(element Element) bool {
			return !slices.ContainsFunc(element.Ancestry(), func(above Element) bool { return above.Node() == candidate.Node() })
		})
		if holdsAll {
			return candidate
		}
	}

	return Element{}
}

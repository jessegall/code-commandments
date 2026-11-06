package vue

import (
	"slices"

	"github.com/jessegall/code-commandments/engine/typescript"
)

func (e Element) Reads() []typescript.Node {
	return append(e.Expressions(), typescript.Of(e.Directive(For).Iterable()))
}

func (e Element) Chains() [][]string {
	var chains [][]string
	for _, read := range e.Reads() {
		chains = append(chains, read.Chains()...)
	}

	return chains
}

func (e Element) ReadRoots() []string {
	var roots []string
	for _, read := range e.Reads() {
		roots = append(roots, read.Roots()...)
	}

	return roots
}

func (e Element) Ancestry() []Element {
	var spine []Element
	for at := e; at.Exists(); at = at.Parent() {
		spine = append(spine, at)
	}

	return spine
}

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

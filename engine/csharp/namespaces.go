package csharp

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// NamespaceGraph is every reference from one namespace of the program to another: a type named, built, called or
// resolved in one namespace and declared in another, each an arrow at the node that makes it.
type NamespaceGraph struct {
	homes  map[string]string
	arrows engine.DependencyArrows
}

// Namespaces is the namespace graph of the program's C# code, read once.
func Namespaces(codebase *engine.Codebase) *NamespaceGraph {
	return engine.Analysis(codebase, "csharp.namespaces", func(codebase *engine.Codebase) *NamespaceGraph {
		graph := &NamespaceGraph{homes: map[string]string{}}
		program := Of(codebase)
		for node := range program.all() {
			if node.IsTypeDeclaration() {
				graph.homes[typeKey(node.Symbol())] = node.Parent().namespaceAround()
			}
		}
		for _, file := range In(codebase).Files() {
			graph.walk(Node{file.Match(0)}, "")
		}

		return graph
	})
}

// namespaceAround is the name of the namespace the node sits in; empty outside one.
func (n Node) namespaceAround() string {
	for around := n; around.Exists(); around = around.Parent() {
		if around.Is("NamespaceDeclaration", "FileScopedNamespaceDeclaration") {
			return around.NamespaceName()
		}
	}

	return ""
}

// walk files an arrow for every node under the one given that reaches a type declared in another namespace.
func (g *NamespaceGraph) walk(node Node, namespace string) {
	here := namespace
	if node.Is("NamespaceDeclaration", "FileScopedNamespaceDeclaration") {
		here = node.NamespaceName()
	}
	if here != "" {
		for _, home := range g.reachedFrom(node) {
			if home != here {
				g.arrows = append(g.arrows, engine.DependencyArrow{At: node.Match, From: here, To: home})
			}
		}
	}
	for _, child := range node.All() {
		g.walk(child, here)
	}
}

// reachedFrom is the namespaces of the program's types the node reaches: the types its type is made of, and the
// type declaring what it calls.
func (g *NamespaceGraph) reachedFrom(node Node) []string {
	symbols := []string{}
	if typed := node.Type(); typed.Exists() {
		symbols = append(symbols, typed.NamedTypes()...)
	}
	if target := node.Target(); target.Exists() {
		symbols = append(symbols, target.Type())
	}
	var homes []string
	for _, symbol := range symbols {
		if home, declared := g.homes[typeKey(symbol)]; declared && home != "" && !slices.Contains(homes, home) {
			homes = append(homes, home)
		}
	}

	return homes
}

// Arrows is every reference from one namespace to another.
func (g *NamespaceGraph) Arrows() engine.DependencyArrows {
	return g.arrows
}

// IndependentArrows is the references between namespaces neither of which nests the other: a namespace and its own
// children are one part of the program.
func (g *NamespaceGraph) IndependentArrows() engine.DependencyArrows {
	var independent engine.DependencyArrows
	for _, arrow := range g.arrows {
		if !nests(arrow.From, arrow.To) && !nests(arrow.To, arrow.From) {
			independent = append(independent, arrow)
		}
	}

	return independent
}

// WouldCloseACycle says whether a reference from the referrer type to the target type would close a cycle between
// their namespaces: the target's namespace already refers back to the referrer's.
func (g *NamespaceGraph) WouldCloseACycle(referrer, target string) bool {
	from, fromDeclared := g.homes[typeKey(referrer)]
	to, toDeclared := g.homes[typeKey(target)]

	return fromDeclared && toDeclared && from != to && g.IndependentArrows().Has(to, from)
}

// nests says whether the outer namespace holds the inner one.
func nests(outer, inner string) bool {
	return strings.HasPrefix(inner, outer+".")
}

// LayerViolations is every reference out of a declared layer into a namespace it may not use: one per file and
// namespace it reaches, at its first reference.
func (g *NamespaceGraph) LayerViolations(stack engine.LayerStack) []engine.Match {
	if stack.IsEmpty() {
		return nil
	}
	seen := map[string]bool{}
	var found []engine.Match
	for _, arrow := range g.arrows {
		from := stack.LayerOf(arrow.From)
		if from == "" || stack.LayerOf(arrow.To) == "" || stack.MayReference(from, arrow.To) {
			continue
		}
		if key := arrow.At.File() + "\x00" + arrow.To; !seen[key] {
			seen[key] = true
			found = append(found, arrow.At)
		}
	}

	return found
}

// typeKey is the type the symbol names, its type arguments aside: `Pipeline<TIn, TOut>` as declared and
// `Pipeline<Order, Invoice>` as used are the one type, whose home a reference through either reaches.
func typeKey(symbol string) string {
	if open := strings.Index(symbol, "<"); open >= 0 {
		return symbol[:open]
	}

	return symbol
}

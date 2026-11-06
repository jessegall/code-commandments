package csharp

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// NamespaceGraph is every reference from one namespace of the program to another: a type named, built, called or resolved in one namespace and declared in another, each an arrow at the node that makes it.
type NamespaceGraph struct {
	homes     map[string]string
	arrows    engine.DependencyArrows
	elsewhere engine.ArrowCounts
}

func Namespaces(codebase *engine.Codebase) *NamespaceGraph {
	return engine.Analysis(codebase, "csharp.namespaces", func(codebase *engine.Codebase) *NamespaceGraph {
		return graphOf(codebase, Of(codebase).facts, nil)
	})
}

func graphOf(codebase *engine.Codebase, facts, own *Summary) *NamespaceGraph {
	graph := &NamespaceGraph{homes: facts.homes}
	if own != nil {
		graph.elsewhere = facts.arrowsBeside(own)
	}
	for _, file := range In(codebase).Files() {
		graph.walk(Node{file.Match(0)}, "")
	}

	return graph
}

func (n Node) namespaceAround() string {
	for around := n; around.Exists(); around = around.Parent() {
		if around.Is("NamespaceDeclaration", "FileScopedNamespaceDeclaration") {
			return around.NamespaceName()
		}
	}

	return ""
}

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

func (g *NamespaceGraph) Arrows() engine.DependencyArrows {
	return g.arrows
}

func (g *NamespaceGraph) IndependentArrows() engine.DependencyArrows {
	return g.arrows.Independent(".")
}

func (g *NamespaceGraph) WouldCloseACycle(referrer, target string) bool {
	from, fromDeclared := g.homes[typeKey(referrer)]
	to, toDeclared := g.homes[typeKey(target)]

	return fromDeclared && toDeclared && from != to && (g.IndependentArrows().Has(to, from) || g.independentElsewhere()[engine.Pair{From: to, To: from}] > 0)
}

func (g *NamespaceGraph) ClosingAMutualPair() []engine.Match {
	return g.IndependentArrows().ClosingAMutualPairBeside(g.independentElsewhere())
}

func (g *NamespaceGraph) independentElsewhere() engine.ArrowCounts {
	independent := engine.ArrowCounts{}
	for pair, count := range g.elsewhere {
		if !engine.Nests(pair.From, pair.To, ".") && !engine.Nests(pair.To, pair.From, ".") {
			independent[pair] = count
		}
	}

	return independent
}

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

func typeKey(symbol string) string {
	if open := strings.Index(symbol, "<"); open >= 0 {
		return symbol[:open]
	}

	return symbol
}

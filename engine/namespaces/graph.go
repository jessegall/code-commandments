// Package namespaces reads which namespace of a codebase references which, from every reference the compiler resolved
// to a declared type: the graph the layers command proposes a layer stack from.
package namespaces

import (
	"github.com/jessegall/code-commandments/engine"
)

// NamespaceGraph is which namespace references which, read from every class reference to a declared class.
type NamespaceGraph struct {
	references map[string]map[string]bool
	nodes      []string
	known      map[string]bool
	arrows     map[string]map[string][]engine.Match
	order      []string
	targets    map[string][]string
}

var graphs = engine.Memoised(func(codebase *engine.Codebase) *NamespaceGraph {
	graph := &NamespaceGraph{references: map[string]map[string]bool{}, arrows: map[string]map[string][]engine.Match{}, targets: map[string][]string{}}
	for _, reference := range codebase.WhereKind("reference", "type-expression").Get() {
		from, to := reference.Namespace(), declaredNamespace(codebase, reference.Refers())
		if from == "" || to == "" || reference.NamesAnAssociation() {
			continue
		}
		graph.node(from)
		graph.node(to)
		if to != from {
			if graph.references[from] == nil {
				graph.references[from] = map[string]bool{}
			}
			graph.references[from][to] = true
		}
		if engine.Within(to, from) || engine.Within(from, to) {
			continue
		}
		if graph.arrows[from] == nil {
			graph.arrows[from] = map[string][]engine.Match{}
			graph.order = append(graph.order, from)
		}
		if graph.arrows[from][to] == nil {
			graph.targets[from] = append(graph.targets[from], to)
		}
		graph.arrows[from][to] = append(graph.arrows[from][to], reference)
	}

	return graph
})

// declaredNamespace is the namespace of the type a symbol names when the codebase declares one, else none.
func declaredNamespace(codebase *engine.Codebase, symbol string) string {
	if symbol == "" {
		return ""
	}
	for _, declared := range codebase.Declarations(symbol) {
		if declared.Is(engine.TypeDeclaration) {
			return declared.Namespace()
		}
	}

	return ""
}

func Of(codebase *engine.Codebase) *NamespaceGraph {
	return graphs.Of(codebase)
}

func (g *NamespaceGraph) ArrowsClosingAMutualPair() []engine.Match {
	var cutting []engine.Match
	for _, pair := range g.MutualPairs() {
		cutting = append(cutting, g.arrows[pair[0]][pair[1]]...)
	}

	return cutting
}

func (g *NamespaceGraph) MutualPairs() [][2]string {
	chosen := map[[2]string][2]string{}
	var order [][2]string
	for _, from := range g.order {
		for _, to := range g.targets[from] {
			back, mutual := g.arrows[to][from]
			if !mutual {
				continue
			}
			key := [2]string{min(from, to), max(from, to)}
			if _, seen := chosen[key]; !seen {
				order = append(order, key)
			}
			thinner := [2]string{to, from}
			if count := len(g.arrows[from][to]); count < len(back) || count == len(back) && from <= to {
				thinner = [2]string{from, to}
			}
			chosen[key] = thinner
		}
	}
	pairs := make([][2]string, 0, len(order))
	for _, key := range order {
		pairs = append(pairs, chosen[key])
	}

	return pairs
}

// Package namespaces reads which namespace of a PHP codebase references which: the graph the dependency-direction
// rules judge, and the cycles a new reference would close.
package namespaces

import (
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/packages"
)

// NamespaceGraph is which namespace references which, read from every class reference to a declared class.
type NamespaceGraph struct {
	references map[string]map[string]bool
	arrows     map[string]map[string][]engine.Match
	order      []string
	targets    map[string][]string
}

var graphs = php.Memoised(func(codebase *engine.Codebase) *NamespaceGraph {
	graph := &NamespaceGraph{references: map[string]map[string]bool{}, arrows: map[string]map[string][]engine.Match{}, targets: map[string][]string{}}
	program := php.ProgramOf(codebase)
	for _, reference := range codebase.Where(engine.As(php.Node.IsClassReference)).Get() {
		node := php.Node{Match: reference}
		from, target := node.NamespaceName(), reference.Name()
		if _, declared := program.Declaration(target); from == "" || !declared {
			continue
		}
		to := php.NamespaceOf(target)
		if to == "" {
			continue
		}
		if to != from {
			if graph.references[from] == nil {
				graph.references[from] = map[string]bool{}
			}
			graph.references[from][to] = true
		}
		if php.Within(to, from) || php.Within(from, to) || declaresAnAssociation(codebase, node) {
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

// Of is the codebase's namespace graph.
func Of(codebase *engine.Codebase) *NamespaceGraph {
	return graphs.Of(codebase)
}

// WouldCloseACycle says whether a reference from the referrer's namespace to the target's would close a cycle: the
// target's namespace already references the referrer's.
func (g *NamespaceGraph) WouldCloseACycle(referrer, target string) bool {
	from, to := php.NamespaceOf(referrer), php.NamespaceOf(target)

	return from != "" && to != "" && from != to && g.references[to][from]
}

// declaresAnAssociation says whether the reference names one end of a two-way association a package declares: an
// ORM relation call's argument, or a binding attribute's.
func declaresAnAssociation(codebase *engine.Codebase, reference php.Node) bool {
	return packages.Excuses(codebase, packages.Association, "", reference.ArgumentOfCall()) ||
		packages.ExcusesAttribute(codebase, packages.Association, reference.EnclosingAttributeName())
}

// ArrowsClosingAMutualPair is every reference of the thinner direction of each pair of namespaces that reference
// each other: fewer references, ties broken on the name.
func (g *NamespaceGraph) ArrowsClosingAMutualPair() []engine.Match {
	var cutting []engine.Match
	for _, pair := range g.mutualPairs() {
		cutting = append(cutting, g.arrows[pair[0]][pair[1]]...)
	}

	return cutting
}

// mutualPairs is each pair of namespaces referencing each other, as its thinner direction, in first-seen order.
func (g *NamespaceGraph) mutualPairs() [][2]string {
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

// Distinct is the references kept once per class that makes them and class they name, imports aside: a class that
// names another ten times crosses once.
func Distinct(references []engine.Match) []engine.Match {
	seen := map[[2]string]bool{}
	var kept []engine.Match
	for _, reference := range references {
		if reference.Parent().Kind() == "UseItem" {
			continue
		}
		referrer := php.EnclosingClassName(reference)
		if referrer == "" {
			referrer = reference.File()
		}
		key := [2]string{referrer, reference.Name()}
		if !seen[key] {
			seen[key] = true
			kept = append(kept, reference)
		}
	}

	return kept
}

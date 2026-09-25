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
}

var graphs = php.Memoised(func(codebase *engine.Codebase) *NamespaceGraph {
	graph := &NamespaceGraph{references: map[string]map[string]bool{}, arrows: map[string]map[string][]engine.Match{}}
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

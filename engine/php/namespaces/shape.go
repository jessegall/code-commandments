package namespaces

import (
	"maps"
	"slices"

	"github.com/jessegall/code-commandments/engine/php"
)

// Layer is one namespace of a shape and the namespaces it uses.
type Layer struct {
	Namespace string
	Uses      []string
}

// DependencyOrder is the namespaces with every one after what it depends on, and those in a cycle, which no
// order can place, after them.
type DependencyOrder struct {
	Ordered []string
	Cyclic  []string
}

// Total is how many namespaces the order holds.
func (o DependencyOrder) Total() int {
	return len(o.Ordered) + len(o.Cyclic)
}

// HasCycles says whether any namespace sits in a cycle.
func (o DependencyOrder) HasCycles() bool {
	return len(o.Cyclic) > 0
}

// All is the ordered namespaces, then the cyclic ones.
func (o DependencyOrder) All() []string {
	return append(slices.Clone(o.Ordered), o.Cyclic...)
}

// node counts the namespace among the graph's, once.
func (g *NamespaceGraph) node(namespace string) {
	if g.known == nil {
		g.known = map[string]bool{}
	}

	if !g.known[namespace] {
		g.known[namespace] = true
		g.nodes = append(g.nodes, namespace)
	}
}

// CurrentShape is every namespace with what it already references, in dependency order: the stack as it
// stands, declared so everything already there passes.
func (g *NamespaceGraph) CurrentShape() []Layer {
	edges := map[string][]string{}

	for _, namespace := range g.nodes {
		edges[namespace] = slices.Sorted(maps.Keys(g.references[namespace]))
	}

	var shape []Layer

	for _, namespace := range topological(edges).All() {
		shape = append(shape, Layer{namespace, edges[namespace]})
	}

	return shape
}

// FloorShape is the current shape's bottom: the namespaces that use nothing, and whose own subtree reaches
// nothing outside it either.
func (g *NamespaceGraph) FloorShape() []Layer {
	var floor []Layer

	for _, layer := range g.CurrentShape() {
		if len(layer.Uses) == 0 && !g.subtreeReachesOut(layer.Namespace) {
			floor = append(floor, layer)
		}
	}

	return floor
}

// subtreeReachesOut says whether any namespace within this one references one outside it.
func (g *NamespaceGraph) subtreeReachesOut(namespace string) bool {
	for from, targets := range g.references {
		if !php.Within(from, namespace) {
			continue
		}

		for to := range targets {
			if !php.Within(to, namespace) {
				return true
			}
		}
	}

	return false
}

// DependencyOrder is every namespace ordered by the arrows between them.
func (g *NamespaceGraph) DependencyOrder() DependencyOrder {
	edges := map[string][]string{}

	for _, namespace := range g.nodes {
		edges[namespace] = g.targets[namespace]
	}

	return topological(edges)
}

// topological orders the nodes round by round: each round takes, sorted, every node whose targets are all
// placed; when none is free, the rest are a cycle, sorted.
func topological(edges map[string][]string) DependencyOrder {
	remaining := map[string]bool{}

	for node := range edges {
		remaining[node] = true
	}

	var ordered []string

	for len(remaining) > 0 {
		var free []string

		for node := range remaining {
			if !slices.ContainsFunc(edges[node], func(target string) bool { return remaining[target] }) {
				free = append(free, node)
			}
		}

		if len(free) == 0 {
			return DependencyOrder{ordered, slices.Sorted(maps.Keys(remaining))}
		}

		slices.Sort(free)
		ordered = append(ordered, free...)

		for _, node := range free {
			delete(remaining, node)
		}
	}

	return DependencyOrder{ordered, nil}
}

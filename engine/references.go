package engine

import (
	"slices"

	"github.com/jessegall/code-commandments/contract"
)

func (m Match) Constructed() Match {
	if read := m.grammar().Constructs; read != nil && m.node != nil {
		return read(m)
	}

	return Match{}
}

func (m Match) Referrers() []Match {
	if m.node == nil || m.node.Facts == nil || m.node.Symbol == "" {
		return nil
	}

	found := referrers(m.file.Codebase())[m.node.Symbol]
	if read := m.grammar().Callers; read != nil {
		found = append(found, read(m)...)
	}

	var outside []Match
	seen := map[any]bool{}
	for _, referrer := range found {
		if seen[referrer.node] || referrer.node == m.node || m.isAbove(referrer) {
			continue
		}

		seen[referrer.node] = true
		outside = append(outside, referrer)
	}

	return outside
}

func (m Match) isAbove(below Match) bool {
	for ancestor := below.Parent(); ancestor.Exists(); ancestor = ancestor.Parent() {
		if ancestor.node == m.node {
			return true
		}
	}

	return false
}

func (m Match) IsImplicit() bool {
	read := m.grammar().Implicit

	return read != nil && m.node != nil && read(m)
}

func (m Match) IsOverride() bool {
	owner := m.Parent()
	for !owner.Is(TypeDeclaration) && owner.Exists() && !owner.Is(Function) {
		owner = owner.Parent()
	}

	if !m.Is(Function) || !owner.Is(TypeDeclaration) || m.Name() == "" {
		return false
	}

	if read := m.grammar().Inherited; read != nil && read(m) {
		return true
	}

	for _, supertype := range append(owner.Lineage(), owner.Contracts()...) {
		if owner.declaresMember(supertype, m.Name()) {
			return true
		}
	}

	return false
}

func (m Match) declaresMember(symbol, name string) bool {
	for _, declaration := range m.file.Codebase().Declarations(symbol) {
		if declaration.file.Language() != m.file.Language() {
			continue
		}

		for _, member := range declaration.Members() {
			if member.Name() == name {
				return true
			}
		}
	}

	return slices.ContainsFunc(outsideSymbols(m.file.Codebase(), m.file.Language())[symbol].Members,
		func(member contract.OutsideMember) bool { return member.Name == name })
}

var promotions = []string{"public", "protected", "private", "readonly"}

func (m Match) IsUnread() bool {
	function := m.DeclaringFunction()
	if !function.Exists() || m.IsImplicit() || function.IsOverride() || slices.ContainsFunc(promotions, m.HasModifier) {
		return false
	}

	for _, below := range function.Descendants() {
		if below.Is(Identifier) && below.Name() == m.Name() && below.node != m.node && !m.isAbove(below) &&
			!below.isSelector() && !below.isShadowed(function) {
			return false
		}
	}

	return true
}

func (m Match) isSelector() bool {
	if parent := m.Parent(); parent.Is(MemberAccess) && parent.Children()[0].node != m.node {
		return true
	}

	read := m.grammar().Labels

	return read != nil && read(m)
}

func (m Match) isShadowed(outer Match) bool {
	for ancestor := m.Parent(); ancestor.Exists() && ancestor.node != outer.node; ancestor = ancestor.Parent() {
		if ancestor.Is(Function) && slices.ContainsFunc(ancestor.Parameters(), func(parameter Match) bool { return parameter.Name() == m.Name() }) {
			return true
		}
	}

	return false
}

func referrers(c *Codebase) map[string][]Match {
	return Analysis(c, "referrers", func(c *Codebase) map[string][]Match {
		index := map[string][]Match{}
		for _, file := range c.files {
			for _, node := range file.Nodes() {
				if node.Facts == nil {
					continue
				}

				if node.Refers != "" {
					index[node.Refers] = append(index[node.Refers], Match{node: node, file: file})
				}

				if node.Target == nil {
					continue
				}

				for _, symbol := range []string{node.Target.Symbol, node.Target.Type} {
					if symbol == "" || symbol == node.Refers {
						continue
					}
					index[symbol] = append(index[symbol], Match{node: node, file: file})
				}
			}
		}

		return index
	})
}

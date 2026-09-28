package engine

import (
	"slices"

	"github.com/jessegall/code-commandments/contract"
)

// Constructed is the node naming the type the construction creates; no node for anything else.
func (m Match) Constructed() Match {
	if read := m.lists().Constructs; read != nil && m.node != nil {
		return read(m)
	}

	return Match{}
}

// Referrers are the nodes referring to the declaration from outside it: every node whose name resolves to its
// symbol or whose call reaches it, and the calls its language's call graph finds. A declaration's references
// to itself, such as a recursive call, are no referrers; a declaration with no symbol has none.
func (m Match) Referrers() []Match {
	if m.node == nil || m.node.Facts == nil || m.node.Symbol == "" {
		return nil
	}

	found := referrers(m.file.Codebase())[m.node.Symbol]
	if read := m.lists().Callers; read != nil {
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

// isAbove says whether the node sits somewhere below this one.
func (m Match) isAbove(below Match) bool {
	for ancestor := below.Parent(); ancestor.Exists(); ancestor = ancestor.Parent() {
		if ancestor.node == m.node {
			return true
		}
	}

	return false
}

// IsImplicit says whether the language itself calls the declaration or binds the parameter: a constructor, a magic
// or dunder method, Python's self and cls.
func (m Match) IsImplicit() bool {
	read := m.lists().Implicit

	return read != nil && m.node != nil && read(m)
}

// Overrides says whether the function is a member its type owes a supertype: its language's compiler says so, or one
// of the same name is declared by a type it extends or a contract it honours, in the scan or outside it, so a call
// through the supertype reaches it.
func (m Match) Overrides() bool {
	owner := m.Parent()
	for !owner.Is(TypeDeclaration) && owner.Exists() && !owner.Is(Function) {
		owner = owner.Parent()
	}

	if !m.Is(Function) || !owner.Is(TypeDeclaration) || m.Name() == "" {
		return false
	}

	if read := m.lists().Overrides; read != nil && read(m) {
		return true
	}

	for _, supertype := range append(owner.Lineage(), owner.Contracts()...) {
		if owner.declaresMember(supertype, m.Name()) {
			return true
		}
	}

	return false
}

// declaresMember says whether the type the symbol names declares a member of the name, in the scan or outside it.
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

// promotions are the modifiers that make a parameter a property as well, read through the object rather than by
// its name: PHP's promoted constructor parameters and TypeScript's parameter properties.
var promotions = []string{"public", "protected", "private", "readonly"}

// IsUnread says whether the parameter's function never reads its name, a closure inside it included. A
// parameter the tool cannot judge, or that the code owes someone else — of a declaration with no body, of a
// function type, of a member a supertype dictates, bound by the language, or promoted to a property — is not unread. A name that selects a member or labels an argument (`this.name`, `name:`) is no read.
func (m Match) IsUnread() bool {
	function := m.DeclaringFunction()
	if !function.Exists() || m.IsImplicit() || function.Overrides() || slices.ContainsFunc(promotions, m.HasModifier) {
		return false
	}

	for _, below := range function.Descendants() {
		if below.Is(Identifier) && below.Name() == m.Name() && below.node != m.node && !m.isAbove(below) && !below.isSelector() {
			return false
		}
	}

	return true
}

// isSelector says whether the name selects or labels rather than reads: it fills a name slot, as a member
// access's member and an argument's label do.
func (m Match) isSelector() bool {
	return m.node.Field == "name" || m.node.Field == "Name"
}

// referrers indexes every node of the codebase by the symbol it refers to, the declaration its call reaches, and
// the type that declaration is a member of, as a static call names its class.
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
					if symbol != "" && symbol != node.Refers {
						index[symbol] = append(index[symbol], Match{node: node, file: file})
					}
				}
			}
		}

		return index
	})
}

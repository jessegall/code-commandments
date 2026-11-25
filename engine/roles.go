package engine

import (
	"fmt"

	"github.com/jessegall/code-commandments/contract"
)

// Truth is a rule's answer over evidence that may be incomplete: true, false, or unknown when the tree cannot say.
// Proven false rules out a conjunction, proven true satisfies a disjunction, and negating unknown stays unknown.
type Truth int8

const (
	False Truth = iota
	True
	Unknown
)

func TruthOf(holds bool) Truth {
	if holds {
		return True
	}

	return False
}

func (t Truth) And(other Truth) Truth {
	switch {
	case t == False || other == False:
		return False
	case t == Unknown || other == Unknown:
		return Unknown
	}

	return True
}

func (t Truth) Or(other Truth) Truth {
	switch {
	case t == True || other == True:
		return True
	case t == Unknown || other == Unknown:
		return Unknown
	}

	return False
}

func (t Truth) Not() Truth {
	switch t {
	case True:
		return False
	case False:
		return True
	}

	return Unknown
}

// Held is what one role of a node holds, and the answer it makes: present, known-empty, unknown when the parser
// recovered from an error there, or inapplicable when the node's kind has no such role.
type Held struct {
	Nodes  []Match
	Answer contract.Answer
}

// Filled is the truth that the role holds a node: unknown when the evidence is incomplete.
func (h Held) Filled() Truth {
	switch h.Answer {
	case contract.Present:
		return True
	case contract.Unknown:
		return Unknown
	}

	return False
}

// First is the role's one node, or no node.
func (h Held) First() Match {
	if len(h.Nodes) == 0 {
		return Match{}
	}

	return h.Nodes[0]
}

// In reads a role of the node by name. A role the node's kind does not declare is refused by name, so a rule that
// asks a handler for its "then" fails where it is written, never by matching nothing.
func (m Match) In(role string) (Held, error) {
	if m.node == nil {
		return Held{Answer: contract.Inapplicable}, nil
	}
	if _, declared := contract.DeclaredRole(m.node.Kind, role); !declared {
		_, err := contract.RoleArity(m.node.Kind, role)

		return Held{Answer: contract.Inapplicable}, err
	}
	nodes, answer := m.node.Role(role)
	held := Held{Answer: answer, Nodes: make([]Match, 0, len(nodes))}
	for _, node := range nodes {
		held.Nodes = append(held.Nodes, Match{node: node, file: m.file})
	}

	return held, nil
}

func (m Match) Single(role string) (Match, contract.Answer) {
	if m.node == nil {
		return Match{}, contract.Inapplicable
	}
	nodes, answer := m.node.Role(role)
	if len(nodes) == 0 {
		return Match{}, answer
	}

	return Match{node: nodes[0], file: m.file}, answer
}

// One reads a role that holds at most one node.
func (m Match) One(role string) (Match, error) {
	if err := m.arity(role, contract.One, contract.Optional); err != nil {
		return Match{}, err
	}
	held, err := m.In(role)

	return held.First(), err
}

// List reads a role that holds an ordered list.
func (m Match) List(role string) ([]Match, error) {
	if err := m.arity(role, contract.Many); err != nil {
		return nil, err
	}
	held, err := m.In(role)

	return held.Nodes, err
}

func (m Match) arity(role string, wanted ...contract.Arity) error {
	if m.node == nil {
		return nil
	}
	arity, err := contract.RoleArity(m.node.Kind, role)
	if err != nil {
		return err
	}
	for _, each := range wanted {
		if arity == each {
			return nil
		}
	}

	return fmt.Errorf("a %s's %q holds %s", m.node.Kind, role, map[bool]string{true: "a list", false: "one node"}[arity == contract.Many])
}

// EnclosingScope is the nearest function-like node around this one: a closure is a scope of its own, so a name bound
// inside it is never the enclosing function's.
func (m Match) EnclosingScope() Match {
	for ancestor := m.Parent(); ancestor.Exists(); ancestor = ancestor.Parent() {
		if ancestor.node.Is("function-like") {
			return ancestor
		}
	}

	return Match{}
}

// EnclosingModule is the module node around this one.
func (m Match) EnclosingModule() Match {
	for ancestor := m.Parent(); ancestor.Exists(); ancestor = ancestor.Parent() {
		if ancestor.node.Kind == "module" {
			return ancestor
		}
	}

	return Match{}
}

// EnclosingFile is the file node at the root of this one's tree.
func (m Match) EnclosingFile() Match {
	return m.Root()
}

// OfKind is every node of the kind in the file, in pre-order, indexed once per file.
func (f *File) OfKind(kind string) []*contract.Node {
	f.indexing.Do(func() {
		f.byKind = map[string][]*contract.Node{}
		for node := range f.Nodes() {
			f.byKind[node.Kind] = append(f.byKind[node.Kind], node)
		}
	})

	return f.byKind[kind]
}

// InModule is every file of the codebase that declares the module, indexed once per codebase.
func (c *Codebase) InModule(module string) []*File {
	return Analysis(c, "modules", func(c *Codebase) map[string][]*File {
		index := map[string][]*File{}
		for _, file := range c.files {
			if file.Module != "" {
				index[file.Module] = append(index[file.Module], file)
			}
		}

		return index
	})[module]
}

package engine

import "strings"

// Arguments are what the call is handed, in source order; none for a node that is no call or a language that
// registered no lists.
func (m Match) Arguments() []Match {
	return m.listed(m.grammar().Arguments)
}

// InheritedMembers are the members the type declaration takes from its parents and the traits its class uses, as the
// scan declares them, nearest first: a member declared outside the scan is not among them.
func (m Match) InheritedMembers() []Match {
	if !m.Is(TypeDeclaration) || m.file == nil {
		return nil
	}
	var inherited []Match
	for _, supplier := range append(m.Lineage(), m.UsedTraits()...) {
		for _, declaration := range m.file.Codebase().Declarations(supplier) {
			if declaration.file.Language() == m.file.Language() {
				inherited = append(inherited, declaration.Members()...)
			}
		}
	}

	return inherited
}

// Members are what the type declaration declares directly, in source order: never a nested type's own.
func (m Match) Members() []Match {
	if !m.Is(TypeDeclaration) {
		return nil
	}

	return m.listed(m.grammar().Members)
}

// Parameters are the parameters the function declares, in source order, each once: a variadic or a
// defaulted one, and Python's self, alike. A callback type written in its signature declares its own.
func (m Match) Parameters() []Match {
	if !m.Is(Function) {
		return nil
	}

	return m.listed(m.grammar().Parameters)
}

// DeclaringFunction is the function whose own parameter the parameter is; no node for a parameter of a
// declaration with no body to run, such as an interface's method, or of a function type.
func (m Match) DeclaringFunction() Match {
	function := m.Closest(Function)
	for _, declared := range function.Parameters() {
		if declared.node == m.node {
			return function
		}
	}

	return Match{}
}

// Lines is how many lines the node spans, its first and its last among them.
func (m Match) Lines() int {
	if m.node == nil {
		return 0
	}

	source, err := m.file.Source()
	if err != nil || m.node.Span.End > len(source) || m.node.Span.Start > m.node.Span.End {
		return 1
	}

	text := Source(source)
	last := m.node.Span.Start + len(strings.TrimRight(string(text[m.node.Span.Start:m.node.Span.End]), "\n"))

	return text.LineAt(last) - text.LineAt(m.node.Span.Start) + 1
}

// Complexity is how many ways through the node there are, counted as one and a way more for every branch,
// loop and catch in it, as its language's tree marks them. A function nested inside it is its own count.
func (m Match) Complexity() int {
	if m.node == nil {
		return 0
	}

	count := 1
	for _, below := range m.OwnDescendants() {
		if below.Is(Branch) || below.Is(Loop) || below.Is(Catch) {
			count++
		}
	}

	return count
}

// OwnDescendants are the nodes below this one that run as part of it, in pre-order: a function nested in it is
// among them, but what that function holds is its own.
func (m Match) OwnDescendants() []Match {
	var own []Match
	for _, child := range m.Children() {
		own = append(own, child)
		if !child.Is(Function) {
			own = append(own, child.OwnDescendants()...)
		}
	}

	return own
}

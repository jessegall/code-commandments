package engine

import "strings"

func (m Match) Arguments() []Match {
	return m.listed(m.grammar().Arguments)
}

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

func (m Match) Members() []Match {
	if !m.Is(TypeDeclaration) {
		return nil
	}

	return m.listed(m.grammar().Members)
}

func (m Match) Parameters() []Match {
	if !m.Is(Function) {
		return nil
	}

	return m.listed(m.grammar().Parameters)
}

func (m Match) DeclaringFunction() Match {
	function := m.Closest(Function)
	for _, declared := range function.Parameters() {
		if declared.node == m.node {
			return function
		}
	}

	return Match{}
}

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

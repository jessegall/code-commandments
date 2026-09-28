package engine

import (
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/contract"
)

// Lists are where a language keeps the lists every language has, which its tree files under fields of its own.
type Lists struct {
	// Arguments are what a call is handed, each once, named, spread and unpacked ones alike.
	Arguments func(Match) []Match
	// Members are what a type declaration declares directly: its methods, fields, constants and nested types.
	Members func(Match) []Match
}

// lists are the lists of each language that registered them.
var lists sync.Map

// ListAs has every match in a file of the language read its arguments and members as the lists say.
func ListAs(language contract.Language, read Lists) {
	lists.Store(language, read)
}

// InFields reads the children filling any of the fields, in source order.
func InFields(fields ...string) func(Match) []Match {
	return func(match Match) []Match {
		var filling []Match
		for _, child := range match.Children() {
			for _, field := range fields {
				if child.node.Field == field {
					filling = append(filling, child)
				}
			}
		}

		return filling
	}
}

// Arguments are what the call is handed, in source order; none for a node that is no call or a language that
// registered no lists.
func (m Match) Arguments() []Match {
	if read, listed := m.lists(); listed && read.Arguments != nil {
		return read.Arguments(m)
	}

	return nil
}

// Members are what the type declaration declares directly, in source order: never a nested type's own.
func (m Match) Members() []Match {
	if !m.Is(TypeDeclaration) {
		return nil
	}

	if read, listed := m.lists(); listed && read.Members != nil {
		return read.Members(m)
	}

	return nil
}

func (m Match) lists() (Lists, bool) {
	if m.file == nil {
		return Lists{}, false
	}

	read, listed := lists.Load(m.file.Language())
	if !listed {
		return Lists{}, false
	}

	return read.(Lists), true
}

// Parameters are the parameters the function declares, in source order, each once: a variadic or a
// defaulted one, and Python's self, alike.
func (m Match) Parameters() []Match {
	if !m.Is(Function) {
		return nil
	}

	var declared []Match
	for _, below := range m.Descendants() {
		if below.Is(Parameter) && below.Closest(Function).node == m.node {
			declared = append(declared, below)
		}
	}

	return declared
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

	return 1 + strings.Count(strings.TrimRight(string(source[m.node.Span.Start:m.node.Span.End]), "\n"), "\n")
}

// Complexity is how many ways through the node there are, counted as one and a way more for every branch,
// loop and catch in it, as its language's tree marks them. A function nested inside it is its own count.
func (m Match) Complexity() int {
	if m.node == nil {
		return 0
	}

	count := 1
	for _, below := range m.Descendants() {
		if !below.Is(Branch) && !below.Is(Loop) && !below.Is(Catch) {
			continue
		}

		if function := below.Closest(Function); function.Exists() && function.node != m.node && m.isAbove(function) {
			continue
		}

		count++
	}

	return count
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

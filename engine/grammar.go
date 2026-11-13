package engine

import (
	"slices"

	"github.com/jessegall/code-commandments/contract"
)

// Grammar is how a language holds what every language has in a place of its own: where its tree files a call's arguments or a type's parents, which declarations the language itself calls, what its test files are named.
type Grammar struct {
	Arguments       func(Match) []Match
	Members         func(Match) []Match
	Parameters      func(Match) []Match
	Extends         func(Match) []Match
	Implements      func(Match) []Match
	WritesAttribute func(Match, string) bool
	DeclaredName    func(Match) string
	Traits          func(Match) []Match
	ClassOf         func(Match) string
	ElementOf       func(Match) *contract.Type

	CodeWords func(Match) []string

	Documentable func(Match) bool

	TypeOf            func(Match) string
	Annotations       func(Match) []Match
	TypeKind          func(Match) string
	ReturnType        func(Match) Match
	ParameterType     func(Match) Match
	Constructs        func(Match) Match
	Callers           func(Match) []Match
	DocTags           func(Match) []string
	BodyHash          func(Match) string
	NodeHash          func(node Match, normalize bool) string
	TestFile          func(judged, path string) bool
	Implicit          func(Match) bool
	NamespaceOf       func(symbol string) string
	AnnotationNames   func(name string) []string
	Inherited         func(Match) bool
	Labels            func(Match) bool
	Continues         func(Match) bool
	Body              func(Match) []Match
	Otherwise         func(Match) []Match
	BindsItsOwnTest   func(Match) bool
	Condition         func(Match) Match
	ComparisonSubject func(Match) Match
	Receiver          func(Match) Match
	Declares          func(Match) bool
	Computes          func(Match) bool
	ElementName       func(Match) string
	Binds             func(Match) bool
}

var TypeKinds = []string{"class", "interface", "enum", "trait", "record", "struct", "protocol"}

func ReadAs(language contract.Language, read Grammar) {
	registers(language, func(row *spoken) { row.grammar = &read })
}

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

func InField(field string) func(Match) Match {
	return func(match Match) Match {
		return match.Child(field)
	}
}

func InAnyField(fields ...string) func(Match) Match {
	return func(match Match) Match {
		for _, child := range match.Children() {
			for _, field := range fields {
				if child.node.Field == field {
					return child
				}
			}
		}

		return Match{}
	}
}

func OfKind(kind, field string) func(Match) Match {
	return func(match Match) Match {
		if match.Kind() != kind {
			return Match{}
		}

		return match.Child(field)
	}
}

func KindIn(kinds ...string) func(Match) bool {
	return func(match Match) bool {
		return slices.Contains(kinds, match.Kind())
	}
}

func Kinds(words map[string]string) func(Match) string {
	return func(match Match) string {
		return words[match.Kind()]
	}
}

func (m Match) grammar() Grammar {
	if m.file == nil {
		return Grammar{}
	}

	read := spokenIn(m.file.Language()).grammar
	if read == nil {
		return Grammar{}
	}

	return *read
}

func (m Match) listed(read func(Match) []Match) []Match {
	if read == nil || m.node == nil {
		return nil
	}

	return read(m)
}

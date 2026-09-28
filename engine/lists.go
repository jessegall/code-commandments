package engine

import (
	"sync"

	"github.com/jessegall/code-commandments/contract"
)

// Lists are where a language keeps what every language has but its tree files in a place of its own.
type Lists struct {
	// Arguments are the values a call is handed, each once, named, spread and unpacked ones alike.
	Arguments func(Match) []Match
	// Members are what a type declaration declares directly: its methods, fields, constants and nested types.
	Members func(Match) []Match
	// Extends are the nodes naming the types a type declaration extends directly.
	Extends func(Match) []Match
	// Implements are the nodes naming the contracts a type declaration honours directly.
	Implements func(Match) []Match
	// Annotations are the nodes naming each attribute or decorator a declaration carries.
	Annotations func(Match) []Match
	// TypeKind is what kind of type a type declaration declares, in the neutral words of TypeKinds.
	TypeKind func(Match) string
	// ReturnType is the node a function's return type is written as; no node when none is written.
	ReturnType func(Match) Match
	// ParameterType is the node a parameter's type is written as; no node when none is written.
	ParameterType func(Match) Match
	// Constructs is the node naming the type a construction creates; no node for anything else.
	Constructs func(Match) Match
	// Callers are the calls reaching a function that its language's call graph finds beyond the symbols the tree
	// resolves, such as a method sent to a receiver whose type the graph works out.
	Callers func(Match) []Match
}

// TypeKinds are the kinds of type every language's declarations are told apart by.
var TypeKinds = []string{"class", "interface", "enum", "trait", "record", "struct", "protocol"}

// lists are the lists of each language that registered them.
var lists sync.Map

// ListAs has every match in a file of the language read what Lists names as read says.
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

// InField reads the first child filling the field.
func InField(field string) func(Match) Match {
	return func(match Match) Match {
		return match.Child(field)
	}
}

// OfKind reads the child filling the field of a node of the kind; no node for a node of another.
func OfKind(kind, field string) func(Match) Match {
	return func(match Match) Match {
		if match.Kind() != kind {
			return Match{}
		}

		return match.Child(field)
	}
}

// Kinds reads a node's kind as a neutral word, from the words its language's kinds stand for.
func Kinds(words map[string]string) func(Match) string {
	return func(match Match) string {
		return words[match.Kind()]
	}
}

func (m Match) lists() Lists {
	if m.file == nil {
		return Lists{}
	}

	read, listed := lists.Load(m.file.Language())
	if !listed {
		return Lists{}
	}

	return read.(Lists)
}

// listed reads a list the node's language registered; none when it registered none.
func (m Match) listed(list func(Lists) func(Match) []Match) []Match {
	if read := list(m.lists()); read != nil && m.node != nil {
		return read(m)
	}

	return nil
}

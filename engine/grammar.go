package engine

import (
	"slices"
	"sync"

	"github.com/jessegall/code-commandments/contract"
)

// Grammar is how a language holds what every language has in a place of its own: where its tree files a call's
// arguments or a type's parents, which declarations the language itself calls, what its test files are named.
type Grammar struct {
	// Arguments are the values a call is handed, each once, named, spread and unpacked ones alike.
	Arguments func(Match) []Match
	// Members are what a type declaration declares directly: its methods, fields, constants and nested types.
	Members func(Match) []Match
	// Parameters are the parameters a function declares itself, never those of a function type written in its
	// signature.
	Parameters func(Match) []Match
	// Extends are the nodes naming the types a type declaration extends directly.
	Extends func(Match) []Match
	// Implements are the nodes naming the contracts a type declaration honours directly.
	Implements func(Match) []Match
	// WritesAttribute says whether a markup element writes the attribute, plain or bound.
	WritesAttribute func(Match, string) bool
	// DeclaredName is the name a declaration that carries none gives what it declares: a PHP property statement's
	// one property.
	DeclaredName func(Match) string
	// Traits are the nodes naming the traits or mixins a type declaration uses directly.
	Traits func(Match) []Match
	// ClassOf is the class a node that declares none is about: the one it names, constructs or holds a value of;
	// empty when the language cannot tell.
	ClassOf func(Match) string
	// TypeOf is the type an expression has, whatever kind of type it is: a class, but a number or a string too,
	// where ClassOf answers a class alone. Empty where the language cannot tell.
	TypeOf func(Match) string
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
	// DocTags are the tags a declaration's documentation carries, each by its bare name: `deprecated`, `param`.
	DocTags func(Match) []string
	// BodyHash is the formatting-blind fingerprint of a function's body; empty for a node without one.
	BodyHash func(Match) string
	// TestFile says whether a file is test code, as the language's conventions name one: by its path as judged,
	// relative to the folder the scan was pointed at, and by its absolute path, where the project's own settings
	// for its tests are found.
	TestFile func(judged, path string) bool
	// Implicit says whether the language itself calls a declaration or binds a parameter, so no code of the
	// project names it: a constructor, a magic or dunder method, Python's self.
	Implicit func(Match) bool
	// NamespaceOf is the namespace part of a declaration's symbol, in the spelling of the language.
	NamespaceOf func(symbol string) string
	// AnnotationNames are the names an annotation of the name may be written by, the name itself among them.
	AnnotationNames func(name string) []string
	// Inherited says whether a member overrides or implements another, as the language's compiler decides it,
	// beside what the declared supertypes show.
	Inherited func(Match) bool
	// Labels says whether a name labels rather than reads, as a named argument's label does.
	Labels func(Match) bool
	// Continues says whether a branch continues the one it sits in rather than nesting inside it: an else-if.
	Continues func(Match) bool
	// Receiver is the object a call or a member read is sent to: the `$order` of `$order->ship()`; no node for a
	// free function's call, which is sent to nobody.
	Receiver func(Match) Match
	// Declares says whether an expression declares a name the code around it then uses: C#'s `out var artists` and
	// its `is Thing thing`, Python's `:=`. A condition holding one cannot be moved behind a name, because the name
	// it binds would go with it.
	Declares func(Match) bool
	// Binds says whether a statement only binds a name to a value and acts on nothing: `$order = $this->orders->find($id);`,
	// `order = self.orders.find(id)`, `var order = _orders.Find(id);`. The languages file an assignment so differently —
	// a statement of its own, an expression wrapped in one, a declaration — that only the language can say.
	Binds func(Match) bool
}

// TypeKinds are the kinds of type every language's declarations are told apart by.
var TypeKinds = []string{"class", "interface", "enum", "trait", "record", "struct", "protocol"}

// grammars are the grammar of each language that registered one.
var grammars sync.Map

// ReadAs has every match in a file of the language read as the grammar says.
func ReadAs(language contract.Language, read Grammar) {
	grammars.Store(language, read)
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

// InAnyField reads the first child filling one of the fields, which is how a language that files one thing under
// several names is read at once: PHP sends a method call to its `var` and a static one to its `class`.
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

// OfKind reads the child filling the field of a node of the kind; no node for a node of another.
func OfKind(kind, field string) func(Match) Match {
	return func(match Match) Match {
		if match.Kind() != kind {
			return Match{}
		}

		return match.Child(field)
	}
}

// KindIn says whether a node is of one of the kinds.
func KindIn(kinds ...string) func(Match) bool {
	return func(match Match) bool {
		return slices.Contains(kinds, match.Kind())
	}
}

// Kinds reads a node's kind as a neutral word, from the words its language's kinds stand for.
func Kinds(words map[string]string) func(Match) string {
	return func(match Match) string {
		return words[match.Kind()]
	}
}

func (m Match) grammar() Grammar {
	if m.file == nil {
		return Grammar{}
	}

	read, listed := grammars.Load(m.file.Language())
	if !listed {
		return Grammar{}
	}

	return read.(Grammar)
}

// listed reads a list of the node's with its language's reading; none when the language registered none.
func (m Match) listed(read func(Match) []Match) []Match {
	if read == nil || m.node == nil {
		return nil
	}

	return read(m)
}

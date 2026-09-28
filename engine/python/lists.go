package python

import (
	"slices"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// protocols are the classes a class lists among its bases to declare itself a protocol.
var protocols = []string{"typing.Protocol", "typing_extensions.Protocol"}

// enumerations are the classes an enumeration inherits from, however far up.
var enumerations = []string{"enum.Enum", "enum.IntEnum", "enum.StrEnum", "enum.Flag", "enum.IntFlag"}

func init() {
	engine.ListAs(contract.Python, engine.Lists{
		Arguments:     values,
		Members:       engine.InFields("body"),
		Extends:       engine.InFields("bases"),
		Annotations:   decorators,
		TypeKind:      classKind,
		ReturnType:    engine.InField("returns"),
		ParameterType: engine.InField("annotation"),
		Constructs:    constructed,
		Callers:       callers,
	})
}

// values are the values a call is handed: its positional arguments, starred ones included, then its keyword
// arguments' values.
func values(call engine.Match) []engine.Match {
	handed := call.ChildrenIn("args")
	for _, keyword := range call.ChildrenIn("keywords") {
		handed = append(handed, keyword.Child("value"))
	}

	return handed
}

// constructed is the class a call creates an instance of: its callee, when that resolves to a class.
func constructed(call engine.Match) engine.Match {
	if !call.Is(engine.Call) {
		return engine.Match{}
	}

	callee := call.Child("func")
	symbol := callee.Refers()
	if symbol == "" {
		return engine.Match{}
	}

	if declarations := call.Codebase().Declarations(symbol); len(declarations) > 0 && declarations[0].Is(engine.TypeDeclaration) {
		return callee
	}

	if call.OutsideKind(symbol) == "class" {
		return callee
	}

	return engine.Match{}
}

// callers are the calls the program's call graph finds reaching a def.
func callers(def engine.Match) []engine.Match {
	var calls []engine.Match
	for _, call := range Of(def.Codebase()).CallersOf(Node{Match: def}) {
		calls = append(calls, call.Match)
	}

	return calls
}

// decorators are the names of the decorators a definition carries: a called one by what it calls.
func decorators(definition engine.Match) []engine.Match {
	var names []engine.Match
	for _, decorator := range definition.ChildrenIn("decorator_list") {
		if decorator.Is(engine.Call) {
			decorator = decorator.Child("func")
		}

		names = append(names, decorator)
	}

	return names
}

// classKind is a protocol for a class listing Protocol among its bases, an enum for one inheriting from an
// enumeration, and a class otherwise.
func classKind(class engine.Match) string {
	for _, base := range class.Extends() {
		if slices.ContainsFunc(protocols, base.Names) {
			return "protocol"
		}
	}

	for _, ancestor := range class.Lineage() {
		if slices.Contains(enumerations, ancestor) {
			return "enum"
		}
	}

	return "class"
}

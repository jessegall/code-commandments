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
		Arguments:     engine.InFields("args", "keywords"),
		Members:       engine.InFields("body"),
		Extends:       engine.InFields("bases"),
		Annotations:   decorators,
		TypeKind:      classKind,
		ReturnType:    engine.InField("returns"),
		ParameterType: engine.InField("annotation"),
	})
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

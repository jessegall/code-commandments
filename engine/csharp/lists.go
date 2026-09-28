package csharp

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func init() {
	engine.ListAs(contract.CSharp, engine.Lists{
		Arguments:   arguments,
		Members:     engine.InFields("Members"),
		Extends:     func(declaration engine.Match) []engine.Match { return basesWhere(declaration, false) },
		Implements:  func(declaration engine.Match) []engine.Match { return basesWhere(declaration, true) },
		Annotations: attributes,
		TypeKind: engine.Kinds(map[string]string{"ClassDeclaration": "class", "InterfaceDeclaration": "interface",
			"EnumDeclaration": "enum", "RecordDeclaration": "record", "RecordStructDeclaration": "record", "StructDeclaration": "struct"}),
		ReturnType:    engine.InField("ReturnType"),
		ParameterType: engine.InField("Type"),
	})
}

// arguments are the expressions a call, object creation or indexer is handed.
func arguments(match engine.Match) []engine.Match {
	var handed []engine.Match
	for _, argument := range (Node{Match: match}).Arguments() {
		handed = append(handed, argument.Match)
	}

	return handed
}

// basesWhere are the types a declaration's base list names that are interfaces, or that are not. C# writes a
// base class and its interfaces in one list, so what each names says which it is; an interface's bases are the
// interfaces it extends.
func basesWhere(declaration engine.Match, interfaces bool) []engine.Match {
	var named []engine.Match
	for _, base := range declaration.Child("BaseList").ChildrenIn("Types") {
		written := base.Child("Type")
		isInterface := declaration.TypeKind() != "interface" && namesInterface(written)

		if declaration.TypeKind() == "interface" {
			isInterface = !interfaces
		}

		if isInterface == interfaces {
			named = append(named, written)
		}
	}

	return named
}

// namesInterface says whether the type the node names is declared an interface, in the scan or outside it.
func namesInterface(written engine.Match) bool {
	symbol := written.Refers()
	if declarations := written.Codebase().Declarations(symbol); len(declarations) > 0 {
		return declarations[0].TypeKind() == "interface"
	}

	return written.OutsideKind(symbol) == "interface"
}

// attributes are the names of the attributes a declaration carries, in every list.
func attributes(declaration engine.Match) []engine.Match {
	var names []engine.Match
	for _, list := range declaration.ChildrenIn("AttributeLists") {
		for _, attribute := range list.ChildrenIn("Attributes") {
			names = append(names, attribute.Child("Name"))
		}
	}

	return names
}

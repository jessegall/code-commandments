package golang

import (
	"strings"
	"unicode"
	"unicode/utf8"

	"github.com/jessegall/code-commandments/engine"
)

func members(declaration engine.Match) []engine.Match {
	written := declaration.Child("type")
	switch written.Kind() {
	case "StructType":
		return written.Child("fields").ChildrenIn("list")
	case "InterfaceType":
		return written.Child("methods").ChildrenIn("list")
	}
	return nil
}

func declaresMethods(declaration engine.Match) bool {
	return declaration.TypeKind() == "interface" && len(declaration.Members()) > 0
}

func isConstructor(function engine.Match) bool {
	result := function.ResultType()
	if function.Kind() != "FuncDecl" || function.Child("recv").Exists() || result == nil {
		return false
	}
	if result.Kind == "tuple" && len(result.Members) > 0 {
		result = result.Members[0]
	}
	if result.Kind == "pointer" {
		result = result.Element
	}
	if result == nil || result.Kind != "named" {
		return false
	}
	name := result.Name[strings.LastIndex(result.Name, ".")+1:]
	return name != "" && function.Name() == "New"+name
}

func privateMember(member engine.Match) bool {
	field := member.Kind() == "Field" && member.Parent().Parent().Kind() == "StructType"
	if !field && (Node{Match: member}).OwnerSymbol() == "" {
		return false
	}
	first, _ := utf8.DecodeRuneInString(member.Name())
	return unicode.IsLower(first)
}

func collaborator(member engine.Match) bool {
	if member.Kind() != "Field" || !member.EnclosingType().Exists() || !privateMember(member) {
		return false
	}
	typed := member.Typed()
	return typed != nil && (typed.Kind == "pointer" || typed.Kind == "named")
}

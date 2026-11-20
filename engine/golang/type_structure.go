package golang

import (
	"slices"
	"strings"
	"unicode"
	"unicode/utf8"

	"github.com/jessegall/code-commandments/engine"
)

func testAssertion(call engine.Match) bool {
	receiver := call.Receiver()
	method := call.Child("fun").Child("sel").Name()
	if receiver.Kind() != "Ident" || !slices.Contains([]string{"Error", "Errorf", "Fatal", "Fatalf"}, method) {
		return false
	}
	for _, parameter := range call.EnclosingFunction().Parameters() {
		if !slices.ContainsFunc(parameter.ChildrenIn("names"), func(name engine.Match) bool { return name.Name() == receiver.Name() }) {
			continue
		}
		written := parameter.Child("type").Child("x")
		if parameter.Child("type").Kind() != "StarExpr" || written.Kind() != "SelectorExpr" ||
			!slices.Contains([]string{"T", "B", "F"}, written.Child("sel").Name()) {
			return false
		}
		for _, imported := range call.Root().Descendants() {
			if imported.Kind() != "ImportSpec" || imported.Child("path").Written() != `"testing"` {
				continue
			}
			alias := imported.Child("name").Name()
			if alias == "" {
				alias = "testing"
			}
			return written.Child("x").Name() == alias
		}
	}

	return false
}

func passesPublicStruct(parameter engine.Match) bool {
	typed := parameter.Typed()
	types, known := parameter.Types()
	if !known || typed == nil || typed.Kind != "named" {
		return false
	}
	declared, found := types.Declaration(typed.Name)
	if !found || declared.TypeKind() != "struct" {
		return false
	}
	fields := declared.Members()
	return len(fields) > 0 && !slices.ContainsFunc(fields, func(field engine.Match) bool {
		return !(Node{Match: field}).IsExported()
	})
}

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

package php

import (
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func interfaceSignatures(declaration engine.Match) []string {
	if len(declaration.ChildrenIn("extends")) > 0 || hasTemplate(declaration) ||
		slices.ContainsFunc(declaration.Members(), func(member engine.Match) bool { return member.Kind() != "Stmt_ClassMethod" }) {
		return nil
	}
	var signatures []string
	for _, method := range Methods(declaration) {
		result := interfaceTypeKey(method.Node().Returns)
		if result == "" || hasTemplate(method) {
			return nil
		}
		parts := []string{strings.ToLower(method.Name()), result,
			strconv.FormatBool(method.HasModifier("static")), strconv.FormatBool(method.HasFlag("by-ref"))}
		for _, param := range Params(method) {
			typed := interfaceTypeKey(param.Node().Declared)
			defaultValue, known := interfaceDefaultKey(param.Child("default"))
			if typed == "" || !known {
				return nil
			}
			parts = append(parts, param.Child("var").Name(), typed, strings.Join(param.Node().Flags, ","), defaultValue)
		}
		signatures = append(signatures, strings.Join(parts, "\x00"))
	}

	return signatures
}

func hasTemplate(declaration engine.Match) bool {
	return slices.ContainsFunc(declaration.DocTags(), func(tag string) bool { return strings.HasPrefix(tag, "@template") })
}

func usesContract(implementation, contract engine.Match) bool {
	namespace := implementation.Namespace()
	return slices.ContainsFunc(implementation.Root().Descendants(), func(reference engine.Match) bool {
		node := Node{Match: reference}
		if !node.IsClassReference() || !strings.EqualFold(strings.TrimLeft(reference.Name(), `\`), contract.Identity()) {
			return false
		}
		return EnclosingClassName(reference) == implementation.Identity() ||
			reference.Parent().Kind() == "UseItem" && node.NamespaceName() == namespace
	})
}

func interfaceDefaultKey(value engine.Match) (string, bool) {
	switch value.Kind() {
	case "":
		return "", true
	case "Scalar_String", "Scalar_Int", "Scalar_Float":
		text, known := value.Text()
		return value.Kind() + strconv.Quote(text), known
	case "Expr_ConstFetch":
		literal := strings.ToLower(value.Node().Literal)
		return literal, slices.Contains([]string{"null", "true", "false"}, literal)
	case "Expr_Array":
		return "[]", len(value.ChildrenIn("items")) == 0
	}

	return "", false
}

func interfaceTypeKey(typed *contract.Type) string {
	if typed == nil {
		return ""
	}
	switch typed.Kind {
	case "union", "intersection":
		keys := make([]string, 0, len(typed.Members))
		for _, member := range typed.Members {
			key := interfaceTypeKey(member)
			if key == "" {
				return ""
			}
			keys = append(keys, key)
		}
		slices.Sort(keys)
		if typed.Kind == "intersection" {
			return "(" + strings.Join(keys, "&") + ")"
		}
		return strings.Join(keys, "|")
	case "named", "keyword":
		name := strings.ToLower(strings.TrimLeft(typed.Name, `\`))
		if name == "" || name == "self" || name == "static" || name == "parent" {
			return ""
		}
		if typed.Nullable && name != "null" {
			keys := []string{name, "null"}
			slices.Sort(keys)
			return strings.Join(keys, "|")
		}
		return name
	}

	return ""
}

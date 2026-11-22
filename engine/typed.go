package engine

import (
	"github.com/jessegall/code-commandments/contract"
	"strings"
)

func (m Match) ResultType() *contract.Type {
	if m.node == nil || !m.Is(Function) {
		return nil
	}

	return m.Child("returnType").Typed()
}

// Typed is the type of the node: the one the compiler resolved, else the one of its declared type or, for a
// function, its return type.
func (m Match) Typed() *contract.Type {
	if m.node == nil {
		return nil
	}
	if m.node.Resolved != nil {
		return m.node.Resolved
	}
	if declared := m.Child("declaredType"); declared.Exists() {
		return declared.Typed()
	}
	if m.Kind() == "function" {
		return m.Child("returnType").Typed()
	}
	if m.Kind() == "type-expression" {
		return m.writtenType()
	}

	return nil
}

// writtenType is the type a type-expression states, for one no compiler resolved: a keyword, a named type, a nullable,
// a union or intersection, an array, a map. Any other form is not known.
func (m Match) writtenType() *contract.Type {
	node := m.node
	written := func(children []Match) []*contract.Type {
		var types []*contract.Type
		for _, child := range children {
			typed := child.Typed()
			if typed == nil {
				return nil
			}
			types = append(types, typed)
		}

		return types
	}
	switch node.Form {
	case "keyword":
		if node.Refers != "" {
			return &contract.Type{Text: node.Refers, Kind: "named", Name: node.Refers, Origin: "written"}
		}

		return &contract.Type{Text: node.Name, Kind: "keyword", Name: node.Name, Origin: "written"}
	case "named":
		name := node.Refers
		if name == "" {
			name = node.Name
		}
		if name == "" {
			return nil
		}

		return &contract.Type{Text: name, Kind: "named", Name: name, Args: written(m.ChildrenIn("typeArguments")), Origin: "written"}
	case "nullable":
		inner := m.Child("element").Typed()
		if inner == nil {
			return nil
		}
		nullable, absent := *inner, true
		nullable.Nullable, nullable.Text = &absent, "?"+inner.Text

		return &nullable
	case "union", "intersection":
		members := written(m.ChildrenIn("members"))
		if len(members) == 0 {
			return nil
		}
		texts := make([]string, len(members))
		for at, member := range members {
			texts[at] = member.Text
		}

		return &contract.Type{Text: strings.Join(texts, "|"), Kind: node.Form, Members: members, Origin: "written"}
	case "array":
		element := m.Child("element").Typed()
		args := written(m.ChildrenIn("typeArguments"))
		if element == nil && len(args) == 1 {
			element = args[0]
		}
		if element == nil {
			return nil
		}

		return &contract.Type{Text: element.Text + "[]", Kind: "array", Element: element, Args: []*contract.Type{element}, Origin: "written"}
	case "function":
		return &contract.Type{Text: node.Name, Kind: "function", Origin: "written"}
	case "map":
		args := written(m.ChildrenIn("typeArguments"))
		if len(args) != 2 {
			return nil
		}

		return &contract.Type{Text: "map[" + args[0].Text + "]" + args[1].Text, Kind: "map", Args: args, Origin: "written"}
	}

	return nil
}

func (m Match) ElementType() *contract.Type {
	typed := m.Typed()

	switch {
	case typed == nil:
		return m.elementItsLanguageKnows()
	case typed.Element != nil:
		return typed.Element
	case typed.Kind == "array" && len(typed.Args) == 1:
		return typed.Args[0]
	case typed.Kind == "map" && len(typed.Args) == 2:
		return typed.Args[1]
	}

	return m.elementItsLanguageKnows()
}

func (m Match) elementItsLanguageKnows() *contract.Type {
	return nil
}

func (m Match) AdmitsAbsence() bool {
	if m.HasFlag("optional") {
		return true
	}
	if declared := m.Child("declaredType").Typed(); declared != nil {
		return declared.Nullable != nil && *declared.Nullable
	}
	typed := m.Typed()

	return typed != nil && typed.Nullable != nil && *typed.Nullable
}

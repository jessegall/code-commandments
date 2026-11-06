package php

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

var builtins = []string{
	"array", "string", "int", "float", "bool", "mixed", "object", "void",
	"never", "iterable", "callable", "self", "static", "parent", "true",
	"false", "null",
}

var specialClassNames = []string{"self", "static", "parent"}

// TypeName reads a written type: the class it names, whether it admits null, how it renders.
type TypeName struct {
	written *contract.Type
}

func Written(t *contract.Type) TypeName {
	return TypeName{written: t}
}

func IsClassName(name string) bool {
	return name != "" && !slices.Contains(builtins, strings.ToLower(strings.TrimLeft(name, `?\`)))
}

func (n TypeName) Class() string {
	switch {
	case n.written == nil:
		return ""
	case n.isUnion():
		return n.soleClassOfUnion()
	case n.isNamed() && IsClassName(n.written.Name):
		return n.written.Name
	}

	return ""
}

func (n TypeName) SimpleName() string {
	switch {
	case n.written == nil:
		return ""
	case n.isUnion():
		if member := n.soleNonNullMember(); member != nil && member.isNamed() {
			return member.written.Name
		}

		return ""
	case n.isNamed():
		return n.written.Name
	}

	return ""
}

func (n TypeName) NullableClass() string {
	switch {
	case n.isSugared():
		return TypeName{written: n.bare()}.Class()
	case n.isNullableUnion():
		return n.soleClassOfUnion()
	}

	return ""
}

func (n TypeName) Named() *contract.Type {
	switch {
	case n.written == nil:
		return nil
	case n.isNamed():
		return n.written
	case !n.isUnion():
		return nil
	}

	member := n.soleNonNullMember()
	if member == nil || !member.isNamed() {
		return nil
	}
	if !n.isNullableUnion() {
		return member.written
	}
	nullable := *member.written
	nullable.Nullable = true
	nullable.Text = "?" + member.written.Text

	return &nullable
}

func (n TypeName) IsNullable() bool {
	return n.isSugared() || n.isNullableUnion()
}

func (n TypeName) IsNullableArray() bool {
	if n.isSugared() {
		return n.written.Kind == "keyword" && n.written.Name == "array"
	}
	if !n.isNullableUnion() {
		return false
	}
	for _, member := range n.members() {
		if member.written.Kind == "keyword" && member.written.Name == "array" {
			return true
		}
	}

	return false
}

func (n TypeName) Render() string {
	switch {
	case n.written == nil:
		return ""
	case n.isSugared():
		return "?" + TypeName{written: n.bare()}.Render()
	case n.isNamed():
		return strings.TrimLeft(n.written.Name, `\`)
	case n.isUnion() || n.written.Kind == "intersection":
		var parts []string
		for _, member := range n.members() {
			parts = append(parts, member.Render())
		}
		slices.Sort(parts)
		if n.isUnion() {
			return strings.Join(parts, "|")
		}

		return strings.Join(parts, "&")
	}

	return ""
}

func (n TypeName) UnionIncludes(fqcn string) bool {
	if !n.isUnion() {
		return false
	}
	for _, member := range n.members() {
		if member.isWrittenAsName() && strings.TrimLeft(member.written.Name, `\`) == strings.TrimLeft(fqcn, `\`) {
			return true
		}
	}

	return false
}

func (n TypeName) Names() []string {
	if n.written == nil {
		return nil
	}
	if n.isUnion() || n.written.Kind == "intersection" {
		var names []string
		for _, member := range n.members() {
			names = append(names, member.Names()...)
		}

		return names
	}
	if n.isWrittenAsName() {
		return []string{n.written.Name}
	}

	return nil
}

func PromisesScalar(param engine.Match, scalar string) bool {
	declared := Written(param.Node().Declared)

	return param.Exists() && !param.Child("default").Exists() && declared.written != nil && !declared.isSugared() &&
		declared.written.Kind == "keyword" && !declared.isWrittenAsName() &&
		declared.written.Name == scalar
}

func Overlaps(one, other string) bool {
	if one == "" || other == "" || one == other {
		return true
	}
	others := widened(other)
	for _, member := range widened(one) {
		if slices.Contains(others, member) {
			return true
		}
	}

	return false
}

func widened(rendered string) []string {
	var members []string
	for _, member := range strings.Split(strings.TrimPrefix(rendered, "?"), "|") {
		members = append(members, member)
		if member == "array" {
			members = append(members, "iterable")
		}
		if member == "static" || member == "self" || member == "$this" {
			members = append(members, "self")
		}
	}
	if strings.HasPrefix(rendered, "?") {
		members = append(members, "null")
	}

	return members
}

func (n TypeName) isWrittenAsName() bool {
	return n.written != nil && (n.written.Kind == "named" || (n.written.Kind == "keyword" && slices.Contains(specialClassNames, strings.ToLower(n.written.Name))))
}

func (n TypeName) isUnion() bool {
	return n.written != nil && n.written.Kind == "union"
}

func (n TypeName) isNamed() bool {
	return n.written != nil && (n.written.Kind == "named" || n.written.Kind == "keyword")
}

func (n TypeName) isSugared() bool {
	return n.isNamed() && n.written.Nullable && !slices.Contains([]string{"null", "mixed"}, strings.ToLower(n.written.Name))
}

func (n TypeName) bare() *contract.Type {
	bare := *n.written
	bare.Nullable = false
	bare.Text = strings.TrimPrefix(bare.Text, "?")

	return &bare
}

func (n TypeName) members() []TypeName {
	var members []TypeName
	for _, member := range n.written.Members {
		members = append(members, TypeName{written: member})
	}

	return members
}

func (n TypeName) isNullMember() bool {
	return n.isNamed() && strings.ToLower(n.written.Name) == "null"
}

func (n TypeName) isNullableUnion() bool {
	if !n.isUnion() {
		return false
	}

	return slices.ContainsFunc(n.members(), TypeName.isNullMember)
}

func (n TypeName) soleNonNullMember() *TypeName {
	var rest []TypeName
	for _, member := range n.members() {
		if !member.isNullMember() {
			rest = append(rest, member)
		}
	}
	if len(rest) != 1 {
		return nil
	}

	return &rest[0]
}

func (n TypeName) soleClassOfUnion() string {
	var classes []string
	for _, member := range n.members() {
		if member.isNullMember() {
			continue
		}
		class := member.Class()
		if class == "" {
			return ""
		}
		classes = append(classes, class)
	}
	if len(classes) != 1 {
		return ""
	}

	return classes[0]
}

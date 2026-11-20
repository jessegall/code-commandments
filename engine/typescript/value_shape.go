package typescript

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

func MembersWithParameterFields(declaration engine.Match) []engine.Match {
	members := declaration.ChildrenIn("members")
	for _, member := range members {
		if member.IsConstructor() {
			for _, parameter := range member.Parameters() {
				if slices.ContainsFunc([]string{"public", "protected", "private", "readonly"}, parameter.HasModifier) {
					members = append(members, parameter)
				}
			}
		}
	}
	return members
}

func RecordKeys(record engine.Match) []string {
	if record.Kind() != "ObjectLiteralExpression" {
		return nil
	}
	var keys []string
	for _, property := range record.ChildrenIn("properties") {
		if property.Kind() != "PropertyAssignment" && property.Kind() != "ShorthandPropertyAssignment" {
			return nil
		}
		name := property.Child("name")
		key := name.Name()
		if key == "" {
			key, _ = name.Text()
		}
		if key == "" || name.Kind() == "ComputedPropertyName" {
			return nil
		}
		keys = append(keys, key)
	}
	return keys
}

func StaticStorage(declaration engine.Match) bool {
	return declaration.HasModifier("static") && !declaration.HasModifier("readonly") ||
		declaration.Kind() == "VariableDeclaration" && !declaration.HasFlag("constant") &&
			declaration.Parent().Parent().Parent().Kind() == "SourceFile"
}

func PrivateMember(member engine.Match) bool {
	return member.HasModifier("private") || member.Child("name").Kind() == "PrivateIdentifier"
}

func ConstructorCollaborator(member engine.Match) bool {
	typed := member.Typed()
	return member.Is(engine.Parameter) && member.DeclaringFunction().IsConstructor() &&
		PrivateMember(member) && typed != nil && typed.Kind == "named"
}

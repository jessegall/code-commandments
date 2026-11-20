package typescript

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

func ReactiveMember(member engine.Match) bool {
	typed := member.Typed()
	if typed == nil || member.Parent().MemberName() != "value" {
		return false
	}
	name, _, _ := strings.Cut(typed.Text, "<")

	return slices.Contains(refWrappers, name)
}

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

func ReturnsVueState(record engine.Match) bool {
	script := record.Parent()
	for script.Exists() && script.Kind() != "SourceFile" {
		script = script.Parent()
	}
	outer := ModuleOf(script.ChildrenIn("statements"))
	local := ModuleOf(record.EnclosingFunction().Child("body").ChildrenIn("statements"))
	for _, returned := range objectShape(Node{record}) {
		variable, found := local.Variable(returned.Local)
		if !found {
			variable, found = outer.Variable(returned.Local)
		}
		if !found || variable.InitCall == nil {
			continue
		}
		name := variable.InitCall.Callee
		imported, known := outer.ImportOf(name)
		if known && (imported.Specifier == "vue" || imported.Specifier == "@vue/reactivity") &&
			slices.Contains(reactive, imported.Bindings[name]) {
			return true
		}
	}
	return false
}

func StaticStorage(declaration engine.Match) bool {
	source := declaration.Parent().Parent().Parent()
	return declaration.HasModifier("static") && !declaration.HasModifier("readonly") ||
		declaration.Kind() == "VariableDeclaration" && !declaration.HasFlag("constant") &&
			source.Kind() == "SourceFile" && !isComponentSetup(source.Parent())
}

func isComponentSetup(block engine.Match) bool {
	return block.Kind() == "Block" && block.Name() == "script" && slices.ContainsFunc(block.ChildrenIn("attributes"), func(attribute engine.Match) bool {
		return attribute.Kind() == "Attribute" && attribute.Name() == "setup"
	})
}

func PrivateMember(member engine.Match) bool {
	return member.HasModifier("private") || member.Child("name").Kind() == "PrivateIdentifier"
}

func ConstructorCollaborator(member engine.Match) bool {
	typed := member.Typed()
	return member.Is(engine.Parameter) && member.DeclaringFunction().IsConstructor() &&
		PrivateMember(member) && typed != nil && typed.Kind == "named"
}

package typescript

import "github.com/jessegall/code-commandments/engine"

func FillsDataWithPlaceholder(built engine.Match) bool {
	if built.Is(engine.Construction) {
		return fillsConstructorSlot(built)
	}
	if built.Kind() == "ObjectLiteralExpression" {
		return fillsObjectField(built)
	}
	return false
}

func fillsConstructorSlot(built engine.Match) bool {
	for _, constructor := range built.Codebase().Declarations(built.TargetSymbol()) {
		if !readonlyDataClass(constructor.EnclosingType()) {
			continue
		}
		parameters := constructor.Parameters()
		for index, argument := range built.Arguments() {
			if index < len(parameters) && argument.IsBlankString() && requiredString(parameters[index]) {
				return true
			}
		}
	}
	return false
}

func readonlyDataClass(class engine.Match) bool {
	if class.TypeKind() != "class" {
		return false
	}
	data := false
	for _, member := range class.Members() {
		if member.IsConstructor() {
			for _, parameter := range member.Parameters() {
				data = data || parameter.HasModifier("readonly")
			}
			continue
		}
		if member.Kind() != "PropertyDeclaration" && !member.Is(engine.Parameter) || !member.HasModifier("readonly") {
			return false
		}
		data = true
	}
	return data
}

func fillsObjectField(built engine.Match) bool {
	annotation := built.Parent().Child("type")
	if !annotation.Exists() {
		return false
	}
	for _, literal := range built.ChildrenIn("properties") {
		if !literal.Child("initializer").IsBlankString() {
			continue
		}
		for _, declaration := range built.Codebase().Declarations(annotation.Child("typeName").Refers()) {
			for _, field := range structuralFields(declaration) {
				if field.Name() == literal.NameRead() && requiredString(field) {
					return true
				}
			}
		}
	}
	return false
}

func structuralFields(declaration engine.Match) []engine.Match {
	if declaration.Kind() == "InterfaceDeclaration" {
		return declaration.Members()
	}
	return declaration.Child("type").ChildrenIn("members")
}

func requiredString(field engine.Match) bool {
	if field.HasFlag("optional") || field.DefaultValue().Exists() {
		return false
	}
	return field.DeclaresString() || field.Child("type").Written() == "string"
}

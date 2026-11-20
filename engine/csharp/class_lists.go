package csharp

import "github.com/jessegall/code-commandments/engine"

func listedClasses(list engine.Match) []string {
	node := Node{Match: list}
	if !node.Is("ArrayCreationExpression", "ImplicitArrayCreationExpression", "CollectionExpression") {
		return nil
	}
	parent := list.Parent()
	if parent.Kind() != "Argument" && !parent.Is(engine.Return) {
		return nil
	}
	var classes []string
	for _, item := range node.writtenElements() {
		if !item.Is("TypeOfExpression") {
			return nil
		}
		class := item.Match.Child("Type").ConcreteClassReference()
		if class == "" {
			return nil
		}
		classes = append(classes, class)
	}

	return classes
}

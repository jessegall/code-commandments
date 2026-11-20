package typescript

import "github.com/jessegall/code-commandments/engine"

func ListedClasses(list engine.Match) []string {
	if list.Kind() != "ArrayLiteralExpression" {
		return nil
	}
	parent := list.Parent()
	if !parent.Is(engine.Return) && !(parent.Is(engine.Call) && list.Field() == "arguments") {
		return nil
	}
	var classes []string
	for _, item := range list.ChildrenIn("elements") {
		class := item.ConcreteClassReference()
		if item.Kind() != "Identifier" || class == "" {
			return nil
		}
		classes = append(classes, class)
	}

	return classes
}

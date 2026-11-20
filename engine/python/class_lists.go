package python

import "github.com/jessegall/code-commandments/engine"

func listedClasses(list engine.Match) []string {
	if list.Kind() != "List" {
		return nil
	}
	parent := list.Parent()
	if !parent.Is(engine.Return) && !(parent.Is(engine.Call) && list.Field() == "args") {
		return nil
	}
	var classes []string
	for _, item := range list.ChildrenIn("elts") {
		class := item.ConcreteClassReference()
		if class == "" {
			return nil
		}
		classes = append(classes, class)
	}

	return classes
}

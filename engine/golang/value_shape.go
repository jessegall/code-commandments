package golang

import "github.com/jessegall/code-commandments/engine"

func recordKeys(record engine.Match) []string {
	if record.Kind() != "CompositeLit" {
		return nil
	}
	typed := record.Typed()
	if typed == nil || typed.Kind != "map" {
		return nil
	}
	var keys []string
	for _, element := range record.ChildrenIn("elts") {
		key := element.Child("key")
		if key.LiteralKind() != "string" {
			return nil
		}
		name, _ := key.Text()
		keys = append(keys, name)
	}
	return keys
}

func staticStorage(declaration engine.Match) bool {
	return declaration.Kind() == "ValueSpec" && declaration.Parent().Operator() == "var" &&
		declaration.Parent().Parent().Kind() == "File"
}

package php

import "github.com/jessegall/code-commandments/engine"

var indexedRegistryRead = engine.IndexedArgument("Expr_ArrayDimFetch", "var", "dim")

func registryFields(class engine.Match) []string {
	var names []string
	for _, field := range Fields(class) {
		names = append(names, field.Name)
	}
	return names
}

func missingKeys(negation engine.Match) []engine.Match {
	if negation.Kind() != "Expr_BooleanNot" {
		return nil
	}
	test := negation.Child("expr")
	if test.Kind() == "Expr_Isset" {
		var stores []engine.Match
		for _, checked := range test.ChildrenIn("vars") {
			if store, key := indexedRegistryRead(checked); key.Exists() {
				stores = append(stores, store)
			}
		}
		return stores
	}
	if (Node{Match: test}).CallsFunction("array_key_exists") {
		if args := Arguments(test); len(args) == 2 {
			return []engine.Match{args[1].Child("value")}
		}
	}
	return nil
}

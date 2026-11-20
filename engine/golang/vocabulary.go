package golang

import "github.com/jessegall/code-commandments/engine"

func vocabularyWords(argument engine.Match) []string {
	reference := argument.Refers()
	if argument.Kind() == "SelectorExpr" {
		reference = argument.Child("sel").Refers()
	}
	if reference == "" {
		return nil
	}
	for _, declared := range argument.Codebase().Declarations(reference) {
		if declared.Kind() != "ValueSpec" || declared.Parent().Operator() != "const" || !declared.Child("type").Exists() {
			continue
		}
		values := declared.ChildrenIn("values")
		if len(values) != 1 || values[0].Node().Literal != "string" {
			continue
		}
		if word, known := values[0].Text(); known {
			return []string{word}
		}
	}

	return nil
}

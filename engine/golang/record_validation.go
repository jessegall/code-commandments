package golang

import "github.com/jessegall/code-commandments/engine"

func validatesRecord(parameter engine.Match) bool {
	function := parameter.DeclaringFunction()
	result := function.ResultType()
	if result == nil || result.Text != "error" {
		return false
	}
	found := false
	for _, use := range function.OwnDescendants() {
		if use.Kind() != "IndexExpr" || use.Child("x").Name() != parameter.Name() {
			continue
		}
		if use.Grouped().Parent().Kind() != "TypeAssertExpr" {
			return false
		}
		found = true
	}
	return found
}

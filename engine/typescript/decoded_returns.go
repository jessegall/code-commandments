package typescript

import "github.com/jessegall/code-commandments/engine"

func DecodesJSON(call engine.Match) bool {
	return isJSONCall(call, "parse")
}

func EncodesJSON(call engine.Match) bool {
	return isJSONCall(call, "stringify")
}

func isJSONCall(call engine.Match, name string) bool {
	return call.TargetSymbol() == "JSON."+name
}

func ReturnsUntypedData(function engine.Match) bool {
	if !function.Exists() {
		return false
	}
	typed := function.ReturnType().Typed()
	return typed == nil || typed.Text == "any" || typed.Text == "unknown" || engine.IsUntypedCollection(typed)
}

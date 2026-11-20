package csharp

import "github.com/jessegall/code-commandments/engine"

func nullSwitchDefault(match engine.Match) bool {
	node := Node{Match: match}
	if !node.Is("SwitchExpression", "SwitchStatement") {
		return false
	}
	value := node.FallbackValue()
	return value.IsAbsenceValue() && Of(match.Codebase()).NamesEveryMember(node) &&
		(!value.Is("FalseLiteralExpression") || !node.IsWithinTryMethod())
}

package csharp

import "github.com/jessegall/code-commandments/engine"

func callbackTarget(call engine.Match) engine.Match {
	expression := call.Child("Expression")
	if expression.Is(engine.Identifier) {
		return expression
	}
	if expression.NameRead() != "Invoke" {
		return engine.Match{}
	}
	if call.Parent().Is(engine.NullSafe) {
		return call.Parent().Child("Expression")
	}
	return expression.Receiver()
}

func defaultValue(declaration engine.Match) engine.Match {
	for _, child := range declaration.Children() {
		if child.Kind() == "EqualsValueClause" {
			return child.Child("Value")
		}
	}
	return engine.Match{}
}

package frontend

import "github.com/jessegall/code-commandments/engine"

func fallback(expression engine.Match) engine.Match {
	if expression.Kind() == "BinaryExpression" && expression.Operator() == "||" {
		return expression.Child("right")
	}
	one, other := conditionalArms(expression)
	if one.Exists() && other.Exists() && one.SameSyntax(expression.Condition()) {
		return other
	}
	return engine.Match{}
}

func conditionalArms(expression engine.Match) (engine.Match, engine.Match) {
	if expression.Kind() != "ConditionalExpression" {
		return engine.Match{}, engine.Match{}
	}
	return expression.Child("whenTrue"), expression.Child("whenFalse")
}

func iterated(loop engine.Match) engine.Match {
	if loop.Kind() == "ForOfStatement" {
		return loop.Child("expression")
	}
	return engine.Match{}
}

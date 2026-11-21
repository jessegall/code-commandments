package typescript

import "github.com/jessegall/code-commandments/engine"

func DispatchTest(test engine.Match) (engine.Match, string) {
	if test.Operator() != "instanceof" {
		return engine.Match{}, ""
	}
	class := test.Child("right").ConcreteClassReference()
	return test.Child("left"), class
}

func IsConstClassEnum(declared engine.Match) bool {
	if declared.TypeKind() != "class" || len(declared.Extends()) > 0 {
		return false
	}
	members := declared.Members()
	if len(members) < 2 {
		return false
	}
	for _, member := range members {
		if member.Kind() != "PropertyDeclaration" || !member.HasModifier("static") || !member.HasModifier("readonly") || !member.Child("initializer").Is(engine.Literal) {
			return false
		}
	}
	for _, reference := range declared.Referrers() {
		access := reference.Parent()
		if access.Kind() == "PropertyAccessExpression" && access.Receiver().Node() == reference.Node() &&
			(access.Parent().Is(engine.Comparison) || access.Parent().Kind() == "CaseClause") {
			return true
		}
	}
	return false
}

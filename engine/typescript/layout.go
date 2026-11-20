package typescript

import "github.com/jessegall/code-commandments/engine"

func MemberOrder(member engine.Match) int {
	switch member.Kind() {
	case "PropertyDeclaration":
		if member.HasModifier("readonly") && member.HasModifier("static") {
			return 1
		}
		return 2
	case "Constructor", "MethodDeclaration":
		return 3
	}
	return 0
}

func TupleSlots(tuple engine.Match) []engine.Match {
	if tuple.Kind() != "TupleType" {
		return nil
	}
	var slots []engine.Match
	for _, slot := range tuple.ChildrenIn("elements") {
		if slot.Kind() != "NamedTupleMember" {
			slots = append(slots, slot)
		}
	}
	return slots
}

func JoinedLines(call engine.Match) []engine.Match {
	parts := call.Receiver()
	arguments := call.Arguments()
	if call.CalleeName() != "join" || parts.Kind() != "ArrayLiteralExpression" || len(arguments) != 1 {
		return nil
	}
	if separator, _ := arguments[0].Text(); separator != "\n" {
		return nil
	}
	return parts.ChildrenIn("elements")
}

func RedundantArrowReturn(arrow engine.Match) bool {
	if arrow.Kind() != "ArrowFunction" {
		return false
	}
	written := arrow.Child("type")
	read, ok := (Node{arrow.Child("body")}).InferType()
	if !ok {
		return false
	}
	inferred, scalar := read.(KeywordType)
	return scalar && written.Written() == inferred.Name
}

func NullSwitchDefault(arm engine.Match) bool {
	if arm.Kind() != "DefaultClause" {
		return false
	}
	statements := arm.ChildrenIn("statements")
	if len(statements) != 1 || !statements[0].Is(engine.Return) ||
		statements[0].Child("expression").LiteralKind() != "null" {
		return false
	}
	cases := map[string]bool{}
	owner := ""
	for _, clause := range arm.Parent().ChildrenIn("clauses") {
		if clause.Kind() == "DefaultClause" {
			continue
		}
		enum, name := EnumCase(clause.Child("expression"))
		if enum == "" || owner != "" && owner != enum {
			return false
		}
		owner = enum
		cases[name] = true
	}
	return len(cases) >= 2 && len(cases) == len(engine.EnumsOf(arm.Codebase())[owner])
}

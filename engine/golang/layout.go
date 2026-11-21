package golang

import "github.com/jessegall/code-commandments/engine"

func joinedLines(call engine.Match) []engine.Match {
	arguments := call.Arguments()
	if call.TargetSymbol() != "strings.Join" || len(arguments) != 2 || arguments[0].Kind() != "CompositeLit" {
		return nil
	}
	if separator, _ := arguments[1].Text(); separator != "\n" {
		return nil
	}
	return arguments[0].ChildrenIn("elts")
}

func nullSwitchDefault(arm engine.Match) bool {
	if arm.Kind() != "CaseClause" || len(arm.ChildrenIn("list")) != 0 {
		return false
	}
	body := arm.ChildrenIn("body")
	if len(body) != 1 || !body[0].Is(engine.Return) || !body[0].Child("results").IsAbsence() {
		return false
	}
	subject := arm.Parent().Parent().Child("tag").Typed()
	if subject == nil || subject.Kind != "named" {
		return false
	}
	cases := 0
	for _, sibling := range arm.Parent().ChildrenIn("list") {
		for _, value := range sibling.ChildrenIn("list") {
			typed := value.Typed()
			if typed == nil || typed.Name != subject.Name || value.Node().Facts == nil || !value.Node().Incomplete {
				return false
			}
			cases++
		}
	}
	return cases >= 2
}

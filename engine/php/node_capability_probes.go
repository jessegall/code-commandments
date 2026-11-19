package php

import "github.com/jessegall/code-commandments/engine"

const leastCapabilityProbes = 3

func (n Node) ProbesOneFieldForCapabilities() bool {
	if n.Kind() != "Stmt_Class" {
		return false
	}
	probes := map[string]int{}
	for _, method := range Methods(n.Match) {
		if field := capabilityProbedField(method); field != "" {
			probes[field]++
		}
	}
	for _, count := range probes {
		if count >= leastCapabilityProbes {
			return true
		}
	}

	return false
}

func capabilityProbedField(method engine.Match) string {
	body := method.ChildrenIn("stmts")
	switch {
	case len(body) == 1 && body[0].Kind() == "Stmt_Return" && body[0].Child("expr").Kind() == "Expr_Ternary":
		ternary := body[0].Child("expr")

		return probedField(ternary.Child("cond"), ternary.Child("if"), ternary.Child("else"))
	case len(body) == 2 && body[0].Kind() == "Stmt_If" && body[1].Kind() == "Stmt_Return":
		branch := body[0].ChildrenIn("stmts")
		if len(branch) != 1 || branch[0].Kind() != "Stmt_Return" || body[0].Child("else").Exists() || len(body[0].ChildrenIn("elseifs")) > 0 {
			return ""
		}

		return probedField(body[0].Child("cond"), branch[0].Child("expr"), body[1].Child("expr"))
	}

	return ""
}

func probedField(condition, answer, fallback engine.Match) string {
	if condition.Kind() != "Expr_Instanceof" || !isBareValue(fallback) && fallback.Kind() != "Expr_Array" {
		return ""
	}
	field := memberOnThis(condition.Child("expr"))
	if field == "" || answer.Kind() != "Expr_MethodCall" || memberOnThis(answer.Child("var")) != field {
		return ""
	}

	return field
}

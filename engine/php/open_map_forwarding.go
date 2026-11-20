package php

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

func (n Node) IsOpenMapForwarding() bool {
	parameter := n.Child("var")
	forwarded := false
	for _, use := range Trace(parameter) {
		if use.Node == parameter {
			continue
		}
		if use.Kind != Argument || !forwardsMapArgument(use.Node, n.EnclosingFunction()) {
			return false
		}
		forwarded = true
	}
	return forwarded
}

func forwardsMapArgument(value, method engine.Match) bool {
	call := ArgumentConsumer(value)
	if !call.Exists() {
		return false
	}
	if call.Parent().Kind() == "Stmt_Return" && call.Child("var").Kind() == "Expr_PropertyFetch" &&
		strings.EqualFold(CallName(call), method.Name()) {
		return true
	}
	argument := value.Parent()
	if CallsGlobalFunction(call, "array_merge") {
		argument = call.Parent()
		if argument.Kind() != "Arg" {
			return false
		}
	}
	return acceptsOpenMap(ResolvedParameterFilled(argument))
}

func acceptsOpenMap(parameter engine.Match) bool {
	if !parameter.Exists() {
		return false
	}
	if Written(parameter.Node().Declared).Render() != "array|string" {
		return false
	}
	name := parameter.Child("var").Name()
	for _, loop := range parameter.DeclaringFunction().OwnDescendants() {
		if loop.Kind() == "Stmt_Foreach" && loop.Child("expr").Kind() == "Expr_Variable" &&
			loop.Child("expr").Name() == name && loop.Child("keyVar").Kind() == "Expr_Variable" {
			return true
		}
	}
	return false
}

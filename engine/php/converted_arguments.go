package php

import (
	"github.com/jessegall/code-commandments/engine"
	"slices"
	"strconv"
	"strings"
)

func callConversion(call engine.Match) engine.CallConversion {
	codebase := call.Codebase()
	owner, method := evidencedCallee(call, codebase)
	if owner == "" {
		return engine.CallConversion{}
	}
	evidence := engine.CallConversion{Key: conversionFingerprint(call, codebase, owner, method)}
	for position := range Arguments(call) {
		evidence.Slots = append(evidence.Slots, owner+"::"+method+"#"+strconv.Itoa(position))
	}
	return evidence
}

func evidencedCallee(call engine.Match, codebase *engine.Codebase) (owner, method string) {
	owner, method = TypesOf(codebase).Callee(call)
	if declaration, _ := ProgramOf(codebase).Declaration(owner); call.IsTestReaching(declaration) {
		return "", ""
	}

	return owner, method
}

func conversionFingerprint(call engine.Match, codebase *engine.Codebase, owner, method string) string {
	types := TypesOf(codebase)
	for position, argument := range Arguments(call) {
		wrapper := conversionIn(argument, call)
		declared := types.ParamTypeOf(owner, method, position)
		if wrapper != "" && declared != "" && !IsClassName(declared) {
			return owner + "::" + method + "#" + strconv.Itoa(position) + "=" + wrapper
		}
	}

	return ""
}

func conversionIn(argument, call engine.Match) string {
	value := argument.Child("value")
	if slices.Contains(argument.Node().Flags, "spread") || argument.Child("name").Exists() || value.Kind() != "Expr_StaticCall" {
		return ""
	}
	class, name := value.Child("class"), value.Child("name")
	if !strings.HasPrefix(class.Kind(), "Name") || name.Kind() != "Identifier" {
		return ""
	}
	passed := 0
	for _, child := range value.Children() {
		if child.Node().Field == "args" {
			passed++
		}
	}
	if lowered := strings.ToLower(class.Name()); passed != 1 || lowered == "self" || lowered == "static" || lowered == "parent" {
		return ""
	}
	if class.Name() == EnclosingClassName(call) {
		return ""
	}

	return class.Name() + "::" + name.Name()
}

package php

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

func publishedContracts(call engine.Match, registrars []string) []string {
	method := CallName(call)
	if method == "" || !slices.ContainsFunc(registrars, func(registrar string) bool {
		return strings.HasSuffix(registrar, "::"+method)
	}) {
		return nil
	}
	owner := TypesOf(call.Codebase()).TypeOf(call.Child("var"))
	if call.Kind() == "Expr_StaticCall" {
		owner = StaticCallClass(call)
	}
	if !slices.Contains(registrars, owner+"::"+method) {
		return nil
	}
	var published []string
	for _, argument := range Arguments(call) {
		if name := ClassLiteral(argument.Child("value")); name != "" {
			published = append(published, name)
		}
	}

	return published
}

func ClassLiteral(fetch engine.Match) string {
	class, name := fetch.Child("class"), fetch.Child("name")
	if fetch.Kind() != "Expr_ClassConstFetch" || !isName(class) || name.Kind() != "Identifier" || name.Name() != "class" {
		return ""
	}

	return strings.TrimLeft(class.Name(), `\`)
}

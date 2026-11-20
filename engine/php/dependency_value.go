package php

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

func HasDataInjection(declaration engine.Match) bool {
	return HasAttribute(declaration, "FromContainer", "FromContainerProperty")
}

func isValueDependency(class engine.Match) bool {
	program := ProgramOf(class.Codebase())
	name := class.Identity()
	if program.IsA(name, `Illuminate\Database\Eloquent\Model`) ||
		program.IsA(name, `Spatie\EventSourcing\AggregateRoots\AggregateRoot`) {
		return true
	}

	return (program.ClassIsValueType(name) || isPublicPayload(class)) && !hasServiceWork(program, class, map[string]bool{})
}

func isPublicPayload(class engine.Match) bool {
	return len(Methods(class)) == 1 && (Node{Match: class}).ConstructorIsPromotionOnly() &&
		!slices.ContainsFunc(Fields(class), func(field Field) bool { return !field.IsPublic })
}

func hasServiceWork(program *Program, class engine.Match, seen map[string]bool) bool {
	name := class.Identity()
	if seen[name] || class.IsInDependency() {
		return false
	}
	seen[name] = true
	if keepsContainerLifetime(class) || receivesContainerConfig(class) {
		return true
	}
	stateless := len(Fields(class)) == 0
	for _, owner := range append([]string{name}, program.Ancestors(name)...) {
		declaration, found := program.Class(owner)
		if !found {
			continue
		}
		if slices.ContainsFunc(Methods(declaration), func(method engine.Match) bool {
			return returnsQuery(program, method) || doesServiceWork(method, stateless)
		}) {
			return true
		}
		for _, field := range Fields(declaration) {
			if dependency, found := program.Class(Written(field.Type).Class()); found && hasServiceWork(program, dependency, seen) {
				return true
			}
		}
	}

	return false
}

func keepsContainerLifetime(class engine.Match) bool {
	return slices.ContainsFunc(AttributeNames(class), func(name string) bool {
		return slices.Contains([]string{`Illuminate\Container\Attributes\Scoped`, `Illuminate\Container\Attributes\Singleton`}, strings.TrimLeft(name, `\`))
	})
}

func receivesContainerConfig(class engine.Match) bool {
	return slices.ContainsFunc(ConstructorParams(class), func(parameter engine.Match) bool {
		return HasDataInjection(parameter) || slices.Contains(AttributeNames(parameter), `Illuminate\Container\Attributes\Config`)
	})
}

func returnsQuery(program *Program, method engine.Match) bool {
	if method.HasModifier("static") {
		return false
	}
	name := Written(method.Node().Returns).Class()

	return program.IsA(name, `Illuminate\Database\Eloquent\Builder`) || program.IsA(name, `Illuminate\Database\Query\Builder`)
}

func doesServiceWork(method engine.Match, stateless bool) bool {
	if method.HasModifier("static") || method.Name() == "__construct" {
		return false
	}
	return slices.ContainsFunc(method.OwnDescendants(), func(node engine.Match) bool {
		switch node.Kind() {
		case "Expr_MethodCall", "Expr_NullsafeMethodCall":
			receiver := node.Child("var")
			return stateless && (receiver.Kind() != "Expr_Variable" || receiver.Name() != "this")
		case "Expr_StaticCall":
			return stateless && !slices.Contains([]string{"self", "static", "parent", EnclosingClassName(method)}, node.Child("class").Name())
		case "Expr_FuncCall":
			return slices.Contains([]string{"fopen", "opendir", "scandir", "glob", "file_get_contents", "file_put_contents", "mkdir", "rmdir", "unlink", "rename"}, node.Child("name").Name())
		}

		return false
	})
}

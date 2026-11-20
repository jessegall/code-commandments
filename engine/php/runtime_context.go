package php

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

var contextArguments = engine.Memoised(func(codebase *engine.Codebase) map[string][]string {
	contexts := map[string][]string{}
	program, types := ProgramOf(codebase), TypesOf(codebase)
	for _, call := range In(codebase).WhereKind("Expr_MethodCall").Reject(engine.Match.IsTest).Get() {
		if method := CallName(call); method != "make" && method != "makeWith" || !IsContainerCall(call) {
			continue
		}
		node := Node{Match: call}
		chosen, arguments := node.Argument(0), node.Argument(1)
		if arguments.Kind() != "Expr_Array" || chosen.Kind() == "Expr_ClassConstFetch" || chosen.Kind() == "Scalar_String" {
			continue
		}
		for _, item := range arguments.ChildrenIn("items") {
			key := item.Child("key")
			if key.Kind() != "Scalar_String" {
				continue
			}
			name, _ := key.Text()
			if supplied := types.TypeOf(item.Child("value")); isContextType(program, supplied) && !slices.Contains(contexts[name], supplied) {
				contexts[name] = append(contexts[name], supplied)
			}
		}
	}

	return contexts
})

func isContextType(program *Program, name string) bool {
	declaration, found := program.Declaration(name)
	if !found {
		return false
	}
	if declaration.Kind() == "Stmt_Interface" {
		return contextMarker(program, declaration)
	}

	return declaration.HasModifier("abstract") && slices.ContainsFunc(Fields(declaration), func(field Field) bool {
		marker, _ := program.Declaration(Written(field.Type).Class())
		return field.IsPublic && contextMarker(program, marker)
	})
}

func contextMarker(program *Program, declaration engine.Match) bool {
	return declaration.Kind() == "Stmt_Interface" && len(Methods(declaration)) == 0 &&
		len(program.Contracts(declaration.Identity())) == 0
}

func receivesContext(parameter, class engine.Match) bool {
	if readsScopedFields(parameter, class) {
		return true
	}
	codebase := parameter.Codebase()
	contracts := contextArguments.Of(codebase)[parameter.Name()]
	program := ProgramOf(codebase)

	return slices.ContainsFunc(contracts, func(contract string) bool { return program.IsA(class.Identity(), contract) })
}

func readsScopedFields(parameter, class engine.Match) bool {
	if !parameter.HasFlag("promoted") ||
		!(parameter.HasModifier("private") || HasDataInjection(parameter) && HasAttribute(parameter, "Hidden")) ||
		!slices.Contains(AttributeNames(class), `Illuminate\Container\Attributes\Scoped`) {
		return false
	}
	fields := Fields(class)
	owner := EnclosingClass(parameter)
	found := false
	for _, use := range owner.Descendants() {
		if selfPropertyOf(use) != parameter.Name() || EnclosingClass(use).Node() != owner.Node() {
			continue
		}
		property := use.Parent()
		if !isPropertyRead(property) {
			return false
		}
		if property.Child("var").Node() != use.Node() || !slices.ContainsFunc(fields, func(field Field) bool {
			return field.Name == property.Child("name").Name() && field.IsPublic && isReadOnlyScopeField(field)
		}) {
			return false
		}
		found = true
	}

	return found
}

func isReadOnlyScopeField(field Field) bool {
	if field.Declaration.HasModifier("readonly") || field.Declaration.HasModifier("private(set)") {
		return true
	}
	hooks := field.Declaration.ChildrenIn("hooks")
	if len(hooks) != 1 || hooks[0].Name() != "get" || hooks[0].HasFlag("by-ref") {
		return false
	}
	return !slices.ContainsFunc(hooks[0].Descendants(), func(node engine.Match) bool {
		return selfPropertyOf(node) == field.Name
	})
}

package spatie

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/php"
	"github.com/jessegall/code-commandments/engine/php/laravel"
)

func (n Node) IsWireArrayRead() bool {
	method := n.EnclosingFunction()
	if !method.HasModifier("private") {
		return false
	}
	name := (php.Node{Match: n.Match}).ArrayBaseName()
	position := php.ParameterPosition(method, name)
	if position < 0 {
		return false
	}
	callers := php.IndexOf(n.Codebase()).ResolvedCallersOfMethod(method)
	if len(callers) == 0 {
		return false
	}
	if n.Parent().Kind() == "Expr_Isset" && php.Written(method.Node().Written).SimpleName() == "bool" {
		return !slices.ContainsFunc(callers, func(call engine.Match) bool {
			return !choosesDecoder(call, position)
		})
	}
	return returnsInputArray(method, name) && !slices.ContainsFunc(callers, func(call engine.Match) bool {
		return !usesWireArray(call, position)
	})
}

func returnsInputArray(method engine.Match, name string) bool {
	returned := false
	for _, statement := range method.OwnDescendants() {
		if statement.Kind() != "Stmt_Return" {
			continue
		}
		value := statement.Child("expr")
		if value.Kind() != "Expr_Variable" || value.Name() != name {
			return false
		}
		returned = true
	}
	return returned
}

func usesWireArray(call engine.Match, position int) bool {
	args := php.Arguments(call)
	if position >= len(args) {
		return false
	}
	if dataDecoder(php.ArgumentConsumer(call), call) != "" {
		return true
	}
	input := args[position].Child("value")
	if input.Kind() != "Expr_Variable" {
		return readsRequest(input)
	}
	return readsRequest(php.LocalValueAt(input))
}

func readsRequest(call engine.Match) bool {
	return call.Exists() && php.CallName(call) == "input" && php.ProgramOf(call.Codebase()).IsA(laravel.CallReceiverClass(call), laravel.Request)
}

func choosesDecoder(call engine.Match, position int) bool {
	branch := call.Parent()
	args := php.Arguments(call)
	if position >= len(args) || branch.Kind() != "Stmt_If" || branch.Child("cond").Node() != call.Node() {
		return false
	}
	selected, otherwise := branch.ChildrenIn("stmts"), branch.Child("else").ChildrenIn("stmts")
	if len(otherwise) == 0 {
		statements := branch.Parent().ChildrenIn("stmts")
		index := slices.IndexFunc(statements, func(statement engine.Match) bool { return statement.Node() == branch.Node() })
		if index < 0 || index+1 >= len(statements) {
			return false
		}
		otherwise = statements[index+1 : index+2]
	}
	input := args[position].Child("value")
	first, second := returnedDecoder(selected, input), returnedDecoder(otherwise, input)
	return first != "" && second != "" && first != second
}

func returnedDecoder(statements []engine.Match, input engine.Match) string {
	if len(statements) != 1 || statements[0].Kind() != "Stmt_Return" {
		return ""
	}
	return dataDecoder(statements[0].Child("expr"), input)
}

func dataDecoder(call, input engine.Match) string {
	if !call.Exists() || !strings.EqualFold(php.CallName(call), "from") {
		return ""
	}
	class := php.StaticCallClass(call)
	if !php.ProgramOf(call.Codebase()).Extends(class, Data) {
		return ""
	}
	if args := php.Arguments(call); len(args) == 1 && args[0].Child("value").SameSyntax(input) {
		return class
	}
	return ""
}

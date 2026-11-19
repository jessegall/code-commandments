package php

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

func (n Node) IsUnusedParameter() bool {
	if n.Kind() != "Param" || n.HasFlag("promoted") || n.HasFlag("by-ref") {
		return false
	}
	routine := Node{Match: n.Parent()}
	if !routine.cannotBeOverridden() {
		return false
	}
	read := routine.variablesRead()
	name := n.Child("var").Name()
	readsAnother := slices.ContainsFunc(Params(routine.Match), func(parameter engine.Match) bool { return read[parameter.Child("var").Name()] })
	if name == "" || read[name] || read[""] || !readsAnother {
		return false
	}

	return !routine.isPassedByName()
}

func (n Node) cannotBeOverridden() bool {
	if n.Kind() != "Stmt_ClassMethod" || len(n.ChildrenIn("stmts")) == 0 || n.IsMagicMethod() {
		return false
	}
	owner := Node{Match: n.Closest(engine.TypeDeclaration)}
	contracted := owner.Child("extends").Exists() || len(owner.ChildrenIn("implements")) > 0 || owner.Kind() == "Stmt_Trait"
	sealed := n.HasModifier("final") || owner.HasModifier("final") || owner.Kind() == "Stmt_Enum"

	return n.HasModifier("private") || sealed && !contracted
}

func (n Node) variablesRead() map[string]bool {
	read := map[string]bool{}
	for _, statement := range n.ChildrenIn("stmts") {
		for _, node := range withDescendants(statement) {
			if node.Kind() == "Expr_Variable" {
				read[node.Name()] = true
			}
			if readsEveryVariable(node) {
				read[""] = true
			}
		}
	}

	return read
}

func (n Node) isPassedByName() bool {
	return slices.ContainsFunc(descendantsOf(n.Closest(engine.TypeDeclaration)), func(node engine.Match) bool {
		return namesInAString(node, n.Name()) || callsWithoutArguments(node, n.Name())
	})
}

func readsEveryVariable(node engine.Match) bool {
	return node.Kind() == "Expr_FuncCall" && slices.Contains([]string{"func_get_args", "get_defined_vars", "compact"}, strings.ToLower(strings.TrimLeft(node.Child("name").Name(), `\`)))
}

func callsWithoutArguments(node engine.Match, method string) bool {
	return callsTheMethod(node, method) && slices.ContainsFunc(node.ChildrenIn("args"), func(argument engine.Match) bool {
		return argument.Kind() == "VariadicPlaceholder"
	})
}

func (n Node) ReassignsAParameter() bool {
	if !n.Is(engine.Assignment) {
		return false
	}
	target := n.Child("var")
	if target.Kind() != "Expr_Variable" || target.Name() == "" {
		return false
	}

	return slices.ContainsFunc(Params(enclosingFunction(n.Match)), func(parameter engine.Match) bool {
		return parameter.Child("var").Name() == target.Name() && !parameter.HasFlag("by-ref")
	})
}

func (n Node) IsUnreachable() bool {
	if n.Node() == nil || n.Node().Field != "stmts" || slices.Contains([]string{"Stmt_Nop", "Stmt_Label", "Stmt_Function", "Stmt_Class", "Stmt_Interface", "Stmt_Trait", "Stmt_Enum"}, n.Kind()) {
		return false
	}
	siblings := n.Parent().ChildrenIn("stmts")
	at := slices.IndexFunc(siblings, func(sibling engine.Match) bool { return sibling.Node() == n.Node() })
	if at < 1 || slices.ContainsFunc(siblings, func(sibling engine.Match) bool { return sibling.Kind() == "Stmt_Label" }) {
		return false
	}

	return (Node{Match: siblings[at-1]}).IsBailOut() && !(Node{Match: siblings[at-1]}).IsUnreachable()
}

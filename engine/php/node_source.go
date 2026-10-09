package php

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// ConstructorHasSideEffect says whether the class's constructor sends a method to a collaborator — a parameter or an
// own property — and throws the answer away: work done on construction.
func (n Node) ConstructorHasSideEffect() bool {
	var constructor engine.Match
	for _, method := range Methods(n.EnclosingClassLike().Match) {
		if strings.EqualFold(method.Name(), "__construct") {
			constructor = method
		}
	}
	if !constructor.Exists() {
		return false
	}
	parameters := map[string]bool{}
	for _, param := range Params(constructor) {
		if name := variableName(param.Child("var")); name != "" {
			parameters[name] = true
		}
	}
	for _, call := range withDescendants(constructor) {
		if isMethodSend(call) && asksACollaborator(call, parameters) && (Node{Match: call}).ResultIsDiscarded() {
			return true
		}
	}

	return false
}

func asksACollaborator(call engine.Match, parameters map[string]bool) bool {
	receiver := call.Child("var")
	for isMethodSend(receiver) {
		receiver = receiver.Child("var")
	}
	if name := variableName(receiver); name != "" {
		return parameters[name]
	}

	return receiver.Kind() == "Expr_PropertyFetch" && variableName(receiver.Child("var")) == "this"
}

// IsStaticStateWrite says whether the node writes a static property, an offset of one included, other than to fill a
// static its function first tests for presence: a memo.
func (n Node) IsStaticStateWrite() bool {
	switch n.Kind() {
	case "Expr_PostInc", "Expr_PreInc", "Expr_PostDec", "Expr_PreDec":
		return n.Child("var").Kind() == "Expr_StaticPropertyFetch"
	case "Expr_AssignOp_Coalesce":
		return false
	}
	if n.Kind() != "Expr_Assign" && !strings.HasPrefix(n.Kind(), "Expr_AssignOp_") {
		return false
	}
	target := n.Child("var")
	for target.Kind() == "Expr_ArrayDimFetch" {
		target = target.Child("var")
	}

	return target.Kind() == "Expr_StaticPropertyFetch" && !n.memoisesThatStatic(target)
}

func (n Node) memoisesThatStatic(target engine.Match) bool {
	function := n.EnclosingFunctionLike()
	name := target.Child("name")
	if !function.Exists() || name.Kind() != "VarLikeIdentifier" && name.Kind() != "Identifier" {
		return false
	}
	for _, test := range withDescendants(function.Match) {
		if !isPresenceTest(test) {
			continue
		}
		for _, read := range withDescendants(test) {
			if read.Kind() == "Expr_StaticPropertyFetch" && read.Child("name").Name() == name.Name() && read.Child("name").Kind() == name.Kind() {
				return true
			}
		}
	}

	return false
}

func isPresenceTest(node engine.Match) bool {
	switch node.Kind() {
	case "Expr_Isset", "Expr_Empty":
		return true
	case "Expr_FuncCall":
		return isName(node.Child("name")) && strings.EqualFold(node.Child("name").Name(), "array_key_exists")
	case "Expr_BinaryOp_Identical", "Expr_BinaryOp_NotIdentical":
		return IsNullConstant(node.Child("left")) || IsNullConstant(node.Child("right"))
	}

	return false
}

// IsInStaticMethod says whether the method or function around the node is a static method.
func (n Node) IsInStaticMethod() bool {
	for at := n; at.Exists(); at = at.Up() {
		if at.Kind() == "Stmt_ClassMethod" {
			return slices.Contains(at.Node().Modifiers, "static")
		}
		if at.Kind() == "Stmt_Function" {
			return false
		}
	}

	return false
}

// BodyNodeCount is how many nodes a function's body holds.
func (n Node) BodyNodeCount() int {
	if !n.IsFunctionDeclaration() {
		return 0
	}
	count := 0
	for _, statement := range n.ChildrenIn("stmts") {
		count += len(withDescendants(statement))
	}

	return count
}

// BodyHash is the fingerprint of a function's body, statement by statement; empty for anything else, and for a
// body with nothing in it.
func (n Node) BodyHash() string {
	body := n.ChildrenIn("stmts")
	if !n.IsFunctionDeclaration() || len(body) == 0 {
		return ""
	}

	return engine.SyntaxHash(body, Hashing, false)
}

// IsGuardedAccessor says whether the node declares a method whose body is throwing guards and then a return of a
// property or a variable.
func (n Node) IsGuardedAccessor() bool {
	body := n.ChildrenIn("stmts")
	if n.Kind() != "Stmt_ClassMethod" || len(body) < 2 {
		return false
	}
	last := body[len(body)-1]
	if returned := last.Child("expr").Kind(); last.Kind() != "Stmt_Return" || returned != "Expr_PropertyFetch" && returned != "Expr_Variable" {
		return false
	}

	return !slices.ContainsFunc(body[:len(body)-1], func(guard engine.Match) bool { return !isThrowingGuard(guard) })
}

func isThrowingGuard(statement engine.Match) bool {
	guard := Node{Match: statement}
	inner := guard.ChildrenIn("stmts")
	if statement.Kind() != "Stmt_If" || guard.Child("else").Exists() || len(guard.ChildrenIn("elseifs")) > 0 || len(inner) == 0 {
		return false
	}

	return !slices.ContainsFunc(inner, func(each engine.Match) bool {
		return each.Kind() != "Stmt_Expression" || each.Child("expr").Kind() != "Expr_Throw"
	})
}

// IsParentForwardingConstructor says whether the node is a constructor whose whole body hands its own parameters,
// as they came, to the parent's constructor: a subclass that declares one only to promote or narrow its own
// parameters, the shared initialisation already written once in the parent.
func (n Node) IsParentForwardingConstructor() bool {
	body := n.ChildrenIn("stmts")
	if n.Kind() != "Stmt_ClassMethod" || !strings.EqualFold(n.Name(), "__construct") || len(body) != 1 {
		return false
	}
	call := body[0].Child("expr")
	class := call.Child("class")
	if body[0].Kind() != "Stmt_Expression" || call.Kind() != "Expr_StaticCall" || !isName(class) || !strings.EqualFold(class.Name(), "parent") || !strings.EqualFold(call.Child("name").Name(), "__construct") {
		return false
	}
	var parameters []string
	for _, parameter := range n.ChildrenIn("params") {
		parameters = append(parameters, parameter.Child("var").Name())
	}

	return !slices.ContainsFunc(Arguments(call), func(argument engine.Match) bool {
		return !slices.Contains(parameters, variableName(argument.Child("value")))
	})
}

// IsSelfSeedingFactory says whether the node declares a static method that makes a new self or static, sets fields
// on it, and returns it.
func (n Node) IsSelfSeedingFactory() bool {
	body := n.ChildrenIn("stmts")
	if n.Kind() != "Stmt_ClassMethod" || !slices.Contains(n.Node().Modifiers, "static") || len(body) < 3 {
		return false
	}
	first, last := body[0], body[len(body)-1]
	assign := first.Child("expr")
	built := assign.Child("expr")
	if first.Kind() != "Stmt_Expression" || assign.Kind() != "Expr_Assign" || built.Kind() != "Expr_New" {
		return false
	}
	seed, class := variableName(assign.Child("var")), built.Child("class")
	if seed == "" || !isName(class) || !strings.EqualFold(class.Name(), "self") && !strings.EqualFold(class.Name(), "static") {
		return false
	}
	if last.Kind() != "Stmt_Return" || variableName(last.Child("expr")) != seed {
		return false
	}

	return !slices.ContainsFunc(body[1:len(body)-1], func(statement engine.Match) bool {
		set := statement.Child("expr")
		if statement.Kind() != "Stmt_Expression" || set.Kind() != "Expr_Assign" {
			return true
		}
		target := set.Child("var")
		for target.Kind() == "Expr_PropertyFetch" {
			target = target.Child("var")
		}

		return variableName(target) != seed
	})
}

// IsDeprecated says whether the node's doc comment marks it @deprecated.
func (n Node) IsDeprecated() bool {
	doc, ok := n.DocComment()

	return ok && strings.Contains(doc.Text, "@deprecated")
}

// ReturnsArrayLiteralOnly says whether the node declares a function whose only statement returns an array literal.
func (n Node) ReturnsArrayLiteralOnly() bool {
	body := n.ChildrenIn("stmts")

	return n.IsFunctionDeclaration() && len(body) == 1 && body[0].Kind() == "Stmt_Return" && body[0].Child("expr").Kind() == "Expr_Array"
}

// YieldsEntriesOnly says whether every statement of the function yields.
func (n Node) YieldsEntriesOnly() bool {
	body := n.ChildrenIn("stmts")

	return n.IsFunctionDeclaration() && len(body) > 0 && !slices.ContainsFunc(body, func(statement engine.Match) bool {
		return statement.Kind() != "Stmt_Expression" || statement.Child("expr").Kind() != "Expr_Yield"
	})
}

// IsSoleExpressionStatement says whether the function's only statement is an expression statement.
func (n Node) IsSoleExpressionStatement() bool {
	body := n.ChildrenIn("stmts")

	return n.IsFunctionDeclaration() && len(body) == 1 && body[0].Kind() == "Stmt_Expression"
}

// ShapeHash is the function's fingerprint with names and data blanked: the shape a near copy shares.
func (n Node) ShapeHash() string {
	if !n.IsFunctionDeclaration() {
		return ""
	}

	return NormalizedHash(n.Match)
}

// LineCount is how many lines the node spans.
func (n Node) LineCount() int {
	span, err := n.Span()
	if err != nil {
		return 0
	}

	return strings.Count(span.Text(), "\n") + 1
}

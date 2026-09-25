package php

import (
	"github.com/jessegall/code-commandments/contract"
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// flattenConjuncts is every operand of a chain of &&, in order.
func flattenConjuncts(node engine.Match) []engine.Match {
	if node.Kind() == "Expr_BinaryOp_BooleanAnd" {
		return append(flattenConjuncts(node.Child("left")), flattenConjuncts(node.Child("right"))...)
	}

	return []engine.Match{node}
}

// isOutermostAnd says whether the node is a && that no && holds.
func (n Node) isOutermostAnd() bool {
	return n.Kind() == "Expr_BinaryOp_BooleanAnd" && n.Parent().Kind() != "Expr_BinaryOp_BooleanAnd"
}

// localAliases is every local its function assigns exactly once, and what it is assigned.
func (n Node) localAliases() map[string]engine.Match {
	function := n.EnclosingFunctionLike()
	if !function.Exists() {
		return nil
	}
	counts, assigned := map[string]int{}, map[string]engine.Match{}
	for _, assign := range withDescendants(function.Match) {
		if name := variableName(assign.Child("var")); assign.Kind() == "Expr_Assign" && name != "" {
			counts[name]++
			assigned[name] = assign.Child("expr")
		}
	}
	for name, count := range counts {
		if count != 1 {
			delete(assigned, name)
		}
	}

	return assigned
}

// reachCount is how many property reads, method calls, static calls and instanceof tests sit in the node.
func reachCount(node engine.Match) int {
	count := 0
	for _, each := range withDescendants(node) {
		switch each.Kind() {
		case "Expr_PropertyFetch", "Expr_MethodCall", "Expr_StaticCall", "Expr_Instanceof":
			count++
		}
	}

	return count
}

// IsSubstantiveGuard says whether the node is an outermost && of two or more conjuncts, not all instanceof, that
// between them reach into two or more things, a local assigned once read as what it holds.
func (n Node) IsSubstantiveGuard() bool {
	if !n.isOutermostAnd() {
		return false
	}
	conjuncts := flattenConjuncts(n.Match)
	if len(conjuncts) < 2 || !slices.ContainsFunc(conjuncts, func(c engine.Match) bool { return c.Kind() != "Expr_Instanceof" }) {
		return false
	}
	aliases := n.localAliases()
	substance := 0
	for _, conjunct := range conjuncts {
		target := conjunct
		if alias, ok := aliases[variableName(conjunct)]; ok {
			target = alias
		}
		substance += reachCount(target)
	}

	return substance >= 2
}

// IsTypeNarrowingGuard says whether the node is an outermost && with two or more instanceof conjuncts.
func (n Node) IsTypeNarrowingGuard() bool {
	if !n.isOutermostAnd() {
		return false
	}
	count := 0
	for _, conjunct := range flattenConjuncts(n.Match) {
		if conjunct.Kind() == "Expr_Instanceof" {
			count++
		}
	}

	return count >= 2
}

// CanonicalGuardHash is the fingerprint of a && guard's conjuncts, in any order, locals assigned once read as
// their expressions.
func (n Node) CanonicalGuardHash() string {
	if n.Kind() != "Expr_BinaryOp_BooleanAnd" {
		return ""
	}
	aliases := n.localAliases()
	var hashes []string
	for _, conjunct := range flattenConjuncts(n.Match) {
		hashes = append(hashes, CanonicalHash(conjunct, aliases))
	}
	slices.Sort(hashes)

	return strings.Join(hashes, "|")
}

// branchPointOf is the branch the test decides, up to its function; no node when it decides none.
func branchPointOf(test engine.Match) engine.Match {
	child, parent := test, test.Parent()
	for parent.Exists() && !(Node{Match: parent}).IsFunctionLike() {
		if isConditionOf(parent, child) {
			return parent
		}
		child, parent = parent, parent.Parent()
	}

	return engine.Match{}
}

// typeTests is every instanceof in the node's function that decides a branch, testing the same subject as the node.
func (n Node) typeTests() []engine.Match {
	function := n.EnclosingFunctionLike()
	if n.Kind() != "Expr_Instanceof" || !function.Exists() {
		return nil
	}
	subject := StructuralHash(n.Child("expr"))
	var tests []engine.Match
	for _, test := range withDescendants(function.Match) {
		if test.Kind() == "Expr_Instanceof" && branchPointOf(test).Exists() && StructuralHash(test.Child("expr")) == subject {
			tests = append(tests, test)
		}
	}

	return tests
}

// IsTypeSwitchHead says whether the node is the first instanceof of a function's chain of branches testing one
// subject against two or more classes.
func (n Node) IsTypeSwitchHead() bool {
	function := n.EnclosingFunctionLike()
	own := branchPointOf(n.Match)
	if n.Kind() != "Expr_Instanceof" || !function.Exists() || !own.Exists() {
		return false
	}
	subject := StructuralHash(n.Child("expr"))
	start := n.Node().Span.Start
	branches, classes := map[*contract.Node]bool{}, map[string]bool{}
	for _, test := range withDescendants(function.Match) {
		point := branchPointOf(test)
		if test.Kind() != "Expr_Instanceof" || !point.Exists() {
			continue
		}
		if point.Node() == own.Node() && test.Node().Span.Start < start {
			return false
		}
		if StructuralHash(test.Child("expr")) != subject {
			continue
		}
		if test.Node().Span.Start < start {
			return false
		}
		branches[point.Node()] = true
		classes[classNameOf(test.Child("class"))] = true
	}

	return len(branches) >= 2 && len(classes) >= 2
}

// classNameOf is the class a name node names; empty for an expression.
func classNameOf(class engine.Match) string {
	if !isName(class) {
		return ""
	}

	return class.Name()
}

// TypeSwitchClasses is every class the node's chain of type tests tests its subject against.
func (n Node) TypeSwitchClasses() []string {
	var classes []string
	for _, test := range n.typeTests() {
		if class := classNameOf(test.Child("class")); !slices.Contains(classes, class) {
			classes = append(classes, class)
		}
	}

	return classes
}

// IsInFromSourceFactory says whether the node sits in a static method returning its own class.
func (n Node) IsInFromSourceFactory() bool {
	function := n.EnclosingFunctionLike()
	if function.Kind() != "Stmt_ClassMethod" || !slices.Contains(function.Node().Modifiers, "static") {
		return false
	}
	returns := Written(function.Node().Returns).Render()
	class := EnclosingClassName(n.Match)

	return returns == "self" || returns == "static" || class != "" && strings.TrimLeft(returns, `\`) == strings.TrimLeft(class, `\`)
}

// TypeSwitchTranslatesEveryArm says whether every arm of the node's type switch builds or converts from the subject:
// a translation between type systems, not behaviour that belongs on the types.
func (n Node) TypeSwitchTranslatesEveryArm() bool {
	subject := StructuralHash(n.Child("expr"))
	var arms []engine.Match
	for _, test := range n.typeTests() {
		if arm := branchPointOf(test); !slices.ContainsFunc(arms, func(seen engine.Match) bool { return seen.Node() == arm.Node() }) {
			arms = append(arms, arm)
		}
	}
	if len(arms) < 2 {
		return false
	}

	return !slices.ContainsFunc(arms, func(arm engine.Match) bool {
		result := armResultOf(arm)

		return !result.Exists() || !isTranslationOf(result, subject)
	})
}

func armResultOf(arm engine.Match) engine.Match {
	switch arm.Kind() {
	case "MatchArm":
		return arm.Child("body")
	case "Expr_Ternary":
		return arm.Child("if")
	case "Stmt_If", "Stmt_ElseIf":
		body := arm.ChildrenIn("stmts")
		if len(body) == 1 && body[0].Kind() == "Stmt_Return" {
			return body[0].Child("expr")
		}
	}

	return engine.Match{}
}

func isTranslationOf(result engine.Match, subject string) bool {
	switch result.Kind() {
	case "Expr_New", "Expr_StaticCall", "Expr_MethodCall":
	default:
		return false
	}

	return slices.ContainsFunc(Arguments(result), func(argument engine.Match) bool { return StructuralHash(argument.Child("value")) == subject })
}

// NamedArguments is every argument the call passes by name.
func (n Node) NamedArguments() []engine.Match {
	var named []engine.Match
	for _, argument := range Arguments(n.Match) {
		if argument.Child("name").Kind() == "Identifier" {
			named = append(named, argument)
		}
	}

	return named
}

// MethodCallName is the method a send names; empty for anything else.
func (n Node) MethodCallName() string {
	if name := n.Child("name"); isMethodSend(n.Match) && name.Kind() == "Identifier" {
		return name.Name()
	}

	return ""
}

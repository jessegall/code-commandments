package php

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

// InteractionKind is how one occurrence of a variable uses it.
type InteractionKind string

const (
	Assigned      InteractionKind = "assigned"
	Argument      InteractionKind = "argument"
	MethodCall    InteractionKind = "method-call"
	PropertyFetch InteractionKind = "property-fetch"
	PropertyWrite InteractionKind = "property-write"
	NullChecked   InteractionKind = "null-checked"
	Coalesced     InteractionKind = "coalesced"
	Nullsafe      InteractionKind = "nullsafe"
	Returned      InteractionKind = "returned"
	Read          InteractionKind = "read"
)

func (k InteractionKind) DeNulls() bool {
	return slices.Contains([]InteractionKind{NullChecked, Coalesced, Nullsafe}, k)
}

// Interaction is one occurrence of a variable and how it is used there.
type Interaction struct {
	Node engine.Match
	Kind InteractionKind
}

func (i Interaction) IsWrite() bool {
	return i.Kind == Assigned
}

func Trace(variable engine.Match) []Interaction {
	function := enclosingFunction(variable)
	if variable.Kind() != "Expr_Variable" || variable.Name() == "" || !function.Exists() {
		return nil
	}
	var journey []Interaction
	for _, occurrence := range descendantsOf(function) {
		if occurrence.Kind() == "Expr_Variable" && occurrence.Name() == variable.Name() {
			journey = append(journey, Interaction{Node: occurrence, Kind: interactionKind(occurrence)})
		}
	}

	return journey
}

func interactionKind(occurrence engine.Match) InteractionKind {
	parent := occurrence.Parent()
	switch {
	case isAssignmentTarget(occurrence):
		return Assigned
	case (parent.Kind() == "Expr_BinaryOp_Identical" || parent.Kind() == "Expr_BinaryOp_NotIdentical") && isDeNulled(occurrence):
		return NullChecked
	case isChild(occurrence, "Expr_BinaryOp_Coalesce", "left"):
		return Coalesced
	case isNullsafeReceiver(occurrence):
		return Nullsafe
	case parent.Kind() == "Stmt_Return":
		return Returned
	case isChild(occurrence, "Expr_MethodCall", "var") || isChild(occurrence, "Expr_NullsafeMethodCall", "var"):
		return MethodCall
	case isChild(occurrence, "Expr_PropertyFetch", "var") && isAssignmentTarget(parent):
		return PropertyWrite
	case isChild(occurrence, "Expr_PropertyFetch", "var"):
		return PropertyFetch
	case parent.Kind() == "Arg":
		return Argument
	}

	return Read
}

func isDeNulled(node engine.Match) bool {
	if isNullsafeReceiver(node) || isChild(node, "Expr_BinaryOp_Coalesce", "left") {
		return true
	}
	parent := node.Parent()
	if parent.Kind() != "Expr_BinaryOp_Identical" && parent.Kind() != "Expr_BinaryOp_NotIdentical" {
		return false
	}
	other := parent.Child("left")
	if node.Node().Field == "left" {
		other = parent.Child("right")
	}

	return other.Kind() == "Expr_ConstFetch" && IsNullConstant(other)
}

func isAssignmentTarget(node engine.Match) bool {
	return isChild(node, "Expr_Assign", "var")
}

func isNullsafeReceiver(node engine.Match) bool {
	return isChild(node, "Expr_NullsafeMethodCall", "var") || isChild(node, "Expr_NullsafePropertyFetch", "var")
}

func isChild(node engine.Match, kind, field string) bool {
	return node.Exists() && node.Parent().Kind() == kind && node.Node().Field == field
}

func ParameterPosition(function engine.Match, name string) int {
	for at, parameter := range Params(function) {
		if parameter.Child("var").Name() == name {
			return at
		}
	}

	return -1
}

func ArgumentIsReadAfterTheCall(call engine.Match, at int) bool {
	arguments := Arguments(call)
	if at < 0 || at >= len(arguments) {
		return false
	}
	handed := arguments[at].Child("value")
	if handed.Kind() != "Expr_Variable" {
		return false
	}
	for _, use := range Trace(handed) {
		if use.Node.Node().Span.Start <= handed.Node().Span.Start {
			continue
		}
		if slices.Contains([]InteractionKind{PropertyFetch, MethodCall, Returned}, use.Kind) && sharesABlock(use.Node, handed) {
			return true
		}
	}

	return false
}

func sharesABlock(one, other engine.Match) bool {
	mine, theirs := ownStatement(one), ownStatement(other)

	return mine.Exists() && theirs.Exists() && mine.Parent().Node() == theirs.Parent().Node() && mine.Node().Field == theirs.Node().Field
}

func ownStatement(node engine.Match) engine.Match {
	for held := node; held.Exists(); held = held.Parent() {
		if slices.Contains([]string{"stmts", "finally"}, held.Node().Field) {
			return held
		}
	}

	return engine.Match{}
}

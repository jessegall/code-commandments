package python

import "slices"

// Fallback is the value the expression falls back to when its subject is missing: `d` in `x or d`,
// `x if x else d`, `x if x is not None else d` and `m.get(k, d)`. None for any other expression, and for
// `c and a or b`, which is a conditional written the old way rather than a default.
func (n Node) Fallback() (Node, bool) {
	_, fallback, ok := n.defaulted()

	return fallback, ok
}

// FallbackSubject is what the expression reads before it falls back; no node for an `or` over more than two
// values, whose subject is the `or` of all but the last.
func (n Node) FallbackSubject() (Node, bool) {
	subject, _, ok := n.defaulted()

	return subject, ok
}

func (n Node) defaulted() (Node, Node, bool) {
	switch n.Kind() {
	case "BoolOp":
		values := n.ChildrenIn("values")
		if n.Node().Operator != "or" || (values[0].Kind() == "BoolOp" && values[0].Node().Operator == "and") {
			return Node{}, Node{}, false
		}
		if len(values) == 2 {
			return values[0], values[1], true
		}

		return Node{}, values[len(values)-1], true
	case "Call":
		callee, arguments := n.Callee(), n.Arguments()
		if callee.Kind() != "Attribute" || callee.Name() != "get" || len(arguments) != 2 || len(n.Keywords()) > 0 || slices.ContainsFunc(arguments, isStarred) {
			return Node{}, Node{}, false
		}

		return callee.Child("value"), arguments[1], true
	case "IfExp":
		test, subject := n.Child("test"), n.Child("body")
		operand, testsNone := test.NoneTestedOperand()
		isNotNone := testsNone && test.Node().Operator == "is not" && operand == test.Child("left") && operand.IsSame(subject)
		if test.IsSame(subject) || isNotNone {
			return subject, n.Child("orelse"), true
		}
	}

	return Node{}, Node{}, false
}

func isStarred(argument Node) bool {
	return argument.Kind() == "Starred"
}

// IsSame says whether the two expressions are the same node, or read the same way.
func (n Node) IsSame(other Node) bool {
	return n == other || (n.Exists() && other.Exists() && n.ExpressionHash() == other.ExpressionHash())
}

// IsEmptyCollection says whether the expression is an empty tuple, list, set or dict display.
func (n Node) IsEmptyCollection() bool {
	return slices.Contains([]string{"Tuple", "List", "Set", "Dict"}, n.Kind()) && len(n.Children()) == 0
}

// IsLoopSubject says whether the expression is what a for loop iterates.
func (n Node) IsLoopSubject() bool {
	parent := n.Parent()

	return (parent.Kind() == "For" || parent.Kind() == "AsyncFor") && parent.Child("iter") == n
}

// FallsBackToEmptyCollection says whether the expression falls back to an empty collection.
func (n Node) FallsBackToEmptyCollection() bool {
	fallback, ok := n.Fallback()

	return ok && fallback.IsEmptyCollection()
}

// FallbackReachesIntoParameter says whether what the expression falls back from is read off a parameter of its
// def, the instance's own aside: a default for an argument the caller could have given whole.
func (n Node) FallbackReachesIntoParameter() bool {
	subject, ok := n.FallbackSubject()
	function := n.EnclosingFunction()
	if !ok || !function.Exists() {
		return false
	}

	return slices.Contains(function.handedIn(), subject.RootName())
}

// handedIn is the names a def's caller hands it: its parameters, a bound method's instance aside.
func (n Node) handedIn() []string {
	names := parameterNames(n.Parameters())
	if n.IsMethod() && !n.IsStatic() && len(names) > 0 {
		return names[1:]
	}

	return names
}

// ResultIsDiscarded says whether the expression stands as a statement of its own, its value thrown away.
func (n Node) ResultIsDiscarded() bool {
	parent := n.Parent()

	return parent.Kind() == "Expr" && parent.Child("value") == n
}

// IsOutermostNestedConditional says whether the conditional expression holds another in a branch and sits in
// none itself.
func (n Node) IsOutermostNestedConditional() bool {
	if n.Kind() != "IfExp" {
		return false
	}
	var branches []Node
	for _, branch := range []Node{n.Child("body"), n.Child("orelse")} {
		branches = append(branches, append([]Node{branch}, branch.Descendants()...)...)
	}
	if !slices.ContainsFunc(branches, func(branch Node) bool { return branch.Kind() == "IfExp" }) {
		return false
	}
	for around := n.wrapper(); around.Exists(); around = around.wrapper() {
		if around.Kind() == "IfExp" {
			return false
		}
	}

	return true
}

// IsBranchingConstruct says whether the statement chooses or repeats: an if, a for, a while or a match.
func (n Node) IsBranchingConstruct() bool {
	return slices.Contains([]string{"If", "For", "AsyncFor", "While", "Match"}, n.Kind())
}

// IsElif says whether the if is written as the elif of the one before it.
func (n Node) IsElif() bool {
	return n.Kind() == "If" && slices.Contains(n.Node().Flags, "elif")
}

// BranchingDepth is how many choices the statement sits inside within its def: each if, for or while whose body
// or else holds it, an elif adding none beyond its if. A match's cases hold their bodies, so a match adds none.
func (n Node) BranchingDepth() int {
	depth := 0
	for child, parent := n, n.Parent(); parent.Exists() && !parent.IsDefinition(); child, parent = parent, parent.Parent() {
		field := child.Node().Field
		if slices.Contains([]string{"If", "For", "AsyncFor", "While"}, parent.Kind()) && (field == "body" || field == "orelse") && !child.IsElif() {
			depth++
		}
	}

	return depth
}

// IsSoleLoopBodyGuard says whether the if is the whole body of a loop, guarding two or more statements of work
// that end in no bail-out, with no else: the loop's real body wrapped in a condition.
func (n Node) IsSoleLoopBodyGuard() bool {
	body := n.ChildrenIn("body")
	if n.Kind() != "If" || len(n.ChildrenIn("orelse")) > 0 || len(body) < 2 || body[len(body)-1].IsBailOut() {
		return false
	}
	loop := n.Parent()

	return slices.Contains([]string{"For", "AsyncFor", "While"}, loop.Kind()) && n.Node().Field == "body" && len(loop.ChildrenIn("body")) == 1
}

// HasRedundantElse says whether the if has an else though its body always leaves: the else's statements can
// stand after the if, unindented.
func (n Node) HasRedundantElse() bool {
	orelse, body := n.ChildrenIn("orelse"), n.ChildrenIn("body")
	if n.Kind() != "If" || n.IsElif() || len(orelse) == 0 || (len(orelse) == 1 && orelse[0].IsElif()) {
		return false
	}

	return len(body) > 0 && body[len(body)-1].IsBailOut()
}

// SubjectLadderLength is how many rungs an if and its elifs have when every one compares one subject to a
// constant with `==`; zero when they are no such ladder, or the if is itself an elif.
func (n Node) SubjectLadderLength() int {
	if n.Kind() != "If" || n.IsElif() {
		return 0
	}
	chain := n.Chain()
	var subjects []string
	for _, rung := range chain {
		subject, ok := rung.Child("test").ComparisonSubject()
		if !ok {
			return 0
		}
		if hash := subject.ExpressionHash(); !slices.Contains(subjects, hash) {
			subjects = append(subjects, hash)
		}
	}
	if len(subjects) != 1 {
		return 0
	}

	return len(chain)
}

// Chain is the if and each elif after it.
func (n Node) Chain() []Node {
	chain := []Node{n}
	for orelse := n.ChildrenIn("orelse"); len(orelse) == 1 && orelse[0].IsElif(); orelse = orelse[0].ChildrenIn("orelse") {
		chain = append(chain, orelse[0])
	}

	return chain
}

// ComparisonSubject is what an `==` comparison tests against a literal: the side that is no literal, when the
// other one is.
func (n Node) ComparisonSubject() (Node, bool) {
	comparators := n.ChildrenIn("comparators")
	if n.Kind() != "Compare" || n.Node().Operator != "==" || len(comparators) != 1 {
		return Node{}, false
	}
	left, right := n.Child("left"), comparators[0]
	switch {
	case left.Kind() != "Constant" && right.Kind() == "Constant":
		return left, true
	case left.Kind() == "Constant" && right.Kind() != "Constant":
		return right, true
	}

	return Node{}, false
}

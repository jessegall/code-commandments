package python

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

func (n Node) Fallback() (Node, bool) {
	_, fallback, ok := n.defaulted()

	return fallback, ok
}

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

func (n Node) IsSame(other Node) bool {
	return n == other || (n.Exists() && other.Exists() && n.ExpressionHash() == other.ExpressionHash())
}

func (n Node) IsEmptyCollection() bool {
	return slices.Contains([]string{"Tuple", "List", "Set", "Dict"}, n.Kind()) && len(n.Children()) == 0
}

func (n Node) IsLoopSubject() bool {
	parent := n.Parent()

	return (parent.Kind() == "For" || parent.Kind() == "AsyncFor") && parent.Child("iter") == n
}

func (n Node) FallsBackToEmptyCollection() bool {
	fallback, ok := n.Fallback()

	return ok && fallback.IsEmptyCollection()
}

func (n Node) FallbackReachesIntoParameter() bool {
	subject, ok := n.FallbackSubject()
	function := n.EnclosingFunction()
	if !ok || !function.Exists() {
		return false
	}

	return slices.Contains(function.handedIn(), subject.RootName())
}

func (n Node) handedIn() []string {
	names := parameterNames(n.Parameters())
	if n.IsMethod() && !n.IsStatic() && len(names) > 0 {
		return names[1:]
	}

	return names
}

func (n Node) ResultIsDiscarded() bool {
	parent := n.Parent()

	return parent.Kind() == "Expr" && parent.Child("value") == n
}

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

func (n Node) IsBranchingConstruct() bool {
	return slices.Contains([]string{"If", "For", "AsyncFor", "While", "Match"}, n.Kind())
}

func (n Node) IsElif() bool {
	return n.Kind() == "If" && slices.Contains(n.Node().Flags, "elif")
}

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

var containerMutators = []string{"append", "extend", "insert", "remove", "pop", "clear", "add", "discard", "update", "setdefault", "popitem"}

func (n Node) ChangesTheCollectionItsLoopWalks() bool {
	changed, changes := n.collectionItChanges()
	if !changes || !changed.namesOneCollection() {
		return false
	}
	loop, walked := n.loopWalking(changed)
	if !walked {
		return false
	}

	return !n.leavesThatLoopAtOnce(loop)
}

func (n Node) collectionItChanges() (Node, bool) {
	if n.IsCall() && slices.Contains(containerMutators, n.Callee().Name()) {
		return n.Callee().Child("value"), true
	}
	if n.Kind() == "Delete" && len(n.ChildrenIn("targets")) == 1 {
		return n.ChildrenIn("targets")[0].subscripted()
	}

	return Node{}, false
}

func (n Node) subscripted() (Node, bool) {
	if n.Kind() != "Subscript" {
		return Node{}, false
	}

	return n.Child("value"), true
}

func (n Node) namesOneCollection() bool {
	return slices.Contains([]string{"Name", "Attribute"}, n.Kind())
}

var collectionViews, mappingViews = []string{"enumerate", "reversed", "iter"}, []string{"items", "keys", "values"}

func (n Node) loopWalking(collection Node) (Node, bool) {
	for held, loop := n, n.Parent(); loop.Exists(); held, loop = loop, loop.Parent() {
		walked, walks := loop.Child("iter").collectionWalkedLive()
		if walks && held.Node().Field == "body" && walked.SameSyntax(collection.Match) {
			return loop, true
		}
	}

	return Node{}, false
}

func (n Node) leavesThatLoopAtOnce(loop Node) bool {
	block := n.blockHolding()
	if len(block) == 0 {
		return false
	}

	return block[len(block)-1].leaves(loop)
}

func (n Node) blockHolding() []Node {
	for held := n; held.Exists(); held = held.Parent() {
		if field := held.Node().Field; slices.Contains([]string{"body", "orelse", "finalbody"}, field) {
			return held.Parent().ChildrenIn(field)
		}
	}

	return nil
}

func (n Node) leaves(loop Node) bool {
	if slices.Contains([]string{"Return", "Raise"}, n.Kind()) {
		return true
	}

	return n.Kind() == "Break" && n.nearestLoop().Node() == loop.Node()
}

func (n Node) nearestLoop() Node {
	return Node{n.Closest(engine.Loop)}
}

func (n Node) collectionWalkedLive() (Node, bool) {
	if n.namesOneCollection() {
		return n, true
	}
	if !n.IsCall() {
		return Node{}, false
	}
	callee, arguments := n.Callee(), n.Arguments()
	if slices.Contains(collectionViews, callee.DottedName()) && len(arguments) == 1 {
		return arguments[0].collectionWalkedLive()
	}
	if slices.Contains(mappingViews, callee.Name()) && callee.Kind() == "Attribute" && len(arguments) == 0 {
		return callee.Child("value").collectionWalkedLive()
	}

	return Node{}, false
}

package python

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

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

// containerMutators are the methods of a builtin list, dict or set that change it in place. Calling one on the
// collection a loop is walking moves the sequence under the iterator mid-walk.
var containerMutators = []string{"append", "extend", "insert", "remove", "pop", "clear", "add", "discard", "update", "setdefault", "popitem"}

// ChangesTheCollectionItsLoopWalks says whether the node changes, in place, the very collection a loop around it
// is walking: a method that changes a builtin container, or a `del` of one of its items, on an expression written
// exactly as that loop's own subject. The iterator then reads a sequence that has moved under it, so the loop
// skips an element, sees one twice, or raises. Walking a copy is a different expression and is left alone.
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

// collectionItChanges is the collection the node changes in place, and whether it changes one at all.
func (n Node) collectionItChanges() (Node, bool) {
	if n.IsCall() && slices.Contains(containerMutators, n.Callee().Name()) {
		return n.Callee().Child("value"), true
	}
	if n.Kind() == "Delete" && len(n.ChildrenIn("targets")) == 1 {
		return n.ChildrenIn("targets")[0].subscripted()
	}

	return Node{}, false
}

// subscripted is the collection an item access reads from, and whether the node is one.
func (n Node) subscripted() (Node, bool) {
	if n.Kind() != "Subscript" {
		return Node{}, false
	}

	return n.Child("value"), true
}

// namesOneCollection says whether the expression names a collection that outlives the statement: a local or a
// field, never a fresh list a call or a slice just built, which is the whole of walking a copy.
func (n Node) namesOneCollection() bool {
	return slices.Contains([]string{"Name", "Attribute"}, n.Kind())
}

// collectionViews are the builtins that hand out a view of a collection rather than a copy of it, and the
// methods a mapping answers its own entries with. A loop over one of these walks the live collection, so
// changing that collection changes what the loop has left to read.
var collectionViews, mappingViews = []string{"enumerate", "reversed", "iter"}, []string{"items", "keys", "values"}

// loopWalking is the loop around the node that walks the given collection, and whether one does, through a view
// of it as well as directly. The node has to sit in the loop's own body: a for's `else` runs once the walk is
// over, so a collection changed there is read by nobody.
func (n Node) loopWalking(collection Node) (Node, bool) {
	for held, loop := n, n.Parent(); loop.Exists(); held, loop = loop, loop.Parent() {
		walked, walks := loop.Child("iter").collectionWalkedLive()
		if walks && held.Node().Field == "body" && walked.SameSyntax(collection.Match) {
			return loop, true
		}
	}

	return Node{}, false
}

// leavesThatLoopAtOnce says whether control cannot reach the loop's iterator again once the node has run: the
// block holding it ends by leaving, with a return, a raise or a break out of that very loop. A find-and-remove
// that returns the moment it has removed reads nothing more, so the sequence moving under the iterator costs it
// nothing, and it is how the work is written in Python.
func (n Node) leavesThatLoopAtOnce(loop Node) bool {
	block := n.blockHolding()
	if len(block) == 0 {
		return false
	}

	return block[len(block)-1].leaves(loop)
}

// blockHolding is the statements of the innermost block the node sits in, in source order.
func (n Node) blockHolding() []Node {
	for held := n; held.Exists(); held = held.Parent() {
		if field := held.Node().Field; slices.Contains([]string{"body", "orelse", "finalbody"}, field) {
			return held.Parent().ChildrenIn(field)
		}
	}

	return nil
}

// leaves says whether the statement hands control out of the loop for good: a return or a raise leaves the
// function around it, and a break leaves the loop it sits in.
func (n Node) leaves(loop Node) bool {
	if slices.Contains([]string{"Return", "Raise"}, n.Kind()) {
		return true
	}

	return n.Kind() == "Break" && n.nearestLoop().Node() == loop.Node()
}

// nearestLoop is the innermost loop around the node; no node outside one.
func (n Node) nearestLoop() Node {
	return Node{n.Closest(engine.Loop)}
}

// collectionWalkedLive is the collection a loop's subject reads as it goes, and whether it reads one live: the
// collection written as itself, or a view over it. `list(items)`, `sorted(items)` and `items[:]` build a
// sequence of their own, which is how a loop walks a collection it means to change, so neither answers one.
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

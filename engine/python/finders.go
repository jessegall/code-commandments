package python

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

// deNulledBy is how many callers must turn the absence into a failure before a finder's `| None` is the lie: one
// caller doing it is one caller's business, two or more is the finder's.
const deNulledBy = 2

// IsDeNulledFinder says whether the def answers `X | None` for an object and every caller outside the tests, two
// or more of them, turns that None into a failure where it stands — asserting it away, raising on it, or reading
// through it as though it could not be there. The annotation then says the value may be missing and no caller
// agrees: the absence is the finder's to decide, by raising, so the callers stop deciding it over and over.
func (p *Program) IsDeNulledFinder(def Node) bool {
	if def.Kind() != "FunctionDef" && def.Kind() != "AsyncFunctionDef" {
		return false
	}
	returns := def.Child("returns")
	held, optional := returns.OptionalOf()
	if !optional || !p.DeclaresClass(held.DottedName()) {
		return false
	}

	outside, deNulled := 0, 0
	for _, call := range p.CallersOf(def) {
		if call.Match.IsTest() {
			continue
		}
		outside++
		if p.ResultIsTakenAsPresent(call) {
			deNulled++
		}
	}

	return outside >= deNulledBy && deNulled == outside
}

// resultIsTakenAsPresent says whether the call's own result is used as though it could not be None: read through
// where it stands, handed straight to something that requires one, or bound to a name the function then asserts,
// casts or raises on.
func (p *Program) ResultIsTakenAsPresent(n Node) bool {
	if n.readThrough() || p.handedOnWhereOneIsRequired(n) {
		return true
	}
	bound := n.boundName()
	function := Node{n.Closest(engine.Function)}
	if bound == "" || !function.Exists() {
		return false
	}

	return slices.ContainsFunc(function.Descendants(), func(read Node) bool {
		return read.Kind() == "Name" && read.Name() == bound && read.takenAsPresent()
	})
}

// handedOnWhereOneIsRequired says whether the result goes straight into a parameter annotated WITHOUT `| None`.
// That is the type checker's "Expected X, got X | None", and the caller's way of saying it knows the value is
// there: it has not looked, and it has not left the absence to anybody else either.
func (p *Program) handedOnWhereOneIsRequired(n Node) bool {
	outer := n.Parent()
	if !outer.IsCall() {
		return false
	}
	at := -1
	for position, argument := range outer.Arguments() {
		if argument.Node() == n.Node() {
			at = position
		}
	}
	target, found := p.Callee(outer)
	if at < 0 || !found {
		return false
	}

	parameters := target.Parameters()
	// A method's first parameter is the object it is called on, which the call does not fill.
	if target.Parent().Kind() == "ClassDef" && len(parameters) > 0 {
		parameters = parameters[1:]
	}
	if at >= len(parameters) {
		return false
	}

	annotation := parameters[at].Child("annotation")
	if !annotation.Exists() {
		return false
	}
	_, optional := annotation.OptionalOf()

	return !optional
}

// readThrough says whether the call's result is reached into where it stands: `find(id).name`, `find(id)[0]` or
// `find(id)()`, each of which fails at once when the answer is None.
func (n Node) readThrough() bool {
	parent := n.Parent()
	if !slices.Contains([]string{"Attribute", "Subscript", "Call"}, parent.Kind()) {
		return false
	}

	return parent.Child("value").Node() == n.Node() || parent.Child("func").Node() == n.Node()
}

// boundName is the name a plain `name = call()` binds the call's result to; empty for a result bound to anything
// more involved, or to nothing.
func (n Node) boundName() string {
	assigned := n.Parent()
	if assigned.Kind() != "Assign" && assigned.Kind() != "AnnAssign" {
		return ""
	}
	if assigned.Child("value").Node() != n.Node() {
		return ""
	}
	targets := assigned.ChildrenIn("targets")
	if assigned.Kind() == "AnnAssign" {
		targets = []Node{assigned.Child("target")}
	}
	if len(targets) != 1 || targets[0].Kind() != "Name" {
		return ""
	}

	return targets[0].Name()
}

// takenAsPresent says whether the read of a name turns its absence into a failure: the subject of an `assert … is
// not None`, of an `if … is None` that only raises, or the value handed to `cast`.
//
// Reading THROUGH the name is deliberately not enough. A caller that guards the None first and reads the value
// after is doing exactly what the annotation asks of it, and every such caller reads through the name somewhere;
// only the call's OWN result, read through where it stands, says nobody checked.
func (n Node) takenAsPresent() bool {
	if n.handedToCast() {
		return true
	}
	test := Node{n.Closest(engine.Comparison)}
	if !test.Exists() || !test.testsForNone(n) {
		return false
	}
	if test.Parent().Kind() == "Assert" {
		return n.assertsPresence(test)
	}

	return test.Parent().Kind() == "If" && test.Parent().onlyRaises()
}

// testsForNone says whether the comparison is a test of the read against None.
func (n Node) testsForNone(read Node) bool {
	if !slices.Contains(n.ChildrenIn("ops"), read) && n.Child("left").Node() != read.Node() {
		return false
	}

	return slices.ContainsFunc(n.ChildrenIn("comparators"), func(side Node) bool { return side.IsNone() })
}

// assertsPresence says whether the assertion insists the value is there: `assert x is not None`.
func (n Node) assertsPresence(test Node) bool {
	return test.Node().Operator == "is not" || test.Node().Operator == "!="
}

// handedToCast says whether the read is the value of a `cast(T, x)`, which tells the type checker the None is gone
// without anything checking that it is.
func (n Node) handedToCast() bool {
	call := n.Parent()
	if !call.IsCall() || !slices.Contains([]string{"cast", "typing.cast"}, call.Callee().DottedName()) {
		return false
	}
	arguments := call.Arguments()

	return len(arguments) == 2 && arguments[1].Node() == n.Node()
}

// onlyRaises says whether the branch's whole body is a raise.
func (n Node) onlyRaises() bool {
	body := n.ChildrenIn("body")

	return len(body) == 1 && body[0].Kind() == "Raise"
}

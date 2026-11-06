package python

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

const deNulledBy = 2

func (p *Program) NoCallerHandlesTheAbsence(def Node) bool {
	for _, call := range p.CallersOf(def) {
		if call.Match.IsTest() {
			continue
		}

		if !p.ResultIsTakenAsPresent(call) {
			return false
		}
	}

	return true
}

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

func (n Node) readThrough() bool {
	parent := n.Parent()
	if !slices.Contains([]string{"Attribute", "Subscript", "Call"}, parent.Kind()) {
		return false
	}

	return parent.Child("value").Node() == n.Node() || parent.Child("func").Node() == n.Node()
}

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

func (n Node) testsForNone(read Node) bool {
	if !slices.Contains(n.ChildrenIn("ops"), read) && n.Child("left").Node() != read.Node() {
		return false
	}

	return slices.ContainsFunc(n.ChildrenIn("comparators"), func(side Node) bool { return side.IsNone() })
}

func (n Node) assertsPresence(test Node) bool {
	return test.Node().Operator == "is not" || test.Node().Operator == "!="
}

func (n Node) handedToCast() bool {
	call := n.Parent()
	if !call.IsCall() || !slices.Contains([]string{"cast", "typing.cast"}, call.Callee().DottedName()) {
		return false
	}
	arguments := call.Arguments()

	return len(arguments) == 2 && arguments[1].Node() == n.Node()
}

func (n Node) onlyRaises() bool {
	body := n.ChildrenIn("body")

	return len(body) == 1 && body[0].Kind() == "Raise"
}

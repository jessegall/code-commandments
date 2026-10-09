package python

import (
	"slices"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/engine"
)

// attributeFlow is every attribute read of the program, tallied once: by the class whose method reads its own
// `self.x`, and by the name of the class mypy types a receiver as.
type attributeFlow struct {
	once  sync.Once
	own   map[Node]map[string]engine.FlowVerdict
	typed map[string]map[string]engine.FlowVerdict
}

// AttributeFlow is how the program reads the class's attribute: how many reads dereference it unguarded, and how
// many admit it may be missing — `self.x` in one of the class's methods, or `obj.x` where mypy typed `obj` as it.
func (p *Program) AttributeFlow(class Node, attribute string) engine.FlowVerdict {
	p.flow.once.Do(p.tallyReads)
	own, typed := p.flow.own[class][attribute], p.flow.typed[class.Name()][attribute]

	return engine.FlowVerdict{Assume: own.Assume + typed.Assume, Guard: own.Guard + typed.Guard}
}

// tallyReads reads every evaluated attribute access of the code once. A test's reads are left out: a test indexes the
// field of an object it built itself, which says nothing of whether the code assumes the field set.
func (p *Program) tallyReads() {
	p.flow.own, p.flow.typed = map[Node]map[string]engine.FlowVerdict{}, map[string]map[string]engine.FlowVerdict{}
	for _, module := range p.modules {
		if module.File.Match(0).IsTest() {
			continue
		}
		for _, read := range module.Nodes() {
			if read.Kind() != "Attribute" || !read.IsEvaluated() {
				continue
			}
			tally := p.tallyFor(read)
			if tally == nil {
				continue
			}
			verdict := tally[read.Name()]
			around := read.wrapper()
			if read.isTested() || around.acknowledgesAbsenceOf(read) {
				verdict.Guard++
			}
			if around.dereferences(read) {
				verdict.Assume++
			}
			tally[read.Name()] = verdict
		}
	}
}

// tallyFor is the tally the read counts towards: its own class's for `self.x` in a method, the typed class's for
// a receiver mypy typed as a class.
func (p *Program) tallyFor(read Node) map[string]engine.FlowVerdict {
	if read.IsOwnAttributeRead() {
		class := read.EnclosingFunction().Parent()
		if !read.EnclosingFunction().Exists() || class.Kind() != "ClassDef" {
			return nil
		}
		if p.flow.own[class] == nil {
			p.flow.own[class] = map[string]engine.FlowVerdict{}
		}

		return p.flow.own[class]
	}
	resolved := read.Child("value").Node().Resolved
	if resolved == nil || resolved.Kind != "named" {
		return nil
	}
	name := resolved.Name[strings.LastIndex(resolved.Name, ".")+1:]
	if p.flow.typed[name] == nil {
		p.flow.typed[name] = map[string]engine.FlowVerdict{}
	}

	return p.flow.typed[name]
}

// IsOwnAttributeRead says whether the expression reads an attribute of `self`.
func (n Node) IsOwnAttributeRead() bool {
	return n.Kind() == "Attribute" && n.Child("value").DottedName() == "self"
}

// SelfAttribute is the attribute of `self` the expression reads; empty for any other expression.
func (n Node) SelfAttribute() string {
	if !n.IsOwnAttributeRead() {
		return ""
	}

	return n.Name()
}

// wrapper is the expression the expression sits directly in; no node for one a statement holds itself.
func (n Node) wrapper() Node {
	parent := n.Parent()
	if parent.IsStatement() || parent.Node() == nil || parent.Node().Role == "type" {
		return Node{}
	}

	return parent
}

// isTested says whether the expression stands as the condition of an if, a while or a conditional expression.
func (n Node) isTested() bool {
	parent := n.Parent()

	return slices.Contains([]string{"If", "While", "IfExp"}, parent.Kind()) && parent.Child("test") == n
}

// dereferences says whether the expression reaches through the inner one: calls it, reads an attribute of it, or
// indexes it.
func (n Node) dereferences(inner Node) bool {
	switch n.Kind() {
	case "Call":
		return n.Callee() == inner
	case "Attribute", "Subscript":
		return n.Child("value") == inner
	}

	return false
}

// acknowledgesAbsenceOf says whether the expression admits the inner one may be missing: compares it to None with
// `is` or `is not`, negates it, or short-circuits on it.
func (n Node) acknowledgesAbsenceOf(inner Node) bool {
	if operand, ok := n.NoneTestedOperand(); ok && operand == inner {
		return true
	}

	return n.IsNegation() || n.IsShortCircuit()
}

// NoneTestedOperand is what an `is None` or `is not None` comparison tests.
func (n Node) NoneTestedOperand() (Node, bool) {
	comparators := n.ChildrenIn("comparators")
	if n.Kind() != "Compare" || (n.Node().Operator != "is" && n.Node().Operator != "is not") || len(comparators) != 1 {
		return Node{}, false
	}
	left, right := n.Child("left"), comparators[0]
	if right.IsNone() {
		return left, true
	}

	return right, left.IsNone()
}

// IsNegation says whether the expression is a `not`.
func (n Node) IsNegation() bool {
	return n.Kind() == "UnaryOp" && n.Node().Operator == "not"
}

// IsShortCircuit says whether the expression is an `and` or an `or`.
func (n Node) IsShortCircuit() bool {
	return n.Kind() == "BoolOp"
}

// FieldNames is the instance's fields, in the order first written: the names the class body annotates, a ClassVar
// aside, then the attributes its __init__ sets on `self`.
func (n Node) FieldNames() []string {
	var names []string
	add := func(name string) {
		if name != "" && !slices.Contains(names, name) {
			names = append(names, name)
		}
	}
	for _, statement := range n.ChildrenIn("body") {
		if target := statement.Child("target"); statement.Kind() == "AnnAssign" && target.Kind() == "Name" && !statement.Child("annotation").IsClassVarType() {
			add(target.Name())
		}
	}
	if init := n.Initializer(); init.Exists() {
		for _, statement := range init.statementsIn() {
			for _, target := range statement.writtenTargets() {
				add(target.SelfAttribute())
			}
		}
	}

	return names
}

// IsClassVarType says whether the annotation is a ClassVar, bare or subscripted.
func (n Node) IsClassVarType() bool {
	named := n
	if n.Kind() == "Subscript" {
		named = n.Child("value")
	}

	return named.DottedName() == "ClassVar" || named.DottedName() == "typing.ClassVar"
}

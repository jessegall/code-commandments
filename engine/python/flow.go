package python

import (
	"slices"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/engine"
)

// attributeFlow is every attribute read of the program, tallied once: by the class whose method reads its own `self.x`, and by the name of the class mypy types a receiver as.
type attributeFlow struct {
	once  sync.Once
	own   map[Node]map[string]engine.FlowVerdict
	typed map[string]map[string]engine.FlowVerdict
}

func (p *Program) AttributeFlow(class Node, attribute string) engine.FlowVerdict {
	p.flow.once.Do(p.tallyReads)
	own, typed := p.flow.own[class][attribute], p.flow.typed[class.Name()][attribute]

	return engine.FlowVerdict{Assume: own.Assume + typed.Assume, Guard: own.Guard + typed.Guard}
}

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

func (n Node) IsOwnAttributeRead() bool {
	return n.Kind() == "Attribute" && n.Child("value").DottedName() == "self"
}

func (n Node) SelfAttribute() string {
	if !n.IsOwnAttributeRead() {
		return ""
	}

	return n.Name()
}

func (n Node) wrapper() Node {
	parent := n.Parent()
	if parent.IsStatement() || parent.Node() == nil || parent.Node().Role == "type" {
		return Node{}
	}

	return parent
}

func (n Node) isTested() bool {
	parent := n.Parent()

	return slices.Contains([]string{"If", "While", "IfExp"}, parent.Kind()) && parent.Child("test") == n
}

func (n Node) dereferences(inner Node) bool {
	switch n.Kind() {
	case "Call":
		return n.Callee() == inner
	case "Attribute", "Subscript":
		return n.Child("value") == inner
	}

	return false
}

func (n Node) acknowledgesAbsenceOf(inner Node) bool {
	if operand, ok := n.NoneTestedOperand(); ok && operand == inner {
		return true
	}

	return n.IsNegation() || n.IsShortCircuit()
}

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

func (n Node) IsNegation() bool {
	return n.Kind() == "UnaryOp" && n.Node().Operator == "not"
}

func (n Node) IsShortCircuit() bool {
	return n.Kind() == "BoolOp"
}

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

func (n Node) IsClassVarType() bool {
	named := n
	if n.Kind() == "Subscript" {
		named = n.Child("value")
	}

	return named.DottedName() == "ClassVar" || named.DottedName() == "typing.ClassVar"
}

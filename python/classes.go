package python

import (
	"strings"
	"sync"
)

// classes are the classes the program declares by their own name, the first declared where several share one,
// and the ones a getattr dispatches to by a name known only at run time.
type classes struct {
	once       sync.Once
	named      map[string]Node
	dispatched map[Node]bool
}

// ClassCalled is the class a module here declares under the name the dotted spelling ends in: the first, when
// several share it.
func (p *Program) ClassCalled(dotted string) (Node, bool) {
	p.classes.once.Do(p.findClasses)
	class, ok := p.classes.named[dotted[strings.LastIndex(dotted, ".")+1:]]

	return class, ok
}

// DeclaresClass says whether a module here declares a class named as the dotted spelling ends. Judging a
// subtree can only answer no for a class declared outside it, never yes for one that is not declared.
func (p *Program) DeclaresClass(dotted string) bool {
	_, ok := p.ClassCalled(dotted)

	return ok
}

// Ancestry is the class and every base of it the program declares, nearest first.
func (p *Program) Ancestry(class Node) []Node {
	var line []Node
	seen := map[Node]bool{}
	for pending := []Node{class}; len(pending) > 0; pending = pending[1:] {
		if seen[pending[0]] {
			continue
		}
		seen[pending[0]] = true
		line = append(line, pending[0])
		for _, base := range pending[0].ChildrenIn("bases") {
			if parent, ok := p.ClassCalled(base.DottedName()); ok {
				pending = append(pending, parent)
			}
		}
	}

	return line
}

// IsBuiltFromData says whether the class, or a base the program declares, has a named constructor that turns
// loose data into it. Its fields then hold what the data holds.
func (p *Program) IsBuiltFromData(class Node) bool {
	for _, kind := range p.Ancestry(class) {
		for _, method := range kind.Methods() {
			if method.IsNamedConstructor() {
				return true
			}
		}
	}

	return false
}

// IsDispatchedByName says whether the class's methods are reached by a name known only at run time: a getattr
// with a name that is no literal, on the class or on a base the program declares. Its public methods are then
// called from outside the code and take what that boundary hands them.
func (p *Program) IsDispatchedByName(class Node) bool {
	p.classes.once.Do(p.findClasses)
	for _, kind := range p.Ancestry(class) {
		if p.classes.dispatched[kind] {
			return true
		}
	}

	return false
}

// findClasses reads every class declaration and every getattr by a run-time name, once.
func (p *Program) findClasses() {
	p.classes.named = map[string]Node{}
	p.classes.dispatched = map[Node]bool{}
	for _, module := range p.modules {
		for _, node := range module.Nodes() {
			if _, ok := p.classes.named[node.Name()]; node.Kind() == "ClassDef" && !ok {
				p.classes.named[node.Name()] = node
			}
		}
	}
	for _, module := range p.modules {
		for _, call := range module.Nodes() {
			if class, ok := p.dispatchedBy(call); ok {
				p.classes.dispatched[class] = true
			}
		}
	}
}

// dispatchedBy is the class a getattr with a run-time name reads from: `self` in one of its methods, or an
// object mypy types as it.
func (p *Program) dispatchedBy(call Node) (Node, bool) {
	arguments := call.Arguments()
	if !call.IsCall() || call.Callee().DottedName() != "getattr" || len(arguments) < 2 || arguments[1].Node().Literal != "" {
		return Node{}, false
	}
	receiver := arguments[0]
	if receiver.DottedName() == "self" {
		class := receiver.EnclosingClass()

		return class, class.Exists()
	}
	if resolved := receiver.Node().Resolved; resolved != nil && resolved.Kind == "named" {
		return p.ClassCalled(resolved.Name)
	}

	return Node{}, false
}

// EnclosingClass is the nearest class around the node; no node at module level.
func (n Node) EnclosingClass() Node {
	for around := n.Parent(); around.Exists(); around = around.Parent() {
		if around.Kind() == "ClassDef" {
			return around
		}
	}

	return Node{}
}

// IsNamedConstructor says whether the def is a @classmethod that returns `cls(...)`: where loose data becomes
// the class, the one place reading that data by key belongs.
func (n Node) IsNamedConstructor() bool {
	if !n.IsClassMethod() {
		return false
	}
	for _, value := range n.ReturnedValues() {
		if value.IsCall() && value.Callee().DottedName() == "cls" {
			return true
		}
	}

	return false
}

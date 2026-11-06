package python

import (
	"slices"
	"strings"
	"sync"
)

// classes are the classes the program declares by their own name, the first declared where several share one, and the ones a getattr dispatches to by a name known only at run time.
type classes struct {
	once       sync.Once
	named      map[string]Node
	dispatched map[Node]bool
}

func (p *Program) ClassCalled(dotted string) (Node, bool) {
	p.classes.once.Do(p.findClasses)

	return p.classes.called(dotted)
}

func (c *classes) called(dotted string) (Node, bool) {
	class, ok := c.named[dotted[strings.LastIndex(dotted, ".")+1:]]

	return class, ok
}

func (p *Program) DeclaresClass(dotted string) bool {
	_, ok := p.ClassCalled(dotted)

	return ok
}

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

func (p *Program) IsDispatchedByName(class Node) bool {
	p.classes.once.Do(p.findClasses)
	for _, kind := range p.Ancestry(class) {
		if p.classes.dispatched[kind] {
			return true
		}
	}

	return false
}

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
		return p.classes.called(resolved.Name)
	}

	return Node{}, false
}

func (n Node) EnclosingClass() Node {
	for around := n.Parent(); around.Exists(); around = around.Parent() {
		if around.Kind() == "ClassDef" {
			return around
		}
	}

	return Node{}
}

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

const protocolMarker = "Protocol"

func (p *Program) AnswersAProtocol(method Node) bool {
	p.classes.once.Do(p.findClasses)
	class := method.Parent()
	if method.Name() == "" || class.Kind() != "ClassDef" {
		return false
	}
	for _, declared := range p.classes.named {
		if declared == class || !p.declaresAProtocol(declared) {
			continue
		}
		if answered, ok := p.MethodOf(declared, method.Name()); ok && takesAlike(answered, method) {
			return true
		}
	}

	return false
}

func (p *Program) declaresAProtocol(class Node) bool {
	for _, base := range class.ChildrenIn("bases") {
		spelled := base.DottedName()
		if spelled == "" {
			spelled = base.Child("value").DottedName()
		}
		if spelled == protocolMarker || strings.HasSuffix(spelled, "."+protocolMarker) {
			return true
		}
	}

	return false
}

func takesAlike(one, other Node) bool {
	return slices.Equal(takenNames(one), takenNames(other))
}

func takenNames(def Node) []string {
	var names []string
	for _, parameter := range def.Parameters() {
		if name := parameter.Name(); name != "self" && name != "cls" {
			names = append(names, name)
		}
	}

	return names
}

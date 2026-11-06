package python

import (
	"fmt"
	"slices"
	"sync"
)

// callGraph is which evaluated calls reach each def, and the def each one reaches; and which calls reach a def only on an object of one of its subclasses, through the class a class attribute holds there.
type callGraph struct {
	once         sync.Once
	callers      map[Node][]Node
	targets      map[Node]Node
	throughClass map[Node]bool
	held         map[Node][]Node
}

func (p *Program) graph() *callGraph {
	p.calls.once.Do(func() {
		p.calls.callers, p.calls.targets, p.calls.throughClass, p.calls.held = map[Node][]Node{}, map[Node]Node{}, map[Node]bool{}, map[Node][]Node{}
		heirs := p.heirs()
		for _, module := range p.modules {
			for _, call := range module.Nodes() {
				if !call.IsCall() || !call.IsEvaluated() {
					continue
				}
				target, ok := p.Callee(call)
				for _, reached := range p.heldTargets(call, heirs) {
					if ok && reached == target {
						continue
					}
					p.calls.held[reached] = append(p.calls.held[reached], call)
				}
				if !ok {
					continue
				}
				p.calls.callers[target] = append(p.calls.callers[target], call)
				p.calls.targets[call] = target
				owner := call.Callee().Child("value")
				if found, ok := module.named(owner.Name()); call.Callee().Kind() == "Attribute" && owner.Kind() == "Name" && ok && found.Kind() == "ClassDef" {
					p.calls.throughClass[call] = true
				}
			}
		}
	})

	return &p.calls
}

func (p *Program) CallersOf(def Node) []Node {
	return p.graph().callers[def]
}

func (p *Program) HeldCallersOf(method Node) []Node {
	return p.graph().held[method]
}

func (p *Program) heldTargets(call Node, heirs map[Node][]Node) []Node {
	callee := call.Child("func")
	owner := callee.Child("value")
	function := call.EnclosingFunction()
	if callee.Kind() != "Attribute" || owner.Kind() != "Attribute" || owner.Child("value").DottedName() != "self" || function.Parent().Kind() != "ClassDef" {
		return nil
	}
	var reached []Node
	for _, class := range append([]Node{function.Parent()}, heirs[function.Parent()]...) {
		if inherited, ok := p.MethodOf(class, function.Name()); !ok || inherited != function {
			continue
		}
		held, ok := p.heldClass(class, owner.Name(), map[Node]bool{})
		if !ok {
			continue
		}
		if method, ok := p.MethodOf(held, callee.Name()); ok && !slices.Contains(reached, method) {
			reached = append(reached, method)
		}
	}

	return reached
}

func (p *Program) heirs() map[Node][]Node {
	direct := map[Node][]Node{}
	for _, module := range p.modules {
		for _, class := range module.Nodes() {
			if class.Kind() != "ClassDef" {
				continue
			}
			for _, base := range class.ChildrenIn("bases") {
				parent, named := p.ClassNamed(base, module)
				if !named {
					continue
				}
				direct[parent] = append(direct[parent], class)
			}
		}
	}
	every := map[Node][]Node{}
	for class := range direct {
		seen := map[Node]bool{class: true}
		for waiting := slices.Clone(direct[class]); len(waiting) > 0; waiting = waiting[1:] {
			if heir := waiting[0]; !seen[heir] {
				seen[heir] = true
				every[class] = append(every[class], heir)
				waiting = append(waiting, direct[heir]...)
			}
		}
	}

	return every
}

func (p *Program) TargetOf(call Node) (Node, bool) {
	target, ok := p.graph().targets[call]

	return target, ok
}

func DeclarationOf(def Node) string {
	return fmt.Sprintf("%s:%d", def.File(), def.Line())
}

// definitions is every def of the program by where it is declared, read once: the way back from the name a call graph is keyed by to the def itself.
type definitions struct {
	once  sync.Once
	byKey map[string]Node
}

func (p *Program) definitions() map[string]Node {
	p.defined.once.Do(func() {
		p.defined.byKey = map[string]Node{}
		for _, module := range p.modules {
			for _, node := range module.Nodes() {
				if node.IsFunction() {
					p.defined.byKey[DeclarationOf(node)] = node
				}
			}
		}
	})

	return p.defined.byKey
}

func (p *Program) CallersOfDeclaration(declaration string) []Node {
	def, found := p.definitions()[declaration]
	if !found {
		return nil
	}

	return p.CallersOf(def)
}

func (p *Program) IsOverridden(method Node) bool {
	class := method.Parent()
	if class.Kind() != "ClassDef" {
		return false
	}
	for _, module := range p.modules {
		for _, other := range module.Nodes() {
			if other.Kind() != "ClassDef" {
				continue
			}
			if _, ok := p.MethodOf(other, method.Name()); !ok {
				continue
			}
			for _, base := range other.ChildrenIn("bases") {
				if parent, ok := p.ClassNamed(base, module); ok && parent == class {
					return true
				}
			}
		}
	}

	return false
}

func (p *Program) ExtendsOutside(method Node) bool {
	class := method.Parent()
	if class.Kind() != "ClassDef" {
		return false
	}
	module := p.ModuleOf(class)
	for _, base := range class.ChildrenIn("bases") {
		if _, ok := p.ClassNamed(base, module); !ok && base.DottedName() != "object" {
			return true
		}
	}

	return false
}

func (p *Program) ArgumentsAt(call Node) (map[string]Node, bool) {
	target, ok := p.TargetOf(call)
	if !ok {
		return nil, false
	}

	return p.ArgumentsFor(call, target)
}

func (p *Program) ArgumentsFor(call, target Node) (map[string]Node, bool) {
	if slices.ContainsFunc(call.Arguments(), func(argument Node) bool { return argument.Kind() == "Starred" }) {
		return nil, false
	}
	if slices.ContainsFunc(call.Keywords(), func(keyword Node) bool { return keyword.Name() == "" }) {
		return nil, false
	}
	arguments := target.Child("args")
	positional := append(arguments.ChildrenIn("posonlyargs"), arguments.ChildrenIn("args")...)
	if p.bindsFirstParameter(call, target) && len(positional) > 0 {
		positional = positional[1:]
	}
	rest := arguments.Child("vararg")
	bound := map[string]Node{}
	for position, argument := range call.Arguments() {
		name := rest.Name()
		if position < len(positional) {
			name = positional[position].Name()
		}
		if _, taken := bound[name]; name != "" && !taken {
			bound[name] = argument
		}
	}
	for _, keyword := range call.Keywords() {
		if _, taken := bound[keyword.Name()]; !taken {
			bound[keyword.Name()] = keyword.Child("value")
		}
	}

	return bound, true
}

func (p *Program) bindsFirstParameter(call, target Node) bool {
	if !target.IsMethod() || target.IsStatic() || call.Callee().Kind() != "Attribute" {
		return false
	}

	return target.IsClassMethod() || !p.graph().throughClass[call]
}

func (p *Program) PassesLiteralKey(call Node) bool {
	target, ok := p.TargetOf(call)
	if !ok {
		return false
	}
	keys := target.KeyParameters()
	bound, ok := p.ArgumentsAt(call)
	if len(keys) == 0 || !ok {
		return false
	}

	return slices.ContainsFunc(keys, func(key string) bool {
		_, isText := bound[key].Text()

		return isText
	})
}

func (p *Program) ReadsForwardedKeywords(read Node) bool {
	base, ok := read.StringKeyBase()
	if !ok || base.Kind() != "Name" {
		return false
	}
	reader := read.EnclosingFunction()
	calls := p.DispatchedCallersOf(reader)

	return len(calls) > 0 && !slices.ContainsFunc(calls, func(call Node) bool {
		bound, ok := p.ArgumentsFor(call, reader)
		handed := bound[base.Name()]
		rest := call.EnclosingFunction().Child("args").Child("kwarg")

		return !ok || handed.Kind() != "Name" || !rest.Exists() || handed.Name() != rest.Name()
	})
}

func (n Node) KeyParameters() []string {
	names := parameterNames(n.Parameters())
	loops := map[string]string{}
	for _, statement := range n.statementsIn() {
		target, iterable := statement.Child("target"), statement.Child("iter")
		if statement.Kind() == "For" && target.Kind() == "Name" && slices.Contains(names, iterable.DottedName()) {
			loops[target.Name()] = iterable.DottedName()
		}
	}
	readers := names
	if len(readers) > 0 && (readers[0] == "self" || readers[0] == "cls") {
		readers = readers[1:]
	}
	var keys []string
	for _, statement := range n.ChildrenIn("body") {
		for _, part := range append([]Node{statement}, statement.Descendants()...) {
			key, ok := part.KeyReadOf(readers)
			if !ok {
				continue
			}
			name := key.DottedName()
			if !slices.Contains(names, name) {
				name = loops[name]
			}
			if name != "" && !slices.Contains(keys, name) {
				keys = append(keys, name)
			}
		}
	}

	return keys
}

func (n Node) KeyReadOf(names []string) (Node, bool) {
	if n.Kind() == "Subscript" && slices.Contains(names, n.Child("value").DottedName()) {
		return n.Child("slice"), true
	}
	callee := n.Callee()
	if n.IsCall() && callee.Kind() == "Attribute" && callee.Name() == "get" && slices.Contains(names, callee.Child("value").DottedName()) && len(n.Arguments()) > 0 {
		return n.Arguments()[0], true
	}

	return Node{}, false
}

func parameterNames(parameters []Node) []string {
	names := make([]string, len(parameters))
	for at, parameter := range parameters {
		names[at] = parameter.Name()
	}

	return names
}

package python

import (
	"fmt"
	"slices"
	"sync"
)

// callGraph is which evaluated calls reach each def, and the def each one reaches.
type callGraph struct {
	once         sync.Once
	callers      map[Node][]Node
	targets      map[Node]Node
	throughClass map[Node]bool
}

// graph is the program's call graph, built once over every call the code evaluates.
func (p *Program) graph() *callGraph {
	p.calls.once.Do(func() {
		p.calls.callers, p.calls.targets, p.calls.throughClass = map[Node][]Node{}, map[Node]Node{}, map[Node]bool{}
		for _, module := range p.modules {
			for _, call := range module.Nodes() {
				if !call.IsCall() || !call.IsEvaluated() {
					continue
				}
				target, ok := p.Callee(call)
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

// CallersOf is every call that reaches the def.
func (p *Program) CallersOf(def Node) []Node {
	return p.graph().callers[def]
}

// TargetOf is the def an evaluated call reaches; none when it cannot be resolved.
func (p *Program) TargetOf(call Node) (Node, bool) {
	target, ok := p.graph().targets[call]

	return target, ok
}

// DeclarationOf is where the def is declared, `path:line`: the name its calls share across processes.
func DeclarationOf(def Node) string {
	return fmt.Sprintf("%s:%d", def.File(), def.Line())
}

// IsOverridden says whether a class of the program that names the method's class as a base declares a method of
// the same name, overriding it.
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

// ExtendsOutside says whether the method's class names a base the program does not declare: a contract from
// outside, which any of its methods may be keeping.
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

// ArgumentsAt is what each parameter of the call's target receives there, its name to the argument handed to it,
// with an instance call's `self` (or a classmethod's `cls`) already bound, and the first extra positional in a
// `*rest` parameter. None when the call does not resolve, or unpacks a `*` or `**` argument whose parts no
// reading can place.
func (p *Program) ArgumentsAt(call Node) (map[string]Node, bool) {
	target, ok := p.TargetOf(call)
	if !ok || slices.ContainsFunc(call.Arguments(), func(argument Node) bool { return argument.Kind() == "Starred" }) {
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

// bindsFirstParameter says whether the call arrives with its target's first parameter already bound: an instance
// method called on an instance, or a classmethod called on anything.
func (p *Program) bindsFirstParameter(call, target Node) bool {
	if !target.IsMethod() || target.IsStatic() || call.Callee().Kind() != "Attribute" {
		return false
	}

	return target.IsClassMethod() || !p.graph().throughClass[call]
}

// PassesLiteralKey says whether the call hands a string literal to a parameter its target uses as a key into
// another, the "title" in `text_of(row, "title")`, and so reads a dict by a string key one call deeper.
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

// ReadsForwardedKeywords says whether the key read's mapping is a parameter every call that can reach its def, through
// the methods it overrides too, fills with the caller's own **kwargs: a generic funnel's keyword arguments handed on unchanged, a mapping no record declares.
func (p *Program) ReadsForwardedKeywords(read Node) bool {
	base, ok := read.StringKeyBase()
	if !ok || base.Kind() != "Name" {
		return false
	}
	calls := p.DispatchedCallersOf(read.EnclosingFunction())

	return len(calls) > 0 && !slices.ContainsFunc(calls, func(call Node) bool {
		bound, ok := p.ArgumentsAt(call)
		handed := bound[base.Name()]
		rest := call.EnclosingFunction().Child("args").Child("kwarg")

		return !ok || handed.Kind() != "Name" || !rest.Exists() || handed.Name() != rest.Name()
	})
}

// KeyParameters is the def's parameters it reads another by as a key: `key` in `row[key]` or `row.get(key)`, or a
// parameter a loop walks whose items are keys so.
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

// KeyReadOf is the key the expression reads one of the names by: `k` in `names[k]` or `names.get(k)`.
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

// parameterNames is the parameters' names, in order.
func parameterNames(parameters []Node) []string {
	names := make([]string, len(parameters))
	for at, parameter := range parameters {
		names[at] = parameter.Name()
	}

	return names
}

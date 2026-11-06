package python

import (
	"slices"
	"strings"
)

func (p *Program) Callee(call Node) (Node, bool) {
	module := p.ModuleOf(call)
	if module == nil || call.Kind() != "Call" {
		return Node{}, false
	}
	callee := call.Child("func")
	if callee.Kind() == "Attribute" && callee.Child("value").Kind() == "Call" {
		return p.calledOnResult(callee)
	}
	if callee.Kind() == "Name" {
		if nested, ok := nestedIn(callee.Name(), call); ok {
			return nested, true
		}
		found, ok := module.named(callee.Name())

		return found, ok && found.IsFunction()
	}
	dotted := callee.DottedName()
	if dotted == "" {
		return Node{}, false
	}
	at := strings.LastIndex(dotted, ".")
	owner, member := dotted[:at], dotted[at+1:]
	if bound, ok := module.bindings().modules[owner]; ok {
		found, ok := bound.Declared(member)

		return found, ok && found.IsFunction()
	}
	class, ok := p.classOf(owner, call, module)
	if !ok {
		return Node{}, false
	}

	return p.MethodOf(class, member)
}

func (p *Program) calledOnResult(callee Node) (Node, bool) {
	inner, ok := p.Callee(callee.Child("value"))
	module := p.ModuleOf(inner)
	if !ok || module == nil || !inner.Child("returns").Exists() {
		return Node{}, false
	}
	class, ok := p.ClassNamed(inner.Child("returns"), module)
	if !ok {
		return Node{}, false
	}

	return p.MethodOf(class, callee.Name())
}

func nestedIn(name string, node Node) (Node, bool) {
	for scope := node.EnclosingFunction(); scope.Exists(); scope = scope.Parent().EnclosingFunction() {
		for _, statement := range scope.ChildrenIn("body") {
			for _, inner := range append([]Node{statement}, statement.Descendants()...) {
				if inner.IsFunction() && inner.Name() == name && inner.Parent().EnclosingFunction() == scope {
					return inner, true
				}
			}
		}
	}

	return Node{}, false
}

func (p *Program) classOf(owner string, node Node, module *Module) (Node, bool) {
	function := node.EnclosingFunction()
	if owner == "self" {
		class := function.Parent()

		return class, function.Exists() && class.Kind() == "ClassDef"
	}
	path := strings.Split(owner, ".")
	if len(path) == 2 && path[0] == "self" {
		class, ok := p.classOf("self", node, module)
		if !ok {
			return Node{}, false
		}
		if held, ok := p.heldClass(class, path[1], map[Node]bool{}); ok {
			return held, true
		}
		annotation, ok := class.AttributeAnnotation(path[1])
		if !ok {
			return Node{}, false
		}

		return p.ClassNamed(annotation, module)
	}
	if function.Exists() {
		if annotation, ok := function.AnnotationOf(owner); ok {
			return p.ClassNamed(annotation, module)
		}
	}
	if strings.Contains(owner, ".") {
		return Node{}, false
	}

	return p.classSpelled(owner, module)
}

func (p *Program) ClassNamed(spelled Node, module *Module) (Node, bool) {
	if text, ok := spelled.Text(); ok {
		return p.classSpelled(text, module)
	}

	return p.classSpelled(spelled.DottedName(), module)
}

func (p *Program) classSpelled(dotted string, module *Module) (Node, bool) {
	owner, name := "", dotted
	if at := strings.LastIndex(dotted, "."); at >= 0 {
		owner, name = dotted[:at], dotted[at+1:]
	}
	var found Node
	var ok bool
	if bound, isModule := module.bindings().modules[owner]; isModule {
		found, ok = bound.Declared(name)
	} else {
		found, ok = module.named(dotted)
	}

	return found, ok && found.Kind() == "ClassDef"
}

func (p *Program) MethodOf(class Node, name string) (Node, bool) {
	return p.memberOf(class, func(member Node) bool { return member.IsFunction() && member.Name() == name }, map[Node]bool{})
}

func (p *Program) ConstantOf(class Node, name string) (Node, bool) {
	return p.memberOf(class, func(member Node) bool { return member.DeclaresConstant() && member.declaresName(name) }, map[Node]bool{})
}

func (p *Program) memberOf(class Node, admits func(Node) bool, seen map[Node]bool) (Node, bool) {
	if seen[class] {
		return Node{}, false
	}
	seen[class] = true
	if member, ok := class.firstInBody(admits); ok {
		return member, true
	}
	home := p.homes[class.Node()]
	if home == nil {
		return Node{}, false
	}
	for _, base := range class.ChildrenIn("bases") {
		if parent, ok := p.ClassNamed(base, home); ok {
			if inherited, ok := p.memberOf(parent, admits, seen); ok {
				return inherited, true
			}
		}
	}

	return Node{}, false
}

func (class Node) firstInBody(admits func(Node) bool) (Node, bool) {
	body := class.ChildrenIn("body")
	at := slices.IndexFunc(body, admits)
	if at < 0 {
		return Node{}, false
	}

	return body[at], true
}

func (statement Node) declaresName(name string) bool {
	targets := statement.ChildrenIn("targets")
	if statement.Kind() == "AnnAssign" {
		targets = []Node{statement.Child("target")}
	}

	return slices.ContainsFunc(targets, func(target Node) bool { return target.Kind() == "Name" && target.Name() == name })
}

func (p *Program) heldClass(class Node, attribute string, seen map[Node]bool) (Node, bool) {
	home := p.homes[class.Node()]
	if seen[class] || home == nil {
		return Node{}, false
	}
	seen[class] = true
	for _, member := range class.ChildrenIn("body") {
		if held, ok := member.HeldClassSpelling(attribute); ok {
			return p.ClassNamed(held, home)
		}
	}
	for _, base := range class.ChildrenIn("bases") {
		if parent, ok := p.ClassNamed(base, home); ok {
			if held, ok := p.heldClass(parent, attribute, seen); ok {
				return held, true
			}
		}
	}

	return Node{}, false
}

func (statement Node) HeldClassSpelling(attribute string) (Node, bool) {
	targets := statement.ChildrenIn("targets")
	if statement.Kind() == "Assign" && len(targets) == 1 && targets[0].Kind() == "Name" && targets[0].Name() == attribute {
		return statement.Child("value"), true
	}
	annotation := statement.Child("annotation")
	if statement.Kind() != "AnnAssign" || statement.Child("target").Name() != attribute || annotation.Kind() != "Subscript" {
		return Node{}, false
	}
	if held := annotation.Child("value").DottedName(); held != "type" && held != "typing.Type" && held != "Type" {
		return Node{}, false
	}

	return annotation.Child("slice"), true
}

func (function Node) AnnotationOf(name string) (Node, bool) {
	for _, parameter := range function.Parameters() {
		if parameter.Name() == name && parameter.Child("annotation").Exists() {
			return parameter.Child("annotation"), true
		}
	}

	return annotated(function.statementsIn(), name)
}

func (function Node) Parameters() []Node {
	var parameters []Node
	for _, parameter := range function.Child("args").Children() {
		if parameter.Kind() == "arg" {
			parameters = append(parameters, parameter)
		}
	}

	return parameters
}

func (class Node) AttributeAnnotation(name string) (Node, bool) {
	if annotation, ok := annotated(class.ChildrenIn("body"), name); ok {
		return annotation, true
	}
	if init := class.Initializer(); init.Exists() {
		return init.storedAnnotation(name)
	}

	return Node{}, false
}

func (function Node) storedAnnotation(attribute string) (Node, bool) {
	if annotation, ok := function.AnnotationOf("self." + attribute); ok {
		return annotation, true
	}
	var stored string
	for _, statement := range function.ChildrenIn("body") {
		targets := statement.ChildrenIn("targets")
		if statement.Kind() == "Assign" && len(targets) == 1 && targets[0].DottedName() == "self."+attribute && statement.Child("value").Kind() == "Name" {
			stored = statement.Child("value").Name()
			break
		}
	}
	if stored == "" {
		return Node{}, false
	}
	for _, statement := range function.statementsIn() {
		for _, written := range statement.writtenNames() {
			if written == stored {
				return Node{}, false
			}
		}
	}

	return function.AnnotationOf(stored)
}

func annotated(statements []Node, dotted string) (Node, bool) {
	for _, statement := range statements {
		if statement.Kind() == "AnnAssign" && statement.Child("target").DottedName() == dotted {
			return statement.Child("annotation"), true
		}
	}

	return Node{}, false
}

func (function Node) statementsIn() []Node {
	var statements []Node
	for _, statement := range function.ChildrenIn("body") {
		for _, inner := range append([]Node{statement}, statement.Descendants()...) {
			if inner.Node().Role == "statement" || inner.IsDefinition() {
				statements = append(statements, inner)
			}
		}
	}

	return statements
}

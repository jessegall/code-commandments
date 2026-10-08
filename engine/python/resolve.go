package python

import (
	"strings"
)

// Callee is the def a call reaches, resolved where the call stands: through a def an enclosing function
// declares, the module's imports (absolute and relative, aliased or not), `self` inside a method, a parameter
// or local annotated with a class, and an attribute of `self` whose class the class body or `__init__`
// declares; a method a class does not declare is looked up in its bases. A call that cannot be resolved is
// never guessed, and an import that names more than one module resolves to none.
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

// calledOnResult is the method a call names on another call's result, `self.guard().kept(…)`: the class the inner
// call's def declares it returns, and the method of that name it declares or inherits.
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

// nestedIn is the def named name that a function enclosing the node declares in its own body: the nearest
// one, as Python looks a name up.
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

// classOf is the class owner stands for where the node sits: `self` in a method, `self.x` whose class the class
// annotates, a parameter or local annotated with a class, or a class named outright.
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

// ClassNamed is the class an annotation or a name spells, read in the module; a string annotation spells it too.
func (p *Program) ClassNamed(spelled Node, module *Module) (Node, bool) {
	if text, ok := spelled.Text(); ok {
		return p.classSpelled(text, module)
	}

	return p.classSpelled(spelled.DottedName(), module)
}

// classSpelled is the class the dotted spelling names in the module.
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

// MethodOf is the def named name that the class declares, or that the first of its bases to declare one does.
func (p *Program) MethodOf(class Node, name string) (Node, bool) {
	return p.methodOf(class, name, map[Node]bool{})
}

func (p *Program) methodOf(class Node, name string, seen map[Node]bool) (Node, bool) {
	if seen[class] {
		return Node{}, false
	}
	seen[class] = true
	for _, member := range class.ChildrenIn("body") {
		if member.IsFunction() && member.Name() == name {
			return member, true
		}
	}
	home := p.homes[class.Node()]
	if home == nil {
		return Node{}, false
	}
	for _, base := range class.ChildrenIn("bases") {
		if parent, ok := p.ClassNamed(base, home); ok {
			if inherited, ok := p.methodOf(parent, name, seen); ok {
				return inherited, true
			}
		}
	}

	return Node{}, false
}

// AnnotationOf is the annotation name carries in the function: as one of its parameters, or as a local it
// declares with one.
func (function Node) AnnotationOf(name string) (Node, bool) {
	for _, parameter := range function.Parameters() {
		if parameter.Name() == name && parameter.Child("annotation").Exists() {
			return parameter.Child("annotation"), true
		}
	}

	return annotated(function.statementsIn(), name)
}

// Parameters is every parameter the function declares, in source order: positional-only, positional, *args,
// keyword-only, **kwargs.
func (function Node) Parameters() []Node {
	var parameters []Node
	for _, parameter := range function.Child("args").Children() {
		if parameter.Kind() == "arg" {
			parameters = append(parameters, parameter)
		}
	}

	return parameters
}

// AttributeAnnotation is the annotation the instance attribute carries: declared in the class body, annotated
// where `__init__` sets it, or taken from the annotated parameter `__init__` stores in it.
func (class Node) AttributeAnnotation(name string) (Node, bool) {
	if annotation, ok := annotated(class.ChildrenIn("body"), name); ok {
		return annotation, true
	}
	if init := class.Initializer(); init.Exists() {
		return init.storedAnnotation(name)
	}

	return Node{}, false
}

// storedAnnotation is the annotation of what the function stores in `self.attribute`: declared there with one,
// or taken from the annotated parameter it assigns straight in its body, when nothing rebinds that parameter.
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

// annotated is the annotation of the first annotated assignment among the statements whose target is the
// dotted name.
func annotated(statements []Node, dotted string) (Node, bool) {
	for _, statement := range statements {
		if statement.Kind() == "AnnAssign" && statement.Child("target").DottedName() == dotted {
			return statement.Child("annotation"), true
		}
	}

	return Node{}, false
}

// statementsIn is every statement of the function's body, nested ones included, in pre-order.
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

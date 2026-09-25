package python

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// Callee is the def a call reaches, resolved where the call stands: through a def an enclosing function
// declares, the module's imports (absolute and relative, aliased or not), `self` inside a method, a parameter
// or local annotated with a class, and an attribute of `self` whose class the class body or `__init__`
// declares; a method a class does not declare is looked up in its bases. A call that cannot be resolved is
// never guessed, and an import that names more than one module resolves to none.
func (p *Program) Callee(call engine.Match) (engine.Match, bool) {
	module := p.ModuleOf(call)
	if module == nil || call.Kind() != "Call" {
		return engine.Match{}, false
	}
	callee := call.Child("func")
	if callee.Kind() == "Name" {
		if nested, ok := nestedIn(callee.Name(), call); ok {
			return nested, true
		}
		found, ok := module.named(callee.Name())

		return found, ok && IsFunction(found)
	}
	dotted := DottedName(callee)
	if dotted == "" {
		return engine.Match{}, false
	}
	at := strings.LastIndex(dotted, ".")
	owner, member := dotted[:at], dotted[at+1:]
	if bound, ok := module.bindings().modules[owner]; ok {
		found, ok := bound.Declared(member)

		return found, ok && IsFunction(found)
	}
	class, ok := p.classOf(owner, call, module)
	if !ok {
		return engine.Match{}, false
	}

	return p.MethodOf(class, member)
}

// nestedIn is the def named name that a function enclosing the node declares in its own body: the nearest
// one, as Python looks a name up.
func nestedIn(name string, node engine.Match) (engine.Match, bool) {
	for scope := EnclosingFunction(node); scope.Exists(); scope = EnclosingFunction(scope.Parent()) {
		for _, statement := range scope.ChildrenIn("body") {
			for _, inner := range append([]engine.Match{statement}, statement.Descendants()...) {
				if IsFunction(inner) && inner.Name() == name && EnclosingFunction(inner.Parent()) == scope {
					return inner, true
				}
			}
		}
	}

	return engine.Match{}, false
}

// classOf is the class owner stands for where the node sits: `self` in a method, `self.x` whose class the class
// annotates, a parameter or local annotated with a class, or a class named outright.
func (p *Program) classOf(owner string, node engine.Match, module *Module) (engine.Match, bool) {
	function := EnclosingFunction(node)
	if owner == "self" {
		class := function.Parent()

		return class, function.Exists() && class.Kind() == "ClassDef"
	}
	path := strings.Split(owner, ".")
	if len(path) == 2 && path[0] == "self" {
		class, ok := p.classOf("self", node, module)
		if !ok {
			return engine.Match{}, false
		}
		annotation, ok := AttributeAnnotation(class, path[1])
		if !ok {
			return engine.Match{}, false
		}

		return p.ClassNamed(annotation, module)
	}
	if function.Exists() {
		if annotation, ok := AnnotationOf(function, owner); ok {
			return p.ClassNamed(annotation, module)
		}
	}
	if strings.Contains(owner, ".") {
		return engine.Match{}, false
	}

	return p.classSpelled(owner, module)
}

// ClassNamed is the class an annotation or a name spells, read in the module; a string annotation spells it too.
func (p *Program) ClassNamed(spelled engine.Match, module *Module) (engine.Match, bool) {
	if text, ok := spelled.Text(); ok {
		return p.classSpelled(text, module)
	}

	return p.classSpelled(DottedName(spelled), module)
}

// classSpelled is the class the dotted spelling names in the module.
func (p *Program) classSpelled(dotted string, module *Module) (engine.Match, bool) {
	owner, name := "", dotted
	if at := strings.LastIndex(dotted, "."); at >= 0 {
		owner, name = dotted[:at], dotted[at+1:]
	}
	var found engine.Match
	var ok bool
	if bound, isModule := module.bindings().modules[owner]; isModule {
		found, ok = bound.Declared(name)
	} else {
		found, ok = module.named(dotted)
	}

	return found, ok && found.Kind() == "ClassDef"
}

// MethodOf is the def named name that the class declares, or that the first of its bases to declare one does.
func (p *Program) MethodOf(class engine.Match, name string) (engine.Match, bool) {
	return p.methodOf(class, name, map[engine.Match]bool{})
}

func (p *Program) methodOf(class engine.Match, name string, seen map[engine.Match]bool) (engine.Match, bool) {
	if seen[class] {
		return engine.Match{}, false
	}
	seen[class] = true
	for _, member := range class.ChildrenIn("body") {
		if IsFunction(member) && member.Name() == name {
			return member, true
		}
	}
	home := p.homes[class.Node()]
	if home == nil {
		return engine.Match{}, false
	}
	for _, base := range class.ChildrenIn("bases") {
		if parent, ok := p.ClassNamed(base, home); ok {
			if inherited, ok := p.methodOf(parent, name, seen); ok {
				return inherited, true
			}
		}
	}

	return engine.Match{}, false
}

// AnnotationOf is the annotation name carries in the function: as one of its parameters, or as a local it
// declares with one.
func AnnotationOf(function engine.Match, name string) (engine.Match, bool) {
	for _, parameter := range Parameters(function) {
		if parameter.Name() == name && parameter.Child("annotation").Exists() {
			return parameter.Child("annotation"), true
		}
	}

	return annotated(statementsIn(function), name)
}

// Parameters is every parameter the function declares, in source order: positional-only, positional, *args,
// keyword-only, **kwargs.
func Parameters(function engine.Match) []engine.Match {
	var parameters []engine.Match
	for _, parameter := range function.Child("args").Children() {
		if parameter.Kind() == "arg" {
			parameters = append(parameters, parameter)
		}
	}

	return parameters
}

// AttributeAnnotation is the annotation the instance attribute carries: declared in the class body, annotated
// where `__init__` sets it, or taken from the annotated parameter `__init__` stores in it.
func AttributeAnnotation(class engine.Match, name string) (engine.Match, bool) {
	if annotation, ok := annotated(class.ChildrenIn("body"), name); ok {
		return annotation, true
	}
	for _, member := range class.ChildrenIn("body") {
		if IsFunction(member) && member.Name() == "__init__" {
			return storedAnnotation(member, name)
		}
	}

	return engine.Match{}, false
}

// storedAnnotation is the annotation of what the function stores in `self.attribute`: declared there with one,
// or taken from the annotated parameter it assigns straight in its body, when nothing rebinds that parameter.
func storedAnnotation(function engine.Match, attribute string) (engine.Match, bool) {
	if annotation, ok := AnnotationOf(function, "self."+attribute); ok {
		return annotation, true
	}
	var stored string
	for _, statement := range function.ChildrenIn("body") {
		targets := statement.ChildrenIn("targets")
		if statement.Kind() == "Assign" && len(targets) == 1 && DottedName(targets[0]) == "self."+attribute && statement.Child("value").Kind() == "Name" {
			stored = statement.Child("value").Name()
			break
		}
	}
	if stored == "" {
		return engine.Match{}, false
	}
	for _, statement := range statementsIn(function) {
		for _, written := range writtenNames(statement) {
			if written == stored {
				return engine.Match{}, false
			}
		}
	}

	return AnnotationOf(function, stored)
}

// annotated is the annotation of the first annotated assignment among the statements whose target is the
// dotted name.
func annotated(statements []engine.Match, dotted string) (engine.Match, bool) {
	for _, statement := range statements {
		if statement.Kind() == "AnnAssign" && DottedName(statement.Child("target")) == dotted {
			return statement.Child("annotation"), true
		}
	}

	return engine.Match{}, false
}

// statementsIn is every statement of the function's body, nested ones included, in pre-order.
func statementsIn(function engine.Match) []engine.Match {
	var statements []engine.Match
	for _, statement := range function.ChildrenIn("body") {
		for _, inner := range append([]engine.Match{statement}, statement.Descendants()...) {
			if inner.Node().Role == "statement" || IsDefinition(inner) {
				statements = append(statements, inner)
			}
		}
	}

	return statements
}

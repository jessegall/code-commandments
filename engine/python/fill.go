package python

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func init() {
	engine.Fills(contract.Python, fill)
}

// fill writes the facts the engine owns on a Python stream (contract/CONTRACT.md, "Who fills it"): each call's
// target, what each name and import refers to, the file each import resolves to, which methods are inherited,
// which expressions are constant, and the class each written type names.
func fill(codebase *engine.Codebase) {
	program := Of(codebase)
	for _, module := range program.Modules() {
		program.fillRefs(module)
		for _, node := range module.Nodes() {
			facts := node.Node()
			if target, ok := program.Callee(node); ok {
				facts.Own().Target = targetOf(target)
			}
			if refers, ok := program.refersTo(node, module); ok {
				facts.Own().Refers = refers
			}
			if node.Kind() == "alias" {
				if reached, ok := module.importedModule(node.Parent(), node); ok {
					facts.Own().Resolves = reached.File.Path
				}
			}
			if node.IsFunction() && program.IsOverride(node) {
				facts.Own().Inherited = true
			}
			if node.isConstant() || program.isEnumCase(node) {
				facts.Own().Constant = true
			}
			if declared := program.resolvedType(facts.Declared, module); declared != facts.Declared {
				facts.Own().Declared = declared
			}
			if returns := program.resolvedType(facts.Returns, module); returns != facts.Returns {
				facts.Own().Returns = returns
			}
		}
	}
}

// targetOf is the contract's target for a def: its symbol, its name, and the class that declares it.
func targetOf(def Node) *contract.Target {
	target := &contract.Target{Symbol: def.Node().Symbol, Name: def.Name()}
	if class := def.Parent(); class.Kind() == "ClassDef" {
		target.Type = class.Node().Symbol
	}

	return target
}

// IsOverride says whether a method, a def in a class body, overrides one a base class of the codebase declares.
func (p *Program) IsOverride(method Node) bool {
	class := method.Parent()
	home := p.homes[class.Node()]
	if class.Kind() != "ClassDef" || home == nil {
		return false
	}
	for _, base := range class.ChildrenIn("bases") {
		if parent, ok := p.ClassNamed(base, home); ok {
			if _, ok := p.MethodOf(parent, method.Name()); ok {
				return true
			}
		}
	}

	return false
}

// refersTo is the symbol id an import alias or a name at the top of its module names: a def or class of the
// codebase, or what an import binds, whether or not the scan holds it. A name a function binds for itself
// refers to its own local, which has no id.
func (p *Program) refersTo(node Node, module *Module) (string, bool) {
	switch node.Kind() {
	case "alias":
		return module.importedId(node)
	case "Name":
		if nested, ok := nestedIn(node.Name(), node); ok {
			return nested.Node().Symbol, nested.Node().Symbol != ""
		}
		if p.isLocal(node.Name(), node) {
			return "", false
		}
		if declared, ok := module.named(node.Name()); ok {
			return declared.Node().Symbol, declared.Node().Symbol != ""
		}

		return module.importedName(node.Name())
	}

	return "", false
}

// importedId is the dotted id an import alias binds: the module it imports, or the member a from-import names.
// A relative import names one only when it reaches a module of the codebase.
func (m *Module) importedId(alias Node) (string, bool) {
	statement := alias.Parent()
	if statement.Kind() == "Import" {
		if _, renamed := alias.renamedTo(); renamed {
			return alias.Name(), true
		}

		return strings.Split(alias.Name(), ".")[0], true
	}
	if alias.Name() == "*" {
		return "", false
	}
	if statement.Level() == 0 {
		return statement.Name() + "." + alias.Name(), true
	}
	source, ok := m.program.moduleNamed(statement.Name(), m, statement.Level())
	if !ok {
		return "", false
	}

	return source.Name + "." + alias.Name(), true
}

// importedName is the dotted id the name, or the first part of a dotted name, is bound to by an import of the
// module: `Decimal` after `from decimal import Decimal`, `decimal.Decimal` after `import decimal`.
func (m *Module) importedName(dotted string) (string, bool) {
	head, rest, qualified := strings.Cut(dotted, ".")
	id, ok := m.bindings().ids[head]
	if !ok {
		return "", false
	}
	if qualified {
		return id + "." + rest, true
	}

	return id, true
}

// renamedTo is the name an import alias renames what it imports to, if it renames it.
func (alias Node) renamedTo() (string, bool) {
	if extras := alias.Node().Extras; extras != nil && extras.Python != nil && extras.Python.As != "" {
		return extras.Python.As, true
	}

	return "", false
}

// isLocal says whether a function around the node binds the name for itself: as a parameter, or by assigning it.
func (p *Program) isLocal(name string, node Node) bool {
	for function := node.EnclosingFunction(); function.Exists(); function = function.Parent().EnclosingFunction() {
		if p.locals(function)[name] {
			return true
		}
	}

	return false
}

// locals is every name the function binds for itself, read once.
func (p *Program) locals(function Node) map[string]bool {
	if held, ok := p.bound.Load(function.Node()); ok {
		return held.(map[string]bool)
	}
	names := map[string]bool{}
	for _, parameter := range function.Parameters() {
		names[parameter.Name()] = true
	}
	for _, statement := range function.statementsIn() {
		for _, written := range statement.writtenNames() {
			names[written] = true
		}
	}
	p.bound.Store(function.Node(), names)

	return names
}

// isConstant says whether the expression has a value before the program runs: a literal, or arithmetic on literals.
func (node Node) isConstant() bool {
	switch node.Kind() {
	case "Constant":
		return true
	case "UnaryOp":
		return node.Child("operand").isConstant()
	case "BinOp":
		return node.Child("left").isConstant() && node.Child("right").isConstant()
	}

	return false
}

// resolvedType is the written type with the class each named part spells named, read in the module: a class of
// the codebase by its symbol id, an imported one by the id its import binds. A name that resolves to neither, such
// as a builtin, stays as written. The written type is never changed, since decoded types are shared: a type that
// resolves differently is a new one, and one that resolves as written is itself.
func (p *Program) resolvedType(written *contract.Type, module *Module) *contract.Type {
	if written == nil {
		return nil
	}
	resolved := *written
	if written.Kind == "named" && written.Name != "" {
		if class, ok := p.classSpelled(written.Name, module); ok {
			resolved.Name = class.Node().Symbol
		} else if imported, ok := module.importedName(written.Name); ok {
			resolved.Name = imported
		}
	}
	resolved.Args = p.resolvedTypes(written.Args, module)
	resolved.Members = p.resolvedTypes(written.Members, module)
	if resolved.Name == written.Name && slices.Equal(resolved.Args, written.Args) && slices.Equal(resolved.Members, written.Members) {
		return written
	}

	return &resolved
}

// resolvedTypes are the types resolved, the slice itself when none of them changed.
func (p *Program) resolvedTypes(written []*contract.Type, module *Module) []*contract.Type {
	resolved := make([]*contract.Type, len(written))
	for at, inner := range written {
		resolved[at] = p.resolvedType(inner, module)
	}
	if slices.Equal(resolved, written) {
		return written
	}

	return resolved
}

// fillRefs writes, on each docstring, the Sphinx cross-references it makes: each as written, whether the codebase
// owns the package it names, and the symbol it reaches when the codebase resolves it.
func (p *Program) fillRefs(module *Module) {
	for at := range module.File.File.Comments {
		comment := &module.File.File.Comments[at]
		if comment.Kind != "doc" || comment.Attached == nil {
			continue
		}
		docstring, ok := Node{module.File.Match(*comment.Attached)}.Docstring()
		if !ok {
			continue
		}
		for _, reference := range References(docstring) {
			owned := p.OwnsPackage(strings.Split(reference, ".")[0])
			ref := contract.Ref{Text: reference, OwnedHere: &owned}
			if p.Resolves(reference) {
				ref.Symbol = reference
			}
			comment.Refs = append(comment.Refs, ref)
		}
	}
}

// isEnumCase says whether the expression reads a case of an enum the program declares: `Colour.RED`.
func (p *Program) isEnumCase(node Node) bool {
	return node.Kind() == "Attribute" && node.Child("value").Kind() == "Name" && p.IsEnum(node.Child("value").Name())
}

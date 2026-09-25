package python

import (
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
		for _, node := range module.Nodes() {
			facts := node.Node()
			if target, ok := program.Callee(node); ok {
				facts.Target = targetOf(target)
			}
			if refers, ok := program.refersTo(node, module); ok {
				facts.Refers = refers
			}
			if node.Kind() == "alias" {
				if reached, ok := module.importedModule(node.Parent(), node); ok {
					facts.Resolves = reached.File.Path
				}
			}
			facts.Inherited = IsFunction(node) && program.IsOverride(node)
			facts.Constant = isConstant(node)
			program.resolveType(facts.Declared, module)
			program.resolveType(facts.Returns, module)
		}
	}
}

// targetOf is the contract's target for a def: its symbol, its name, and the class that declares it.
func targetOf(def engine.Match) *contract.Target {
	target := &contract.Target{Symbol: def.Node().Symbol, Name: def.Name()}
	if class := def.Parent(); class.Kind() == "ClassDef" {
		target.Type = class.Node().Symbol
	}

	return target
}

// IsOverride says whether a method, a def in a class body, overrides one a base class of the codebase declares.
func (p *Program) IsOverride(method engine.Match) bool {
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
func (p *Program) refersTo(node engine.Match, module *Module) (string, bool) {
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
func (m *Module) importedId(alias engine.Match) (string, bool) {
	statement := alias.Parent()
	if statement.Kind() == "Import" {
		if _, renamed := renamedTo(alias); renamed {
			return alias.Name(), true
		}

		return strings.Split(alias.Name(), ".")[0], true
	}
	if alias.Name() == "*" {
		return "", false
	}
	if Level(statement) == 0 {
		return statement.Name() + "." + alias.Name(), true
	}
	source, ok := m.program.moduleNamed(statement.Name(), m, Level(statement))
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
func renamedTo(alias engine.Match) (string, bool) {
	if extras := alias.Node().Extras; extras != nil && extras.Python != nil && extras.Python.As != "" {
		return extras.Python.As, true
	}

	return "", false
}

// isLocal says whether a function around the node binds the name for itself: as a parameter, or by assigning it.
func (p *Program) isLocal(name string, node engine.Match) bool {
	for function := EnclosingFunction(node); function.Exists(); function = EnclosingFunction(function.Parent()) {
		if p.locals(function)[name] {
			return true
		}
	}

	return false
}

// locals is every name the function binds for itself, read once.
func (p *Program) locals(function engine.Match) map[string]bool {
	if held, ok := p.bound.Load(function.Node()); ok {
		return held.(map[string]bool)
	}
	names := map[string]bool{}
	for _, parameter := range Parameters(function) {
		names[parameter.Name()] = true
	}
	for _, statement := range statementsIn(function) {
		for _, written := range writtenNames(statement) {
			names[written] = true
		}
	}
	p.bound.Store(function.Node(), names)

	return names
}

// isConstant says whether the expression has a value before the program runs: a literal, or arithmetic on literals.
func isConstant(node engine.Match) bool {
	switch node.Kind() {
	case "Constant":
		return true
	case "UnaryOp":
		return isConstant(node.Child("operand"))
	case "BinOp":
		return isConstant(node.Child("left")) && isConstant(node.Child("right"))
	}

	return false
}

// resolveType names the class each named part of a written type spells, read in the module: a class of the
// codebase by its symbol id, an imported one by the id its import binds. A name that resolves to neither, such as
// a builtin, stays as written.
func (p *Program) resolveType(written *contract.Type, module *Module) {
	if written == nil {
		return
	}
	if written.Kind == "named" && written.Name != "" {
		if class, ok := p.classSpelled(written.Name, module); ok {
			written.Name = class.Node().Symbol
		} else if imported, ok := module.importedName(written.Name); ok {
			written.Name = imported
		}
	}
	for _, inner := range append(append([]*contract.Type{}, written.Args...), written.Members...) {
		p.resolveType(inner, module)
	}
}

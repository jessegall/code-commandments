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

func fill(codebase *engine.Codebase, changed []string) {
	program := Of(codebase)
	for _, module := range program.filling(changed) {
		program.fillRefs(module)
		for _, node := range module.Nodes() {
			facts := node.Node()
			if target, ok := program.Callee(node); ok {
				facts.Own().Target = targetOf(target)
			} else if outside, ok := module.outsideTarget(node); ok {
				facts.Own().Target = outside
			}
			if refers, ok := program.refersTo(node, module); ok {
				facts.Own().Refers = refers
			}
			if reached, resolved := resolvedImport(module, node); resolved {
				facts.Own().Resolves = reached
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

func (p *Program) filling(changed []string) []*Module {
	if len(changed) == 0 {
		return p.Modules()
	}

	importers := map[string][]*Module{}
	for _, module := range p.Modules() {
		for _, node := range module.Nodes() {
			if reached, resolved := resolvedImport(module, node); resolved {
				importers[reached] = append(importers[reached], module)
			}
		}
	}

	filling := map[string]*Module{}
	var walk func(path string)
	walk = func(path string) {
		if _, seen := filling[path]; seen {
			return
		}

		module, held := p.byPath[path]
		if !held {
			return
		}

		filling[path] = module
		for _, importer := range importers[path] {
			walk(importer.File.Path)
		}
	}

	for _, path := range changed {
		walk(path)
	}

	var modules []*Module
	for _, module := range p.Modules() {
		if _, filled := filling[module.File.Path]; filled {
			modules = append(modules, module)
		}
	}

	return modules
}

func resolvedImport(module *Module, node Node) (string, bool) {
	if node.Kind() != "alias" {
		return "", false
	}
	reached, held := module.importedModule(node.Parent(), node)
	if !held {
		return "", false
	}

	return reached.File.Path, true
}

func (m *Module) outsideTarget(call Node) (*contract.Target, bool) {
	if call.Kind() != "Call" {
		return nil, false
	}
	id, imported := m.importedName(call.Child("func").DottedName())
	at := strings.LastIndex(id, ".")
	if !imported || at < 0 {
		return nil, false
	}
	if _, inside := m.program.ModuleCalled(id[:at]); inside {
		return nil, false
	}

	return &contract.Target{Symbol: id, Name: id[at+1:]}, true
}

func targetOf(def Node) *contract.Target {
	target := &contract.Target{Symbol: def.Node().Symbol, Name: def.Name()}
	if class := def.Parent(); class.Kind() == "ClassDef" {
		target.Type = class.Node().Symbol
	}

	return target
}

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

func (p *Program) DispatchedCallersOf(method Node) []Node {
	calls := append(slices.Clone(p.CallersOf(method)), p.HeldCallersOf(method)...)
	for _, overridden := range p.overriddenBy(method) {
		calls = append(calls, p.CallersOf(overridden)...)
	}

	return calls
}

func (p *Program) overriddenBy(method Node) []Node {
	var found []Node
	seen := map[string]bool{}
	classes := []Node{method.Parent()}
	for len(classes) > 0 {
		class := classes[0]
		classes = classes[1:]
		home := p.homes[class.Node()]
		if class.Kind() != "ClassDef" || home == nil || seen[class.Location()] {
			continue
		}
		seen[class.Location()] = true
		for _, base := range class.ChildrenIn("bases") {
			parent, ok := p.ClassNamed(base, home)
			if !ok {
				continue
			}
			if declared, ok := p.MethodOf(parent, method.Name()); ok {
				found = append(found, declared)
			}
			classes = append(classes, parent)
		}
	}

	return found
}

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

func (alias Node) renamedTo() (string, bool) {
	if extras := alias.Node().Extras; extras != nil && extras.Python != nil && extras.Python.As != "" {
		return extras.Python.As, true
	}

	return "", false
}

func (p *Program) isLocal(name string, node Node) bool {
	for function := node.EnclosingFunction(); function.Exists(); function = function.Parent().EnclosingFunction() {
		if p.locals(function)[name] {
			return true
		}
	}

	return false
}

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

func (p *Program) isEnumCase(node Node) bool {
	return node.Kind() == "Attribute" && node.Child("value").Kind() == "Name" && p.IsEnum(node.Child("value"))
}

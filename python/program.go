// Package python is the Python engine: the program a Python stream describes — its modules, what each one
// binds, the classes and functions they declare — and the facts the engine fills on a Python stream.
package python

import (
	"path/filepath"
	"strings"
	"sync"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

// Program is the Python part of a codebase: every module, by path and by dotted name.
type Program struct {
	modules []*Module
	byName  map[string][]*Module
	byPath  map[string]*Module
	homes   map[*contract.Node]*Module
	bound   sync.Map
	classes classes
	calls   callGraph
	flow    attributeFlow
}

// Module is one Python file and the dotted name Python imports it by.
type Module struct {
	File    *engine.File
	Name    string
	program *Program
	once    sync.Once
	binds   map[string]bool
	bound   sync.Once
	imports *bindings
}

// bindings are the names a module's imports bind: those bound to a module, and those bound to a function or
// class another module declares.
type bindings struct {
	modules map[string]*Module
	members map[string]Node
	ids     map[string]string
}

// Of is the Python program of the codebase, built once.
func Of(codebase *engine.Codebase) *Program {
	return engine.Analysis(codebase, "python.program", build)
}

func build(codebase *engine.Codebase) *Program {
	program := &Program{byName: map[string][]*Module{}, byPath: map[string]*Module{}, homes: map[*contract.Node]*Module{}}
	for _, file := range codebase.Of(contract.Python).Files() {
		module := &Module{File: file, Name: file.Module, program: program}
		program.modules = append(program.modules, module)
		program.byName[module.Name] = append(program.byName[module.Name], module)
		program.byPath[file.Path] = module
		for _, node := range file.Nodes() {
			if node.Kind == "ClassDef" {
				program.homes[node] = module
			}
		}
	}

	return program
}

// Modules is every module, in the order the stream wrote them.
func (p *Program) Modules() []*Module {
	return p.modules
}

// ModuleOf is the module a node sits in.
func (p *Program) ModuleOf(node Node) *Module {
	return p.byPath[node.File()]
}

// ModuleCalled is the one module the codebase holds under the full dotted name; none when it holds none, or
// more than one.
func (p *Program) ModuleCalled(dotted string) (*Module, bool) {
	found := p.byName[dotted]
	if len(found) != 1 {
		return nil, false
	}

	return found[0], true
}

// OwnsPackage says whether the codebase holds the top-level module or package, so that a reference into it
// can be checked from here.
func (p *Program) OwnsPackage(name string) bool {
	_, ok := p.ModuleCalled(name)

	return ok
}

// Resolves says whether the dotted name names something the codebase holds: a module, or a name bound at the
// top of the deepest module its prefix names. What follows that name is not checked.
func (p *Program) Resolves(dotted string) bool {
	parts := strings.Split(dotted, ".")
	for depth := len(parts); depth >= 1; depth-- {
		if module, ok := p.ModuleCalled(strings.Join(parts[:depth], ".")); ok {
			return depth == len(parts) || module.Binds(parts[depth])
		}
	}

	return false
}

// moduleNamed is the one module the dotted name reaches from the module: level dots up from its package for a
// relative import. None when no module or more than one has that name.
func (p *Program) moduleNamed(dotted string, from *Module, level int) (*Module, bool) {
	if level == 0 {
		return p.ModuleCalled(dotted)
	}
	folder := from.File.Path
	for range level {
		folder = filepath.Dir(folder)
	}
	if dotted != "" {
		folder = filepath.Join(folder, strings.ReplaceAll(dotted, ".", "/"))
	}
	var found []*Module
	for _, module := range p.modules {
		if module.File.Path == folder+".py" || module.File.Path == filepath.Join(folder, "__init__.py") {
			found = append(found, module)
		}
	}
	if len(found) != 1 {
		return nil, false
	}

	return found[0], true
}

// Root is the module's root node.
func (m *Module) Root() Node {
	return Node{m.File.Match(0)}
}

// Nodes is every node of the module, in pre-order.
func (m *Module) Nodes() []Node {
	root := m.Root()

	return append([]Node{root}, root.Descendants()...)
}

// Declared is the function or class the module's own body declares under the name.
func (m *Module) Declared(name string) (Node, bool) {
	for _, statement := range m.Root().ChildrenIn("body") {
		if statement.IsDefinition() && statement.Name() == name {
			return statement, true
		}
	}

	return Node{}, false
}

// Binds says whether the module binds the name at its top: a declaration, an import, or an assignment
// outside every function and class.
func (m *Module) Binds(name string) bool {
	m.once.Do(func() {
		m.binds = map[string]bool{}
		for _, node := range m.Nodes() {
			if node.insideDefinition() {
				continue
			}
			for _, bound := range append(node.declaredNames(), node.writtenNames()...) {
				m.binds[bound] = true
			}
		}
	})

	return m.binds[name]
}

// bindings is what the module's imports bind, read once.
func (m *Module) bindings() *bindings {
	m.bound.Do(m.bind)

	return m.imports
}

func (m *Module) bind() {
	bound := &bindings{modules: map[string]*Module{}, members: map[string]Node{}, ids: map[string]string{}}
	for _, statement := range m.Nodes() {
		if statement.IsImport() {
			for _, alias := range statement.ChildrenIn("names") {
				if id, ok := m.importedId(alias); ok {
					bound.ids[alias.boundAs()] = id
				}
			}
		}
		switch statement.Kind() {
		case "Import":
			for _, alias := range statement.ChildrenIn("names") {
				if found, ok := m.program.moduleNamed(alias.Name(), m, 0); ok {
					key := alias.boundAs()
					if key == strings.Split(alias.Name(), ".")[0] {
						key = alias.Name()
					}
					bound.modules[key] = found
				}
			}
		case "ImportFrom":
			source, sourced := m.program.moduleNamed(statement.Name(), m, statement.Level())
			for _, alias := range statement.ChildrenIn("names") {
				if sourced {
					if declared, ok := source.Declared(alias.Name()); ok {
						bound.members[alias.boundAs()] = declared
						continue
					}
				}
				submodule := strings.TrimPrefix(statement.Name()+"."+alias.Name(), ".")
				if found, ok := m.program.moduleNamed(submodule, m, statement.Level()); ok {
					bound.modules[alias.boundAs()] = found
				}
			}
		}
	}
	m.imports = bound
}

// Imports is every module of the codebase an import in the module reaches, with the import that reaches it: the
// module imported, the one a from-import imports a name out of, or the submodule it names.
func (m *Module) Imports() []Import {
	var reached []Import
	for _, statement := range m.Nodes() {
		if !statement.IsImport() {
			continue
		}
		for _, alias := range statement.ChildrenIn("names") {
			if found, ok := m.importedModule(statement, alias); ok {
				reached = append(reached, Import{Statement: statement, Alias: alias, Module: found})
			}
		}
	}

	return reached
}

// Import is one import reaching a module of the codebase.
type Import struct {
	Statement Node
	Alias     Node
	Module    *Module
}

// importedModule is the module the import's alias reaches.
func (m *Module) importedModule(statement, alias Node) (*Module, bool) {
	switch statement.Kind() {
	case "Import":
		return m.program.moduleNamed(alias.Name(), m, 0)
	case "ImportFrom":
		source, ok := m.program.moduleNamed(statement.Name(), m, statement.Level())
		if ok && source.Binds(alias.Name()) {
			return source, true
		}
		if submodule, found := m.program.moduleNamed(strings.TrimPrefix(statement.Name()+"."+alias.Name(), "."), m, statement.Level()); found {
			return submodule, true
		}

		return source, ok
	}

	return nil, false
}

// named is what the name is bound to at the top of the module: a function or class it declares, or one it
// imports.
func (m *Module) named(name string) (Node, bool) {
	if imported, ok := m.bindings().members[name]; ok {
		return imported, true
	}

	return m.Declared(name)
}

package vue

import (
	"path/filepath"
	"slices"
	"sort"
	"strings"

	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/typescript"
)

// Modules finds the script of a module file: a component's scripts, or a TypeScript file whole.
type Modules func(path string) (typescript.Module, bool)

func ModulesIn(codebase *engine.Codebase, fallback Modules) Modules {
	return func(path string) (typescript.Module, bool) {
		for _, file := range codebase.Files() {
			if file.Path == path {
				return ModuleOf(file.Match(file.Root.ID))
			}
		}
		if fallback == nil {
			return typescript.Module{}, false
		}

		return fallback(path)
	}
}

func ModuleOf(root engine.Match) (typescript.Module, bool) {
	switch root.Kind() {
	case "Component":
		return Component{root}.Module(), true
	case "SourceFile":
		return typescript.ModuleOf(root.ChildrenIn("statements")), true
	}

	return typescript.Module{}, false
}

func (c Component) PropTypes() typescript.Fields {
	module := c.Module()
	call, declares := module.Call("defineProps")
	if !declares {
		wrapped, ok := module.Call("withDefaults")
		if !ok || len(wrapped.Arguments) == 0 {
			return typescript.Fields{}
		}
		call, declares = typescript.CallOf(wrapped.Arguments[0])
		declares = declares && call.Callee == "defineProps"
	}
	if !declares || len(call.TypeArguments) == 0 {
		return typescript.Fields{}
	}

	return call.TypeArguments[0].FieldsWith(module.TypeFields)
}

func (c Component) TemplateElements() []Element {
	var elements []Element
	for _, child := range c.Template().Children() {
		if child.Kind() == "Element" {
			elements = append(elements, Element{child})
		}
	}

	return elements
}

// componentPrint is what a component's surface looks like: its root's shape, and the fields it reads off each prop.
type componentPrint struct {
	path   string
	name   string
	shape  string
	fields []propFields
}

// propFields is a prop and the fields a template reads off it.
type propFields struct {
	prefix string
	leaves []string
}

// ComponentReuse is an existing component a subtree could be replaced by, and what each of its props binds to.
type ComponentReuse struct {
	Path     string
	Name     string
	Bindings []Binding
}

// Binding is a prop and the expression the call site binds it to.
type Binding struct {
	Prop       string
	Expression string
}

// ComponentLibrary is the components a codebase already has, fingerprinted so a subtree shaped like one reuses it.
type ComponentLibrary struct {
	components []componentPrint
}

func LibraryOf(components []Component) *ComponentLibrary {
	library := &ComponentLibrary{}
	for _, component := range components {
		library.Register(component)
	}

	return library
}

func (l *ComponentLibrary) Clone() *ComponentLibrary {
	return &ComponentLibrary{components: slices.Clone(l.components)}
}

func (l *ComponentLibrary) Register(component Component) {
	l.RegisterAt(component, component.File())
}

func (l *ComponentLibrary) RegisterAt(component Component, path string) {
	elements := component.TemplateElements()
	if len(elements) != 1 {
		return
	}
	root := elements[0]
	props := component.PropTypes()
	var fields []propFields
	for _, byPrefix := range fieldsByPrefix(ChainsIn(root)) {
		if _, declared := props.Get(byPrefix.prefix); declared && len(byPrefix.leaves) >= 2 {
			fields = append(fields, byPrefix)
		}
	}
	if len(fields) == 0 {
		return
	}
	l.components = append(l.components, componentPrint{path: path, name: strings.TrimSuffix(filepath.Base(path), ".vue"), shape: root.ShapeHash(), fields: fields})
}

func (l *ComponentLibrary) Match(x *Extraction) (ComponentReuse, bool) {
	shape := x.Element.ShapeHash()
	blockFields := fieldsByPrefix(ChainsIn(x.Element))
	for _, component := range l.components {
		if component.shape != shape || component.path == x.Path() {
			continue
		}
		if bindings, ok := bind(component.fields, blockFields); ok {
			return ComponentReuse{Path: component.path, Name: component.name, Bindings: bindings}, true
		}
	}

	return ComponentReuse{}, false
}

func bind(componentFields, blockFields []propFields) ([]Binding, bool) {
	var bindings []Binding
	used := map[string]bool{}
	for _, prop := range componentFields {
		match := ""
		for _, object := range blockFields {
			if used[object.prefix] || !sameSet(prop.leaves, object.leaves) {
				continue
			}
			match = object.prefix

			break
		}
		if match == "" {
			return nil, false
		}
		bindings = append(bindings, Binding{Prop: prop.prefix, Expression: match})
		used[match] = true
	}

	return bindings, true
}

func sameSet(a, b []string) bool {
	left, right := slices.Clone(a), slices.Clone(b)
	sort.Strings(left)
	sort.Strings(right)

	return slices.Equal(left, right)
}

func fieldsByPrefix(chains [][]string) []propFields {
	var byPrefix []propFields
	for _, chain := range chains {
		if len(chain) < 2 {
			continue
		}
		prefix, leaf := strings.Join(chain[:len(chain)-1], "."), chain[len(chain)-1]
		found := false
		for index := range byPrefix {
			if byPrefix[index].prefix != prefix {
				continue
			}
			found = true
			if !slices.Contains(byPrefix[index].leaves, leaf) {
				byPrefix[index].leaves = append(byPrefix[index].leaves, leaf)
			}
		}
		if !found {
			byPrefix = append(byPrefix, propFields{prefix: prefix, leaves: []string{leaf}})
		}
	}

	return byPrefix
}

func ChainsIn(element Element) [][]string {
	var chains [][]string
	for _, each := range append([]Element{element}, element.DescendantElements()...) {
		chains = append(chains, each.Chains()...)
	}

	return chains
}

// componentUsage is a parent component rendering a child, and what it binds each prop to.
type componentUsage struct {
	parent   Component
	bindings map[string]Directive
}

// ComponentGraph is who renders each component, with what props.
type ComponentGraph struct {
	incoming map[string][]componentUsage
}

func GraphOf(components []Component) *ComponentGraph {
	graph := &ComponentGraph{incoming: map[string][]componentUsage{}}
	for _, parent := range components {
		for _, root := range parent.TemplateElements() {
			for _, element := range append([]Element{root}, root.DescendantElements()...) {
				bindings := element.Bindings()
				if !element.IsComponent() || element.Resolves() == "" || len(bindings) == 0 {
					continue
				}
				graph.incoming[element.Resolves()] = append(graph.incoming[element.Resolves()], componentUsage{parent: parent, bindings: bindings})
			}
		}
	}

	return graph
}

// PropTypes traces a prop's type up the render tree when the component itself leaves it open.
type PropTypes struct {
	graph   *ComponentGraph
	modules Modules
}

func PropTypesOver(graph *ComponentGraph, modules Modules) PropTypes {
	return PropTypes{graph: graph, modules: modules}
}

func (p PropTypes) TypeOf(component Component, prop string) (typescript.TypeNode, bool) {
	return p.typeOf(component, prop, nil)
}

func (p PropTypes) typeOf(component Component, prop string, seen []string) (typescript.TypeNode, bool) {
	key := component.File() + "#" + prop
	if slices.Contains(seen, key) {
		return nil, false
	}
	seen = append(seen, key)
	if local, ok := component.PropTypes().Get(prop); ok && local.Render() != "unknown" {
		return local, true
	}
	if p.graph == nil {
		return nil, false
	}
	for _, usage := range p.graph.incoming[component.File()] {
		binding, binds := usage.bindings[prop]
		if !binds {
			continue
		}
		if typed, ok := p.expressionType(typescript.Of(binding.Value()), usage.parent, seen); ok {
			return typed, true
		}
	}

	return nil, false
}

func (p PropTypes) expressionType(expression typescript.Node, scope Component, seen []string) (typescript.TypeNode, bool) {
	chain, ok := expression.Chain()
	if !ok {
		return expression.InferType()
	}
	typed, ok := p.nameType(scope, chain[0], seen)
	if !ok {
		return nil, false
	}

	return AccessType(typed, chain[1:]), true
}

func AccessType(typed typescript.TypeNode, segments []string) typescript.TypeNode {
	for _, segment := range segments {
		typed = typescript.IndexedAccessType{Object: typed, Index: typescript.LiteralType{Raw: "'" + segment + "'"}}
	}

	return typed
}

func (p PropTypes) nameType(scope Component, name string, seen []string) (typescript.TypeNode, bool) {
	script := scope.Module()
	if typed, ok := scope.PropTypes().Get(name); ok {
		return typed, true
	}
	if typed, ok := script.DeclaredType(name); ok {
		return typed, true
	}
	if typed, ok := ComposableType(script, name, p.modules); ok {
		return typed, true
	}

	return p.typeOf(scope, name, seen)
}

func ComposableType(script typescript.Module, name string, modules Modules) (typescript.TypeNode, bool) {
	composable, ok := script.DestructuredCall(name)
	if !ok || modules == nil {
		return nil, false
	}
	imported, ok := script.ImportOf(composable)
	if !ok || imported.Resolves == "" {
		return nil, false
	}
	module, ok := modules(imported.Resolves)
	if !ok {
		return nil, false
	}
	returnType, declares := module.ReturnTypeName(composable)
	if !declares {
		return module.InferredReturnFields(composable).Get(name)
	}
	var fields typescript.Fields
	if object, isObject := returnType.(typescript.ObjectType); isObject {
		fields = object.Fields()
	} else {
		fields = TypeFieldsFrom(returnType.Render(), imported.Resolves, module, modules)
	}
	typed, ok := fields.Get(name)
	if !ok {
		return nil, false
	}

	return typed.UnwrapRef(), true
}

func TypeFieldsFrom(typeName, file string, script typescript.Module, modules Modules) typescript.Fields {
	return resolveTypeFields(typeName, file, script, modules, nil)
}

func resolveTypeFields(typeName, file string, script typescript.Module, modules Modules, seen []string) typescript.Fields {
	if slices.Contains(seen, file) {
		return typescript.Fields{}
	}
	seen = append(seen, file)
	if local := script.TypeFields(typeName); len(local.Names) > 0 {
		return local
	}
	files := script.ReExports()
	if imported, ok := script.ImportOf(typeName); ok && imported.Resolves != "" {
		files = append([]string{imported.Resolves}, files...)
	}
	for _, path := range files {
		module, ok := modules(path)
		if !ok {
			continue
		}
		if fields := resolveTypeFields(typeName, path, module, modules, seen); len(fields.Names) > 0 {
			return fields
		}
	}

	return typescript.Fields{}
}

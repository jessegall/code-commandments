package vue

import (
	"path/filepath"
	"slices"
	"sort"
	"strings"

	"github.com/jessegall/code-commandments/engine/typescript"
)

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

// LibraryOf fingerprints each component.
func LibraryOf(components []*Sfc) *ComponentLibrary {
	library := &ComponentLibrary{}
	for _, sfc := range components {
		if print, ok := printOf(sfc); ok {
			library.components = append(library.components, print)
		}
	}

	return library
}

// Clone is a library holding what this one holds, that can grow without growing this one.
func (l *ComponentLibrary) Clone() *ComponentLibrary {
	return &ComponentLibrary{components: slices.Clone(l.components)}
}

// Register fingerprints a component drafted during the run, so a later subtree may reuse it.
func (l *ComponentLibrary) Register(path, source string) {
	if print, ok := printOf(ParseSfc(source, path)); ok {
		l.components = append(l.components, print)
	}
}

func printOf(sfc *Sfc) (componentPrint, bool) {
	elements := sfc.Template.Elements()
	if len(elements) != 1 {
		return componentPrint{}, false
	}
	root := elements[0]
	props := ReadScript(sfc.ScriptContent()).PropTypes()
	var fields []propFields
	for _, byPrefix := range fieldsByPrefix(markupChains(root)) {
		if _, declared := props.Get(byPrefix.prefix); declared && len(byPrefix.leaves) >= 2 {
			fields = append(fields, byPrefix)
		}
	}
	if len(fields) == 0 {
		return componentPrint{}, false
	}

	return componentPrint{path: sfc.Path, name: strings.TrimSuffix(filepath.Base(sfc.Path), ".vue"), shape: root.ShapeSignature(), fields: fields}, true
}

// Match is the component a subtree could be replaced by: the same shape, and a prop for each object it reads the
// same fields off.
func (l *ComponentLibrary) Match(x *Extraction) (ComponentReuse, bool) {
	shape := x.Node.ShapeSignature()
	blockFields := fieldsByPrefix(markupChains(x.Node))
	for _, component := range l.components {
		if component.shape != shape || component.path == x.Sfc.Path {
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

// fieldsByPrefix is each chain's object and the fields read off it, in the order first read.
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

// markupChains is every chain the node and the tags below it read.
func markupChains(node *Markup) [][]string {
	var chains [][]string
	for _, element := range append([]*Markup{node}, node.Descendants()...) {
		for _, expression := range element.Expressions() {
			chains = append(chains, expression.Chains()...)
		}
	}

	return chains
}

// componentUsage is a parent component rendering a child, and what it binds each prop to.
type componentUsage struct {
	parent   *Sfc
	bindings []NamedExpression
}

// ComponentGraph is who renders each component, with what props.
type ComponentGraph struct {
	incoming map[string][]componentUsage
}

// GraphOf reads who renders whom from each component's imports.
func GraphOf(components []*Sfc) *ComponentGraph {
	graph := &ComponentGraph{incoming: map[string][]componentUsage{}}
	for _, parent := range components {
		script := ReadScript(parent.ScriptContent())
		resolver := ResolverFor(parent.Path)
		for _, element := range parent.Template.Descendants() {
			if !element.IsComponent() {
				continue
			}
			specifier, imported := script.ImportSpecifier(element.Tag)
			bindings := element.PropBindings()
			if !imported || len(bindings) == 0 {
				continue
			}
			if child, ok := resolver.Resolve(parent.Path, specifier); ok {
				graph.incoming[child] = append(graph.incoming[child], componentUsage{parent: parent, bindings: bindings})
			}
		}
	}

	return graph
}

func (g *ComponentGraph) usagesOf(file string) []componentUsage {
	if real, ok := realFile(file); ok {
		return g.incoming[real]
	}

	return g.incoming[file]
}

// PropTypes traces a prop's type up the render tree when the component itself leaves it open.
type PropTypes struct {
	graph *ComponentGraph
}

// PropTypesOver traces through the graph.
func PropTypesOver(graph *ComponentGraph) PropTypes {
	return PropTypes{graph: graph}
}

// TypeOf is a component's prop's type: as it declares it, else as a parent binds it.
func (p PropTypes) TypeOf(component *Sfc, prop string) (string, bool) {
	return p.typeOf(component, prop, nil)
}

func (p PropTypes) typeOf(component *Sfc, prop string, seen []string) (string, bool) {
	key := component.Path + "#" + prop
	if slices.Contains(seen, key) {
		return "", false
	}
	seen = append(seen, key)
	if local, ok := ReadScript(component.ScriptContent()).PropTypes().Get(prop); ok && local != "unknown" {
		return local, true
	}
	if p.graph == nil {
		return "", false
	}
	for _, usage := range p.graph.usagesOf(component.Path) {
		for _, binding := range usage.bindings {
			if binding.Name != prop {
				continue
			}
			if typed, ok := p.expressionType(binding.Expression, usage.parent, seen); ok {
				return typed, true
			}
		}
	}

	return "", false
}

func (p PropTypes) expressionType(expression *typescript.Expr, scope *Sfc, seen []string) (string, bool) {
	chain, ok := expression.AsChain()
	if !ok {
		return expression.InferType()
	}
	typed, ok := p.nameType(scope, chain[0], seen)
	if !ok {
		return "", false
	}
	for _, segment := range chain[1:] {
		typed += "['" + segment + "']"
	}

	return typed, true
}

func (p PropTypes) nameType(scope *Sfc, name string, seen []string) (string, bool) {
	script := ReadScript(scope.ScriptContent())
	if typed, ok := script.PropTypes().Get(name); ok {
		return typed, true
	}
	if typed, ok := script.DeclaredType(name); ok {
		return typed, true
	}
	if typed, ok := composableType(scope, script, name); ok {
		return typed, true
	}

	return p.typeOf(scope, name, seen)
}

func composableType(scope *Sfc, script Script, name string) (string, bool) {
	composable, ok := script.DestructuredCall(name)
	if !ok {
		return "", false
	}
	specifier, ok := script.ImportSpecifier(composable)
	if !ok {
		return "", false
	}
	path, ok := ResolverFor(scope.Path).Resolve(scope.Path, specifier)
	if !ok {
		return "", false
	}
	module := ScriptOf(path)
	returnType, declares := module.ReturnTypeName(composable)
	if !declares {
		return module.InferredReturnFields(composable).Get(name)
	}
	var fields typescript.Fields
	if object, isObject := typescript.ParseType(returnType).(typescript.ObjectType); isObject {
		fields = object.Fields()
	} else {
		fields = TypeFieldsFrom(returnType, path, module)
	}
	typed, ok := fields.Get(name)
	if !ok {
		return "", false
	}

	return typescript.UnwrapRefText(typed), true
}

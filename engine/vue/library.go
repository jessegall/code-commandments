package vue

import (

	"github.com/jessegall/code-commandments/engine/typescript"
)

// Modules finds the script of a module file: a component's scripts, or a TypeScript file whole.
type Modules func(path string) (typescript.Module, bool)

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

// componentUsage is a parent component rendering a child, and what it binds each prop to.
type componentUsage struct {
	parent   Component
	bindings map[string]Directive
}

// ComponentGraph is who renders each component, with what props.
type ComponentGraph struct {
	incoming map[string][]componentUsage
}

// PropTypes traces a prop's type up the render tree when the component itself leaves it open.
type PropTypes struct {
	graph   *ComponentGraph
	modules Modules
}


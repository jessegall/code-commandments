package vue

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/typescript"
)

var refs = map[string]bool{"Ref": true, "ShallowRef": true, "ComputedRef": true, "WritableComputedRef": true}

// Component is a .vue file: its root, and the script its template reads names from.
type Component struct {
	engine.Match
}

func (Component) Decorate(m engine.Match) Component {
	return Component{m}
}

func ComponentOf(m engine.Match) Component {
	if root := m.Root(); root.Kind() == "Component" {
		return Component{root}
	}

	return Component{}
}

func (c Component) Statements() []typescript.Node {
	var statements []typescript.Node
	for _, block := range c.ChildrenIn("blocks") {
		if block.Name() != "script" {
			continue
		}
		for _, statement := range block.Child("children").ChildrenIn("statements") {
			statements = append(statements, typescript.Of(statement))
		}
	}

	return statements
}

func (c Component) Binding(name string) typescript.Node {
	for _, statement := range c.Statements() {
		if declaration := statement.Binds(name); declaration.Exists() {
			return declaration
		}
	}

	return typescript.Node{}
}

func (c Component) DefineProps() typescript.Node {
	for _, statement := range c.Statements() {
		for _, node := range statement.Descendants() {
			if node.Kind() == "CallExpression" && node.Child("expression").Name() == "defineProps" {
				return typescript.Of(node)
			}
		}
	}

	return typescript.Node{}
}

func (c Component) Props(codebase *engine.Codebase) []typescript.Node {
	argument := c.DefineProps().Child("typeArguments")
	if !argument.Exists() {
		return nil
	}

	return typescript.Of(argument).Members(codebase)
}

func (c Component) Prop(codebase *engine.Codebase, name string) typescript.Node {
	for _, prop := range c.Props(codebase) {
		if prop.Name() == name {
			return prop
		}
	}

	return typescript.Node{}
}

func (c Component) TypeOf(codebase *engine.Codebase, name string) (*contract.Type, bool) {
	if prop := c.Prop(codebase, name); prop.Declared() != nil {
		return prop.Declared(), true
	}
	binding := c.Binding(name)
	if binding.Kind() != "VariableDeclaration" {
		return nil, false
	}
	if declared := binding.Declared(); declared != nil {
		return unwrapped(declared), true
	}
	if resolved := typescript.Of(binding.Child("initializer")).Resolved(); resolved != nil {
		return unwrapped(resolved), true
	}

	return nil, false
}

func unwrapped(t *contract.Type) *contract.Type {
	if t.Kind == "named" && refs[typescript.DeclaredName(t.Name)] && len(t.Args) > 0 {
		return t.Args[0]
	}

	return t
}

func (c Component) Template() engine.Match {
	for _, block := range c.ChildrenIn("blocks") {
		if block.Name() == "template" {
			return block
		}
	}

	return engine.Match{}
}

func (c Component) Size() int {
	size := 0
	for _, element := range c.TemplateElements() {
		size += element.Rendered()
	}

	return size
}

func (c Component) TemplateLines() int {
	span, err := c.Template().Span()
	if err != nil {
		return 0
	}

	return strings.Count(span.Text(), "\n") + 1
}

func (c Component) LocalNames() []string {
	var names []string
	for _, statement := range c.Statements() {
		names = append(names, statement.DeclaredNames()...)
	}

	return names
}

func (c Component) PropsVariable() string {
	call := c.DefineProps()
	for _, statement := range c.Statements() {
		for _, declaration := range statement.Child("declarationList").ChildrenIn("declarations") {
			if slices.ContainsFunc(declaration.Descendants(), func(node engine.Match) bool { return node.Node() == call.Node() }) {
				return declaration.Child("name").Name()
			}
		}
	}

	return ""
}

func (c Component) ReadsMember(name, member string) bool {
	for _, statement := range c.Statements() {
		for _, node := range statement.Descendants() {
			if node.Kind() == "PropertyAccessExpression" && node.Name() == member && node.Child("expression").Name() == name {
				return true
			}
		}
	}

	return false
}

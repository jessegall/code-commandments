package vue

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/engine/typescript"
)

// refs are Vue's reactive wrappers a template reads through: a ref named in a template is its value.
var refs = map[string]bool{"Ref": true, "ShallowRef": true, "ComputedRef": true, "WritableComputedRef": true}

// Component is a .vue file: its root, and the script its template reads names from.
type Component struct {
	engine.Match
}

// Decorate is the component a query's match is: the root of a .vue file.
func (Component) Decorate(m engine.Match) Component {
	return Component{m}
}

// ComponentOf is the component a node of a .vue file belongs to; no node outside one.
func ComponentOf(m engine.Match) Component {
	if root := m.Root(); root.Kind() == "Component" {
		return Component{root}
	}

	return Component{}
}

// Statements is the top-level statements of every <script> block, in order.
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

// Binding is the script's top-level declaration of a name its template can read: a variable, a function or
// an import; no node when the script binds no such name.
func (c Component) Binding(name string) typescript.Node {
	for _, statement := range c.Statements() {
		if declaration := statement.Binds(name); declaration.Exists() {
			return declaration
		}
	}

	return typescript.Node{}
}

// DefineProps is the component's defineProps call, bare or inside withDefaults; no node when it declares none.
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

// Props is each prop the defineProps type argument declares, as its property signature: a literal type's
// members, or those of the interface or alias it names, wherever in the codebase that is declared.
func (c Component) Props(codebase *engine.Codebase) []typescript.Node {
	argument := c.DefineProps().Child("typeArguments")
	if !argument.Exists() {
		return nil
	}

	return typescript.Of(argument).Members(codebase)
}

// Prop is the prop of the name the component declares; no node when it declares none.
func (c Component) Prop(codebase *engine.Codebase, name string) typescript.Node {
	for _, prop := range c.Props(codebase) {
		if prop.Name() == name {
			return prop
		}
	}

	return typescript.Node{}
}

// TypeOf is the type a name has where the template reads it: a prop's declared type, else the script
// binding's, a ref read as its value. False when neither is known.
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

// unwrapped is a ref's value type, as a template reads it; any other type as it is.
func unwrapped(t *contract.Type) *contract.Type {
	if t.Kind == "named" && refs[typescript.DeclaredName(t.Name)] && len(t.Args) > 0 {
		return t.Args[0]
	}

	return t
}

// Template is the component's <template> block; no node when it has none.
func (c Component) Template() engine.Match {
	for _, block := range c.ChildrenIn("blocks") {
		if block.Name() == "template" {
			return block
		}
	}

	return engine.Match{}
}

// Size is how many elements the component's template renders, a <template> wrapper never one of them.
func (c Component) Size() int {
	size := 0
	for _, element := range c.TemplateElements() {
		size += element.Rendered()
	}

	return size
}

// TemplateLines is how many lines the component's template spans.
func (c Component) TemplateLines() int {
	span, err := c.Template().Span()
	if err != nil {
		return 0
	}

	return strings.Count(span.Text(), "\n") + 1
}

// LocalNames is every name the component's script declares at its top: variables, functions, classes.
func (c Component) LocalNames() []string {
	var names []string
	for _, statement := range c.Statements() {
		names = append(names, statement.DeclaredNames()...)
	}

	return names
}

// PropsVariable is the name the script holds its props under, props in `const props = defineProps(...)`;
// empty when it holds them under none.
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

// ReadsMember says whether the script reads the member off the name anywhere: props.customer.
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

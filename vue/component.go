package vue

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
	"github.com/jessegall/code-commandments/typescript"
)

// refs are Vue's reactive wrappers a template reads through: a ref named in a template is its value.
var refs = map[string]bool{"Ref": true, "ShallowRef": true, "ComputedRef": true, "WritableComputedRef": true}

// Component is a .vue file: its root, and the script its template reads names from.
type Component struct {
	engine.Match
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
	if t.Kind == "named" && refs[typescript.DeclaredName(t.Name)] && len(t.Args) == 1 {
		return t.Args[0]
	}

	return t
}

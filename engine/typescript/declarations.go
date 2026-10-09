package typescript

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// Binds is the declaration in this top-level statement that binds the name: a variable of a const/let/var
// statement, a function or class of that name, or an import binding; no node when it binds no such name.
func (n Node) Binds(name string) Node {
	switch n.Kind() {
	case "VariableStatement":
		for _, declaration := range n.Child("declarationList").ChildrenIn("declarations") {
			if declaration.Child("name").Name() == name {
				return Node{declaration}
			}
		}
	case "FunctionDeclaration", "ClassDeclaration":
		if n.Name() == name {
			return n
		}
	case "ImportDeclaration":
		for _, node := range n.Descendants() {
			if node.Node().Field == "name" && node.Name() == name {
				return Node{node.Parent()}
			}
		}
	}

	return Node{}
}

// Members is what a written type declares as members: a type literal's own, or those of the interface or
// type alias a type reference names, followed to its declaration anywhere in the codebase.
func (n Node) Members(codebase *engine.Codebase) []Node {
	return n.members(codebase, 0)
}

func (n Node) members(codebase *engine.Codebase, depth int) []Node {
	if depth > 8 {
		return nil
	}
	switch n.Kind() {
	case "TypeLiteral", "InterfaceDeclaration":
		var members []Node
		for _, member := range n.ChildrenIn("members") {
			members = append(members, Node{member})
		}

		return members
	case "TypeAliasDeclaration":
		return Node{n.Child("type")}.members(codebase, depth+1)
	case "TypeReference":
		refers := n.Child("typeName").Node()
		if refers == nil || refers.Refers == "" {
			return nil
		}
		for _, declaration := range codebase.Declarations(refers.Refers) {
			if members := (Node{declaration}).members(codebase, depth+1); members != nil {
				return members
			}
		}
	}

	return nil
}

// DeclaredNames is every name a top-level statement declares: each variable, destructured ones included,
// and a function's or class's own name.
func (n Node) DeclaredNames() []string {
	switch n.Kind() {
	case "VariableStatement":
		var names []string
		for _, declaration := range n.Child("declarationList").ChildrenIn("declarations") {
			names = append(names, Node{declaration.Child("name")}.boundNames()...)
		}

		return names
	case "FunctionDeclaration", "ClassDeclaration":
		if n.Name() != "" {
			return []string{n.Name()}
		}
	}

	return nil
}

// boundNames is every name a binding pattern binds: x, or a and b in { a, b: [b] }.
func (n Node) boundNames() []string {
	if n.Kind() == "Identifier" {
		return []string{n.Name()}
	}
	var names []string
	for _, element := range n.Children() {
		if element.Kind() == "BindingElement" {
			names = append(names, Node{element.Child("name")}.boundNames()...)
		}
	}

	return names
}

// IsObjectType says whether the node declares an object's shape: an interface, or a type alias of a literal
// object type.
func (n Node) IsObjectType() bool {
	return n.Kind() == "InterfaceDeclaration" || (n.Kind() == "TypeAliasDeclaration" && n.Child("type").Kind() == "TypeLiteral")
}

// Namespace is the dotted name of the namespaces the declaration sits in, outermost first: App.Data for a type inside
// `declare namespace App.Data { … }`; empty outside any.
func (n Node) Namespace() string {
	var names []string
	for at := n.Parent(); at.Exists(); at = at.Parent() {
		if at.Kind() == "ModuleDeclaration" {
			names = append([]string{at.Name()}, names...)
		}
	}

	return strings.Join(names, ".")
}

// FieldNames is the names of the members an object type declares itself, in order.
func (n Node) FieldNames() []string {
	members := n.ChildrenIn("members")
	if n.Kind() == "TypeAliasDeclaration" {
		members = n.Child("type").ChildrenIn("members")
	}
	var names []string
	for _, member := range members {
		if member.Name() != "" {
			names = append(names, member.Name())
		}
	}

	return names
}

package typescript

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

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

func (n Node) IsObjectType() bool {
	return n.Kind() == "InterfaceDeclaration" || (n.Kind() == "TypeAliasDeclaration" && n.Child("type").Kind() == "TypeLiteral")
}

func (n Node) Namespace() string {
	var names []string
	for at := n.Parent(); at.Exists(); at = at.Parent() {
		if at.Kind() == "ModuleDeclaration" {
			names = append([]string{at.Name()}, names...)
		}
	}

	return strings.Join(names, ".")
}

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

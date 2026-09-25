package typescript

import "github.com/jessegall/code-commandments/engine"

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

// Descendants is every node below this one, in pre-order.
func (n Node) Descendants() []engine.Match {
	var below []engine.Match
	for _, child := range n.Children() {
		below = append(below, child)
		below = append(below, Node{child}.Descendants()...)
	}

	return below
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

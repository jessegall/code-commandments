package python

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// Node is a Python node as a detector asks about it, the Python twin of a backend NodeMatch:
// Where(engine.As(python.Node.IsFunction)). Its navigation stays in Python terms.
type Node struct {
	engine.Match
}

// Decorate is the Python view of a match.
func (Node) Decorate(match engine.Match) Node {
	return Node{match}
}

// Parent is the node whose children hold this one; no node above the root.
func (n Node) Parent() Node {
	return Node{n.Match.Parent()}
}

// Child is the first child filling the ast field, such as "func" or "value"; no node when none does.
func (n Node) Child(field string) Node {
	return Node{n.Match.Child(field)}
}

// Children is the node's children, in source order.
func (n Node) Children() []Node {
	return nodes(n.Match.Children())
}

// ChildrenIn is every child filling the ast field, in source order, such as a class's "bases" or a def's "body".
func (n Node) ChildrenIn(field string) []Node {
	return nodes(n.Match.ChildrenIn(field))
}

// Descendants is every node below this one, in pre-order.
func (n Node) Descendants() []Node {
	return nodes(n.Match.Descendants())
}

// nodes is the matches as Python nodes.
func nodes(matches []engine.Match) []Node {
	viewed := make([]Node, len(matches))
	for at, match := range matches {
		viewed[at] = Node{match}
	}

	return viewed
}

// IsDefinition says whether the node is a def, an async def or a class.
func (node Node) IsDefinition() bool {
	return node.IsFunction() || node.Kind() == "ClassDef"
}

// IsFunction says whether the node is a def or an async def; a lambda is not one.
func (node Node) IsFunction() bool {
	return node.Kind() == "FunctionDef" || node.Kind() == "AsyncFunctionDef"
}

// IsImport says whether the node is an import or a from-import.
func (node Node) IsImport() bool {
	return node.Kind() == "Import" || node.Kind() == "ImportFrom"
}

// Level is a from-import's relative dot count: 0 for an absolute import.
func (node Node) Level() int {
	if extras := node.Node().Extras; extras != nil && extras.Python != nil {
		return extras.Python.Level
	}

	return 0
}

// boundAs is the name an import alias binds: its rename, else the first part of what it imports.
func (alias Node) boundAs() string {
	if renamed, ok := alias.renamedTo(); ok {
		return renamed
	}
	if alias.Parent().Kind() == "Import" {
		return strings.Split(alias.Name(), ".")[0]
	}

	return alias.Name()
}

// DottedName is a name or attribute chain spelled out, a.b.c; empty for any other expression.
func (expression Node) DottedName() string {
	switch expression.Kind() {
	case "Name":
		return expression.Name()
	case "Attribute":
		owner := expression.Child("value").DottedName()
		if owner == "" {
			return ""
		}

		return owner + "." + expression.Name()
	}

	return ""
}

// EnclosingFunction is the nearest def around the node, the node itself when it is one; a lambda is passed over.
func (node Node) EnclosingFunction() Node {
	for around := node; around.Exists(); around = around.Parent() {
		if around.IsFunction() {
			return around
		}
	}

	return Node{}
}

// insideDefinition says whether a def or a class holds the node.
func (node Node) insideDefinition() bool {
	for around := node.Parent(); around.Exists(); around = around.Parent() {
		if around.IsDefinition() {
			return true
		}
	}

	return false
}

// declaredNames are the names a statement declares: a definition's own, an import's bindings.
func (statement Node) declaredNames() []string {
	if statement.IsDefinition() {
		return []string{statement.Name()}
	}
	if !statement.IsImport() {
		return nil
	}
	var names []string
	for _, alias := range statement.ChildrenIn("names") {
		names = append(names, alias.boundAs())
	}

	return names
}

// writtenTargets are the expressions an assignment writes: every target of an =, an augmented one's target, an
// annotated one's target when it holds a value.
func (statement Node) writtenTargets() []Node {
	switch statement.Kind() {
	case "Assign":
		return statement.ChildrenIn("targets")
	case "AugAssign":
		return []Node{statement.Child("target")}
	case "AnnAssign":
		if statement.Child("value").Exists() {
			return []Node{statement.Child("target")}
		}
	}

	return nil
}

// writtenNames are the dotted names an assignment writes.
func (statement Node) writtenNames() []string {
	var names []string
	for _, target := range statement.writtenTargets() {
		names = append(names, target.DottedName())
	}

	return names
}

// IsCall says whether the expression is a call.
func (n Node) IsCall() bool {
	return n.Kind() == "Call"
}

// Callee is what a call calls; no node for any other expression.
func (n Node) Callee() Node {
	return n.Child("func")
}

// Arguments is a call's positional arguments, starred ones included, in source order.
func (n Node) Arguments() []Node {
	return n.ChildrenIn("args")
}

// Keywords is a call's keyword arguments, **spread ones included, in source order.
func (n Node) Keywords() []Node {
	return n.ChildrenIn("keywords")
}

// Keyword is the value a call passes as the named keyword argument; no node when it passes none.
func (n Node) Keyword(name string) Node {
	for _, keyword := range n.Keywords() {
		if keyword.Name() == name {
			return keyword.Child("value")
		}
	}

	return Node{}
}

// IsNone says whether the expression is the literal None.
func (n Node) IsNone() bool {
	return n.Kind() == "Constant" && n.Node().Literal == "null"
}

// dottedBinding is what an absolute import alias binds its name to: a from-import's module and name, a renamed
// import's module, else the first part of the module it imports.
func (alias Node) dottedBinding() string {
	statement := alias.Parent()
	if statement.Kind() == "ImportFrom" {
		return statement.Name() + "." + alias.Name()
	}
	if alias.boundAs() == strings.Split(alias.Name(), ".")[0] {
		return alias.boundAs()
	}

	return alias.Name()
}

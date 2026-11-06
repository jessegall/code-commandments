package python

import (
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// Node is a Python node as a detector asks about it, the Python twin of a backend NodeMatch: Where(engine.As(python.Node.IsFunction)).
type Node struct {
	engine.Match
}

func (Node) Decorate(match engine.Match) Node {
	return Node{match}
}

func (n Node) Parent() Node {
	return Node{n.Match.Parent()}
}

func (n Node) Child(field string) Node {
	return Node{n.Match.Child(field)}
}

func (n Node) Children() []Node {
	return nodes(n.Match.Children())
}

func (n Node) ChildrenIn(field string) []Node {
	return nodes(n.Match.ChildrenIn(field))
}

func (n Node) Descendants() []Node {
	return nodes(n.Match.Descendants())
}

func nodes(matches []engine.Match) []Node {
	viewed := make([]Node, len(matches))
	for at, match := range matches {
		viewed[at] = Node{match}
	}

	return viewed
}

func (node Node) IsDefinition() bool {
	return node.IsFunction() || node.Kind() == "ClassDef"
}

func (node Node) IsFunction() bool {
	return node.Kind() == "FunctionDef" || node.Kind() == "AsyncFunctionDef"
}

func (node Node) IsImport() bool {
	return node.Kind() == "Import" || node.Kind() == "ImportFrom"
}

func (node Node) Level() int {
	if extras := node.Node().Extras; extras != nil && extras.Python != nil {
		return extras.Python.Level
	}

	return 0
}

func (alias Node) boundAs() string {
	if renamed, ok := alias.renamedTo(); ok {
		return renamed
	}
	if alias.Parent().Kind() == "Import" {
		return strings.Split(alias.Name(), ".")[0]
	}

	return alias.Name()
}

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

func (node Node) EnclosingFunction() Node {
	for around := node; around.Exists(); around = around.Parent() {
		if around.IsFunction() {
			return around
		}
	}

	return Node{}
}

func (node Node) insideDefinition() bool {
	for around := node.Parent(); around.Exists(); around = around.Parent() {
		if around.IsDefinition() {
			return true
		}
	}

	return false
}

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

func (statement Node) writtenNames() []string {
	var names []string
	for _, target := range statement.writtenTargets() {
		names = append(names, target.DottedName())
	}

	return names
}

func (n Node) IsCall() bool {
	return n.Kind() == "Call"
}

func (n Node) Callee() Node {
	return n.Child("func")
}

func (n Node) Arguments() []Node {
	return n.ChildrenIn("args")
}

func (n Node) Keywords() []Node {
	return n.ChildrenIn("keywords")
}

func (n Node) Keyword(name string) Node {
	for _, keyword := range n.Keywords() {
		if keyword.Name() == name {
			return keyword.Child("value")
		}
	}

	return Node{}
}

func (n Node) IsFalse() bool {
	return n.Kind() == "Constant" && n.Node().Literal == "bool" && n.Written() == "False"
}

func (n Node) IsNone() bool {
	return n.Kind() == "Constant" && n.Node().Literal == "null"
}

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

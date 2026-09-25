package typescript

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// Rules is how TypeScript reads when a subtree is fingerprinted.
type Rules struct{}

var _ engine.HashRules = Rules{}

// declaresName are the declarations whose own name a shape reads as any name.
var declaresName = []string{"VariableDeclaration", "Parameter", "BindingElement", "FunctionDeclaration"}

// IsName says whether the node is a name read or a local's declared name; a called name and a member's name
// are kept, as they say what the code does.
func (Rules) IsName(m engine.Match) bool {
	if m.Kind() != "Identifier" {
		return false
	}
	field := m.Node().Field
	if field == "expression" && m.Parent().Kind() == "CallExpression" {
		return false
	}
	if field == "name" {
		return slices.Contains(declaresName, m.Parent().Kind())
	}

	return true
}

// Literal reads a literal as its kind and value, a string or number blanked in a shape; a template reads
// whole, as its source.
func (Rules) Literal(m engine.Match, shape bool) (string, bool) {
	node := m.Node()
	if node.Literal == "" {
		return "", false
	}
	value := Node{m}.Source()
	if node.Literal != "interpolated" {
		value = Node{m}.LiteralValue()
	}
	data := node.Literal == "string" || node.Literal == "int" || node.Literal == "float" || node.Literal == "interpolated"
	if shape && data {
		value = "_"
	}

	return "lit:" + node.Literal + ":" + value, true
}

// Leaf reads a written type whole, as its source.
func (Rules) Leaf(m engine.Match) (string, bool) {
	if m.Node().Role != "type" {
		return "", false
	}

	return "T:" + strings.Join(strings.Fields(Node{m}.Source()), " "), true
}

// Body is the block a function-like runs: a function's, a method's, an accessor's, or the block of the arrow
// a variable is declared as. No node for an arrow that is one expression.
func (n Node) Body() Node {
	if n.Kind() == "VariableDeclaration" {
		arrow := n.Child("initializer")
		if arrow.Kind() != "ArrowFunction" {
			return Node{}
		}
		n = Node{arrow}
	}
	if body := n.Child("body"); body.Kind() == "Block" {
		return Node{body}
	}

	return Node{}
}

// BodyHash is the fingerprint of the function's body; empty for a function without a block.
func (n Node) BodyHash() string {
	if body := n.Body(); body.Exists() {
		return engine.Hash(body.Match, Rules{})
	}

	return ""
}

// BodyShape is the shape of the function's body: its code with names and data blanked.
func (n Node) BodyShape() string {
	if body := n.Body(); body.Exists() {
		return engine.ShapeHash(body.Match, Rules{})
	}

	return ""
}

// BodyWeight is how much code the function's body holds: its statements, blocks, catch clauses and
// expressions, member and declaration names not counted. A construction counts twice, for the object it builds
// and the constructor it calls: the duplicate thresholds were set on a count that reads `new X()` as both.
func (n Node) BodyWeight() int {
	body := n.Body()
	if !body.Exists() {
		return 0
	}
	weight := 0
	for _, node := range append([]engine.Match{body.Match}, body.Descendants()...) {
		role := node.Node().Role
		counted := role == "statement" || role == "expression" || node.Is(engine.Block) || node.Is(engine.Catch)
		if counted && !(node.Kind() == "Identifier" && node.Node().Field == "name") {
			weight++
		}
		if node.Is(engine.Construction) {
			weight++
		}
	}

	return weight
}

// SoleStatement is the body's only statement; no node when it holds more or none.
func (n Node) SoleStatement() Node {
	statements := n.Body().ChildrenIn("statements")
	if len(statements) != 1 {
		return Node{}
	}

	return Node{statements[0]}
}

// IsLiteralLookup says whether the body calls nothing and every return hands back a literal: a table of
// answers written as code.
func (n Node) IsLiteralLookup() bool {
	body := n.Body()
	returns := 0
	for _, node := range body.Descendants() {
		if node.Kind() == "CallExpression" {
			return false
		}
		if node.Kind() != "ReturnStatement" {
			continue
		}
		returns++
		value := Node{node.Child("expression")}.unwrap()
		if !value.IsLiteral() {
			return false
		}
	}

	return returns > 0
}

// IsFunction says whether the node is a function the duplication rules compare: a function, a method, a
// constructor, an accessor, or a variable declared as an arrow.
func (n Node) IsFunction() bool {
	switch n.Kind() {
	case "FunctionDeclaration", "MethodDeclaration", "Constructor", "GetAccessor", "SetAccessor":
		return true
	case "VariableDeclaration":
		return n.Child("initializer").Kind() == "ArrowFunction"
	}

	return false
}

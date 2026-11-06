package typescript

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// rules is how TypeScript code is fingerprinted: every node counts, a written type reads whole, a name read or a local's declared name is a name while a called name and a member's name are kept, a string or number is data.
type rules struct{}

var hashing engine.HashRules = rules{}

var declaresName = []string{"VariableDeclaration", "Parameter", "BindingElement", "FunctionDeclaration"}

func (rules) Counts(node engine.Match) bool {
	return node.Kind() != "TemplateSpan"
}

func (rules) Mark(node engine.Match) string {
	if node.Is(engine.NullSafe) {
		return "?."
	}

	return ""
}

func (rules) Unwrap(node engine.Match) (engine.Match, bool) {
	if node.Kind() != "AsExpression" {
		return engine.Match{}, false
	}

	return node.Child("expression"), true
}

func (rules) Weight(node engine.Match) int {
	facts := node.Node()
	if node.Kind() == "Identifier" && facts.Field == "name" {
		return 0
	}
	weight := 0
	if facts.Role == "statement" || facts.Role == "expression" || node.Is(engine.Block) || node.Is(engine.Catch) {
		weight = 1
	}
	if node.Kind() == "VariableDeclaration" && callsAName(node.Child("initializer")) {
		weight++
	}
	if node.Is(engine.Construction) {
		weight++
	}

	return weight
}

func (rules) IsName(node engine.Match) bool {
	if node.Kind() != "Identifier" || isUndefined(node) {
		return false
	}
	if node.Node().Field == "name" {
		return slices.Contains(declaresName, node.Parent().Kind())
	}

	return true
}

func (rules) Declares(node engine.Match) bool {
	return slices.Contains(declaresName, node.Kind())
}

func (rules) IsCallee(node engine.Match) bool {
	return node.Node().Field == "expression" && node.Parent().Kind() == "CallExpression"
}

func (rules) Literal(m engine.Match, shape bool) (string, bool) {
	if isUndefined(m) {
		return "lit:undefined", true
	}
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

func (rules) Leaf(m engine.Match) (string, bool) {
	if m.Node().Role != "type" {
		return "", false
	}

	return "T:" + strings.Join(strings.Fields(Node{m}.Source()), " "), true
}

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

func (n Node) BodyHash() string {
	if body := n.Body(); body.Exists() {
		return engine.SyntaxHash([]engine.Match{body.Match}, hashing, false)
	}

	return ""
}

func (n Node) BodyShape() string {
	if body := n.Body(); body.Exists() {
		return engine.SyntaxHash([]engine.Match{body.Match}, hashing, true)
	}

	return ""
}

func (n Node) BodyWeight() int {
	body := n.Body()
	if !body.Exists() {
		return 0
	}

	return engine.SyntaxWeight([]engine.Match{body.Match}, hashing)
}

func (n Node) SoleStatement() Node {
	statements := n.Body().ChildrenIn("statements")
	if len(statements) != 1 {
		return Node{}
	}

	return Node{statements[0]}
}

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

func (n Node) IsLiteralScript() bool {
	statements := n.Body().ChildrenIn("statements")
	if len(statements) == 0 {
		return false
	}
	callee := Node{statements[0]}.statementCall().Child("expression")
	for _, statement := range statements {
		call := Node{statement}.statementCall()
		if !call.Child("expression").SameSyntax(callee) {
			return false
		}
		for _, argument := range call.ChildrenIn("arguments") {
			if !(Node{argument}).IsLiteral() {
				return false
			}
		}
	}

	return true
}

func (n Node) statementCall() Node {
	if n.Kind() != "ExpressionStatement" {
		return Node{}
	}
	expression := Node{n.Child("expression")}.unwrap()
	if expression.Kind() == "AwaitExpression" {
		expression = Node{expression.Child("expression")}.unwrap()
	}
	if expression.Kind() != "CallExpression" {
		return Node{}
	}

	return expression
}

func (n Node) IsFunction() bool {
	switch n.Kind() {
	case "FunctionDeclaration", "MethodDeclaration", "Constructor", "GetAccessor", "SetAccessor":
		return true
	case "VariableDeclaration":
		return n.Child("initializer").Kind() == "ArrowFunction"
	}

	return false
}

func isUndefined(node engine.Match) bool {
	return node.Kind() == "Identifier" && node.Name() == "undefined" && node.Node().Field != "name"
}

func callsAName(expression engine.Match) bool {
	for expression.Kind() == "CallExpression" {
		expression = expression.Child("expression")
	}

	return expression.Kind() == "Identifier"
}

func (n Node) NodeHash(normalize bool) string {
	return engine.SyntaxHash([]engine.Match{n.Match}, hashing, normalize)
}

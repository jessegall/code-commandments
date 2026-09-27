package typescript

import (
	"slices"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// rules is how TypeScript code is fingerprinted: every node counts, a written type reads whole, a name read or a
// local's declared name is a name while a called name and a member's name are kept, a string or number is data.
// Nodes weigh as the PHP engine's tree has them, so a size floor means the same on both.
type rules struct{}

var hashing engine.HashRules = rules{}

// declaresName are the declarations whose own name normalising leaves out: two names for one body are one code.
var declaresName = []string{"VariableDeclaration", "Parameter", "BindingElement", "FunctionDeclaration"}

// Counts leaves out what the PHP engine reads as part of one node: the spans of a template literal, which is one
// literal.
func (rules) Counts(node engine.Match) bool {
	return node.Kind() != "TemplateSpan"
}

// Mark sets an optional chain apart from a plain one, as the PHP engine does: `a?.b` guards what `a.b` assumes.
func (rules) Mark(node engine.Match) string {
	if node.Is(engine.NullSafe) {
		return "?."
	}

	return ""
}

// Unwrap reads a value cast with `as` as the value, as the PHP engine does: the cast names a type, not code.
func (rules) Unwrap(node engine.Match) (engine.Match, bool) {
	if node.Kind() != "AsExpression" {
		return engine.Match{}, false
	}

	return node.Child("expression"), true
}

// Weight counts statements, blocks, catch clauses and expressions, but not a member's or a declaration's name,
// nor a written type. A construction counts twice, for the object it builds and the constructor it calls, as the
// PHP engine's tree reads `new X()`.
func (rules) Weight(node engine.Match) int {
	facts := node.Node()
	if node.Kind() == "Identifier" && facts.Field == "name" {
		return 0
	}
	weight := 0
	if facts.Role == "statement" || facts.Role == "expression" || node.Is(engine.Block) || node.Is(engine.Catch) {
		weight = 1
	}
	// A declaration initialised by a call of a plain name weighs one, as the PHP engine weighs it: its parser keeps the
	// name called beside the call, as it keeps `useThing` for `const x = useThing()`.
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

// Literal reads a literal as its kind and value, a string or number blanked in a shape; a template reads
// whole, as its source.
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

// Leaf reads a written type whole, as its source.
func (rules) Leaf(m engine.Match) (string, bool) {
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
		return engine.SyntaxHash([]engine.Match{body.Match}, hashing, false)
	}

	return ""
}

// BodyShape is the shape of the function's body: its code with names and data blanked.
func (n Node) BodyShape() string {
	if body := n.Body(); body.Exists() {
		return engine.SyntaxHash([]engine.Match{body.Match}, hashing, true)
	}

	return ""
}

// BodyWeight is how much code the function's body holds, as the rules weigh it.
func (n Node) BodyWeight() int {
	body := n.Body()
	if !body.Exists() {
		return 0
	}

	return engine.SyntaxWeight([]engine.Match{body.Match}, hashing)
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

// isUndefined says whether the node is `undefined`: a value, as `null` is, though TypeScript's tree spells it as a
// name — the PHP engine reads it as the constant it is, never blanked as data.
func isUndefined(node engine.Match) bool {
	return node.Kind() == "Identifier" && node.Name() == "undefined" && node.Node().Field != "name"
}

// callsAName says whether the expression is a call whose callee, followed through calls, is a plain name: f(), f(x)(y).
func callsAName(expression engine.Match) bool {
	for expression.Kind() == "CallExpression" {
		expression = expression.Child("expression")
	}

	return expression.Kind() == "Identifier"
}

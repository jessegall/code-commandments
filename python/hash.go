package python

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

// hashRules is how Python code is fingerprinted: a docstring runs nothing and a type annotation is read as a type,
// so neither counts; names are Name expressions; a string or a number is data, a bool or None is not. It weighs
// nodes as the PHP engine's tree has them, a block for each list of statements and none for a wrapper Python's
// ast adds, so a size floor means the same on both.
type hashRules struct{}

var hashing engine.HashRules = hashRules{}

func (hashRules) Counts(node engine.Match) bool {
	facts := node.Node()
	if facts.Role == "type" {
		return false
	}
	value := Node{node}.Child("value")
	_, isText := value.Text()

	return !(facts.Kind == "Expr" && isText)
}

// Weight counts a node as the PHP engine's tree holds it: a block for each list of statements, an elif standing
// in its if's place without one; one binary per
// extra operand of an `and`, an `or` or a `|` pattern; a node for each `**` entry of a dict and for a `*rest`
// capture's name and a conversion; and nothing for a wrapper Python's ast adds around what it holds.
func (hashRules) Weight(node engine.Match) int {
	facts := node.Node()
	switch facts.Kind {
	case "arguments", "withitem", "alias", "MatchValue":
		return 0
	case "FormattedValue":
		if facts.Operator == "" {
			return 0
		}
	case "JoinedStr":
		if facts.Field == "format_spec" {
			return 0
		}
	case "BoolOp":
		return len(node.ChildrenIn("values")) - 1
	case "MatchOr":
		return len(node.ChildrenIn("patterns")) - 1
	case "Dict":
		return 1 + len(node.ChildrenIn("values")) - len(node.ChildrenIn("keys"))
	case "Global", "Nonlocal":
		return 1 + len(facts.Extras.Python.Names)
	case "MatchStar":
		if facts.Name != "" {
			return 2
		}
	}
	weight := 1
	for _, field := range []string{"body", "orelse", "finalbody"} {
		if statements := node.ChildrenIn(field); facts.Role != "expression" && len(statements) > 0 && !isElif(statements) {
			weight++
		}
	}

	return weight
}

func (hashRules) IsName(node engine.Match) bool {
	return node.Kind() == "Name"
}

func (hashRules) Declares(node engine.Match) bool {
	return Node{node}.IsDefinition() || node.Kind() == "arg" || node.Kind() == "alias"
}

func (hashRules) IsCallee(node engine.Match) bool {
	return node.Node().Field == "func" && node.Parent().Kind() == "Call"
}

func (hashRules) Literal(node engine.Match, normalize bool) (string, bool) {
	facts := node.Node()
	if facts.Kind != "Constant" {
		return "", false
	}
	if normalize && slices.Contains([]string{"string", "bytes", "int", "float"}, facts.Literal) {
		return "lit:" + facts.Literal + ":_", true
	}

	return "lit:" + facts.Literal + ":" + node.Written(), true
}

// BodyHash is a formatting-blind fingerprint of the statements the def runs, its name left out: two names for one
// body are the same code. Empty for a node that is no def.
func (n Node) BodyHash() string {
	if !n.IsFunction() {
		return ""
	}

	return engine.SyntaxHash(n.body(), hashing, false)
}

// ShapeHash is the body's fingerprint blind to local names and data literals too: two bodies with one skeleton
// that differ only in what they call their locals and which constants they use.
func (n Node) ShapeHash() string {
	if !n.IsFunction() {
		return ""
	}

	return engine.SyntaxHash(n.body(), hashing, true)
}

// BodyWeight is how many nodes make up the def's body, a block counted as the PHP engine counts one; zero for a
// node that is no def.
func (n Node) BodyWeight() int {
	if !n.IsFunction() {
		return 0
	}

	return 1 + engine.SyntaxWeight(n.body(), hashing)
}

// ExpressionHash is a formatting-blind fingerprint of one expression: two spellings of the same read hash alike.
func (n Node) ExpressionHash() string {
	return engine.SyntaxHash([]engine.Match{n.Match}, hashing, false)
}

// body is the def's statements as matches.
func (n Node) body() []engine.Match {
	var statements []engine.Match
	for _, statement := range n.ChildrenIn("body") {
		statements = append(statements, statement.Match)
	}

	return statements
}

// isElif says whether the statements are an elif alone: the else of an if written as the next test.
func isElif(statements []engine.Match) bool {
	return len(statements) == 1 && slices.Contains(statements[0].Node().Flags, "elif")
}

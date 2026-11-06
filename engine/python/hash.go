package python

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

// hashRules is how Python code is fingerprinted: a docstring runs nothing and a type annotation is read as a type, so neither counts; names are Name expressions; a string or a number is data, a bool or None is not.
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

func (hashRules) Leaf(engine.Match) (string, bool) {
	return "", false
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

func (n Node) BodyHash() string {
	if !n.IsFunction() {
		return ""
	}

	return engine.SyntaxHash(n.body(), hashing, false)
}

func (n Node) ShapeHash() string {
	if !n.IsFunction() {
		return ""
	}

	return engine.SyntaxHash(n.body(), hashing, true)
}

func (n Node) BodyWeight() int {
	if !n.IsFunction() {
		return 0
	}

	return 1 + engine.SyntaxWeight(n.body(), hashing)
}

func (n Node) ExpressionHash() string {
	return engine.SyntaxHash([]engine.Match{n.Match}, hashing, false)
}

func (n Node) body() []engine.Match {
	var statements []engine.Match
	for _, statement := range n.ChildrenIn("body") {
		statements = append(statements, statement.Match)
	}

	return statements
}

func isElif(statements []engine.Match) bool {
	return len(statements) == 1 && slices.Contains(statements[0].Node().Flags, "elif")
}

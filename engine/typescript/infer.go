package typescript

import (
	"slices"
	"strconv"
	"strings"
)

// comparisons are the operators whose result is a boolean.
var comparisons = []string{"===", "!==", "==", "!=", "<", ">", "<=", ">=", "instanceof", "in"}

// arithmetic are the binary operators whose result is a number.
var arithmetic = []string{"-", "*", "/", "%", "**"}

// logical are the binary operators whose result is one of their operands.
var logical = []string{"&&", "||", "??"}

// assignments are the operators that write their left operand.
var assignments = []string{"=", "+=", "-=", "*=", "/=", "%=", "**=", "??=", "||=", "&&="}

// bare is the expression parentheses hold.
func (n Node) bare() Node {
	for n.Kind() == "ParenthesizedExpression" {
		n = Node{n.Child("expression")}
	}

	return n
}

// InferType is the type the expression is of, where the expression alone tells it for certain, as the PHP tool
// tells it: a literal's, an operator's result, the union of what a condition or a fallback chooses between, an
// array of one element type.
func (n Node) InferType() (TypeNode, bool) {
	n = n.bare()
	switch n.Kind() {
	case "StringLiteral", "NoSubstitutionTemplateLiteral", "TemplateExpression":
		return keyword("string")
	case "NumericLiteral":
		return KeywordType{Name: "number"}, isNumeric(n.Source())
	case "TrueKeyword", "FalseKeyword":
		return keyword("boolean")
	case "NullKeyword":
		return keyword("null")
	case "Identifier":
		return KeywordType{Name: "undefined"}, n.Name() == "undefined"
	case "PrefixUnaryExpression", "PostfixUnaryExpression":
		return unaryType(n.Operator())
	case "TypeOfExpression":
		return keyword("string")
	case "VoidExpression":
		return keyword("undefined")
	case "DeleteExpression":
		return keyword("boolean")
	case "BinaryExpression":
		switch {
		case slices.Contains(comparisons, n.Operator()):
			return keyword("boolean")
		case slices.Contains(arithmetic, n.Operator()):
			return keyword("number")
		case slices.Contains(logical, n.Operator()):
			return unionType(n.Left(), n.Right())
		}
	case "ConditionalExpression":
		return unionType(Node{n.Child("whenTrue")}, Node{n.Child("whenFalse")})
	case "ArrayLiteralExpression":
		return arrayType(n)
	}

	return nil, false
}

func keyword(name string) (TypeNode, bool) {
	return KeywordType{Name: name}, true
}

func unaryType(operator string) (TypeNode, bool) {
	switch operator {
	case "!":
		return keyword("boolean")
	case "-", "+", "++", "--":
		return keyword("number")
	}

	return nil, false
}

func arrayType(n Node) (TypeNode, bool) {
	elements := n.ChildrenIn("elements")
	if len(elements) == 0 {
		return nil, false
	}
	var element TypeNode
	for _, node := range elements {
		typed, ok := Node{node}.InferType()
		if !ok || element != nil && typed.Render() != element.Render() {
			return nil, false
		}
		element = typed
	}

	return ArrayType{Element: element}, true
}

// unionType is the types the expressions are of, each once, joined as the PHP tool joins them.
func unionType(nodes ...Node) (TypeNode, bool) {
	var types []TypeNode
	seen := map[string]bool{}
	for _, node := range nodes {
		typed, ok := node.InferType()
		if !ok {
			return nil, false
		}
		if !seen[typed.Render()] {
			seen[typed.Render()] = true
			types = append(types, typed)
		}
	}
	if len(types) == 1 {
		return types[0], true
	}

	return inferredUnion{CompositeType{Operator: "|", Members: types}}, len(types) > 0
}

// isNumeric answers as PHP's is_numeric does on a literal: digits with at most one point and an optional exponent.
func isNumeric(text string) bool {
	trimmed := strings.TrimSpace(text)
	if trimmed == "" || strings.ContainsAny(trimmed, "_xXbBoOiInN") {
		return false
	}
	_, err := strconv.ParseFloat(trimmed, 64)

	return err == nil || strings.Contains(err.Error(), "value out of range")
}

// ReturnType is the type an arrow function's expression body is of.
func (n Node) ReturnType() (TypeNode, bool) {
	n = n.bare()
	body := Node{n.Child("body")}
	if n.Kind() != "ArrowFunction" || body.Kind() == "Block" {
		return nil, false
	}

	return body.InferType()
}

// CalledFunctions is each name the expression calls as a plain function, once.
func (n Node) CalledFunctions() []string {
	var names []string
	for _, node := range append([]Node{n}, descendantsOf(n)...) {
		callee := Node{node.Child("expression")}.bare()
		if node.Kind() == "CallExpression" && callee.Kind() == "Identifier" && !slices.Contains(names, callee.Name()) {
			names = append(names, callee.Name())
		}
	}

	return names
}

func descendantsOf(n Node) []Node {
	descendants := n.Descendants()
	nodes := make([]Node, 0, len(descendants))
	for _, descendant := range descendants {
		nodes = append(nodes, Node{descendant})
	}

	return nodes
}

// IsAssignment says whether the expression writes a target: `x = y`, `total += 1`.
func (n Node) IsAssignment() bool {
	n = n.bare()

	return n.Kind() == "BinaryExpression" && slices.Contains(assignments, n.Operator())
}

// PlainCall is a call to a plain name whose arguments are each a name, a literal, a member or an index of them.
type PlainCall struct {
	Name      string
	Arguments []string
}

// Arity is how many arguments the call passes.
func (c PlainCall) Arity() int { return len(c.Arguments) }

// TrailingArguments is the arguments as they follow a first one: `, a, b`, or nothing.
func (c PlainCall) TrailingArguments() string {
	if len(c.Arguments) == 0 {
		return ""
	}

	return ", " + strings.Join(c.Arguments, ", ")
}

// AsPlainCall is the expression as a call to a plain name with plainly written arguments.
func (n Node) AsPlainCall() (PlainCall, bool) {
	n = n.bare()
	callee := Node{n.Child("expression")}.bare()
	if n.Kind() != "CallExpression" || callee.Kind() != "Identifier" {
		return PlainCall{}, false
	}
	var arguments []string
	for _, argument := range n.ChildrenIn("arguments") {
		written, ok := plainly(Node{argument})
		if !ok {
			return PlainCall{}, false
		}
		arguments = append(arguments, written)
	}

	return PlainCall{Name: callee.Name(), Arguments: arguments}, true
}

// plainly is a name, a literal, a member or an index of them, as written again, a cast or an assertion read as what
// it wraps; false for anything else.
func plainly(n Node) (string, bool) {
	n = n.unwrap()
	switch n.Kind() {
	case "Identifier", "StringLiteral", "NumericLiteral", "NoSubstitutionTemplateLiteral", "TemplateExpression",
		"TrueKeyword", "FalseKeyword", "NullKeyword", "ThisKeyword":
		return n.Source(), true
	case "PropertyAccessExpression":
		object, ok := plainly(Node{n.Child("expression")})
		dot := "."
		if n.HasFlag("optional") {
			dot = "?."
		}

		return object + dot + n.Name(), ok
	case "ElementAccessExpression":
		object, ok := plainly(Node{n.Child("expression")})
		index, indexed := plainly(Node{n.Child("argumentExpression")})

		return object + "[" + index + "]", ok && indexed
	}

	return "", false
}

// CalleeName is the name a call calls, when it calls a plain name.
func (n Node) CalleeName() (string, bool) {
	n = n.bare()
	callee := Node{n.Child("expression")}.bare()
	if n.Kind() != "CallExpression" || callee.Kind() != "Identifier" {
		return "", false
	}

	return callee.Name(), true
}

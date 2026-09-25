// Package csharp is the C# engine: the reads a C# detector asks of a node the Roslyn bridge wrote, and the
// analyses over the whole program — resolution, state flow and the namespace graph.
package csharp

import (
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

// functions are the kinds that run a body of their own: a member, an accessor, a local function, a lambda.
var functions = []string{
	"MethodDeclaration", "ConstructorDeclaration", "DestructorDeclaration", "OperatorDeclaration", "ConversionOperatorDeclaration",
	"PropertyDeclaration", "IndexerDeclaration",
	"LocalFunctionStatement", "GetAccessorDeclaration", "SetAccessorDeclaration", "InitAccessorDeclaration",
	"AddAccessorDeclaration", "RemoveAccessorDeclaration", "ParenthesizedLambdaExpression", "SimpleLambdaExpression", "AnonymousMethodExpression",
}

// Node is a C# node as a detector asks about it, the C# twin of a backend NodeMatch:
// Where(engine.As(csharp.Node.IsCall)). Its navigation stays in C# terms.
type Node struct {
	engine.Match
}

// Decorate is the C# view of a match.
func (Node) Decorate(match engine.Match) Node {
	return Node{match}
}

// Parent is the node whose children hold this one; no node above the root.
func (n Node) Parent() Node {
	return Node{n.Match.Parent()}
}

// Child is the first child filling the Roslyn property, such as "Expression" or "Body"; no node when none does.
func (n Node) Child(field string) Node {
	return Node{n.Match.Child(field)}
}

// ChildrenIn is every child filling the Roslyn property, in source order, such as a class's "Members".
func (n Node) ChildrenIn(field string) []Node {
	return nodes(n.Match.ChildrenIn(field))
}

// All is every child, expressions included, in source order.
func (n Node) All() []Node {
	return nodes(n.Match.Children())
}

// At is the child at the position, expressions included; no node past the last.
func (n Node) At(position int) Node {
	children := n.Match.Children()
	if position < 0 || position >= len(children) {
		return Node{}
	}

	return Node{children[position]}
}

// nodes is the matches as C# nodes.
func nodes(matches []engine.Match) []Node {
	viewed := make([]Node, len(matches))
	for at, match := range matches {
		viewed[at] = Node{match}
	}

	return viewed
}

// Is says whether the node is one of the kinds.
func (n Node) Is(kinds ...string) bool {
	return n.Exists() && slices.Contains(kinds, n.Kind())
}

// IsExpression says whether the node is an expression the compiler evaluates.
func (n Node) IsExpression() bool {
	return n.Exists() && n.Node().Role == "expression"
}

// IsStatement says whether the node is a statement; a local function is one, though it declares.
func (n Node) IsStatement() bool {
	return n.Exists() && (n.Node().Role == "statement" || n.Is("LocalFunctionStatement"))
}

// IsTypeNode says whether the node is a type as written, in a position only a type can fill.
func (n Node) IsTypeNode() bool {
	return n.Exists() && n.Node().Role == "type"
}

// Children is the child nodes that are not expressions: statements, members, parameters, clauses.
func (n Node) Children() []Node {
	var kept []Node
	for _, child := range n.All() {
		if !child.IsExpression() {
			kept = append(kept, child)
		}
	}

	return kept
}

// Expressions is the expressions this node holds directly.
func (n Node) Expressions() []Node {
	var kept []Node
	for _, child := range n.All() {
		if child.IsExpression() {
			kept = append(kept, child)
		}
	}

	return kept
}

// Descendants is every node beneath this one that is not an expression, at any depth, reached through
// expressions too, so the statements of a lambda's block are found.
func (n Node) Descendants() []Node {
	var all []Node
	for _, child := range n.All() {
		if !child.IsExpression() {
			all = append(all, child)
		}
		all = append(all, child.Descendants()...)
	}

	return all
}

// Flatten is this expression and every expression beneath it, reached through any node between them.
func (n Node) Flatten() []Node {
	all := []Node{n}
	for _, child := range n.All() {
		if child.IsExpression() {
			all = append(all, child.Flatten()...)
			continue
		}
		for _, inner := range child.OutermostExpressions() {
			all = append(all, inner.Flatten()...)
		}
	}

	return all
}

// OutermostExpressions is the outermost expressions beneath this node, however deep the nodes between.
func (n Node) OutermostExpressions() []Node {
	var found []Node
	for _, child := range n.All() {
		if child.IsExpression() {
			found = append(found, child)
			continue
		}
		found = append(found, child.OutermostExpressions()...)
	}

	return found
}

// IsFunction says whether the node runs a body of its own: a member, an accessor, a local function, a lambda.
func (n Node) IsFunction() bool {
	return n.Is(functions...)
}

// FunctionBody is the body a function-like runs: its block, or the expression after `=>`; no node for one with
// neither.
func (n Node) FunctionBody() Node {
	if !n.IsFunction() {
		return Node{}
	}
	for _, child := range n.All() {
		if child.Is("Block", "ArrowExpressionClause") {
			return child
		}
	}

	return Node{}
}

// IsBailOut says whether the statement leaves its block.
func (n Node) IsBailOut() bool {
	return n.Is("ReturnStatement", "ThrowStatement", "ContinueStatement", "BreakStatement", "YieldBreakStatement")
}

// HasModifier says whether the modifier keyword is written on the node.
func (n Node) HasModifier(modifier string) bool {
	return n.Exists() && slices.Contains(n.Node().Modifiers, modifier)
}

// DeclaredNames is the name a declaration introduces; none for an expression.
func (n Node) DeclaredNames() []string {
	if n.Name() == "" || n.IsExpression() {
		return nil
	}

	return []string{n.Name()}
}

// IsReturn says whether the node hands a value back: a `return`, or the expression body of a member that
// returns one.
func (n Node) IsReturn() bool {
	return n.Is("ReturnStatement") || (n.Is("ArrowExpressionClause") && !n.isVoid())
}

// ReturnedValue is the value a return hands back; no node for anything else.
func (n Node) ReturnedValue() Node {
	if !n.IsReturn() {
		return Node{}
	}
	if expressions := n.Expressions(); len(expressions) > 0 {
		return expressions[0]
	}

	return Node{}
}

// IsExpressionStatement says whether the node runs an expression for its effect: a statement, or the
// expression body of a `void` member.
func (n Node) IsExpressionStatement() bool {
	return n.Is("ExpressionStatement") || (n.Is("ArrowExpressionClause") && n.isVoid())
}

// isVoid says whether an expression body's value is nothing.
func (n Node) isVoid() bool {
	expressions := n.Expressions()

	return len(expressions) > 0 && expressions[0].Type().Name() == "global::System.Void"
}

// IsCall says whether the node is an invocation.
func (n Node) IsCall() bool {
	return n.Is("InvocationExpression")
}

// IsConstant says whether the expression has a value the compiler fixes: a literal, an enum member, a `const`,
// arithmetic on them, or `typeof` a concrete type.
func (n Node) IsConstant() bool {
	return n.Exists() && n.Node().Constant
}

// IsLiteral says whether the node is a literal written in the source: a string, a number, a character, `true`,
// `null`, `default`.
func (n Node) IsLiteral() bool {
	return strings.HasSuffix(n.Kind(), "LiteralExpression")
}

// Text is a literal's value as written, unescaped, or an interpolated string's text part; empty for any other
// node.
func (n Node) Text() string {
	if !n.Exists() || n.Node().Value == nil {
		return ""
	}
	value := n.Node().Value
	if text, ok := value.Text(); ok {
		return text
	}
	if yes, ok := value.Bool(); ok {
		return strconv.FormatBool(yes)
	}

	return "null"
}

// Operator is the operator token of a binary, assignment or unary expression.
func (n Node) Operator() string {
	if !n.Exists() {
		return ""
	}

	return n.Node().Operator
}

// IsStep says whether the expression is in a `for` loop's step list.
func (n Node) IsStep() bool {
	return n.Exists() && slices.Contains(n.Node().Flags, "step")
}

// ForgivesNull says whether the node is a `!` over an operand declared nullable.
func (n Node) ForgivesNull() bool {
	extras := n.Node().Extras

	return n.Exists() && extras != nil && extras.CSharp != nil && extras.CSharp.ForgivesNull
}

// Symbol is a declaration's symbol id; empty for anything else.
func (n Node) Symbol() string {
	if !n.Exists() {
		return ""
	}

	return n.Node().Symbol
}

// IsInherited says whether the member overrides or implements another, decided against the whole hierarchy.
func (n Node) IsInherited() bool {
	return n.Exists() && n.Node().Inherited
}

// Target is the declaration a call or construction reaches; no target for one the compiler could not bind.
func (n Node) Target() CallTarget {
	if !n.Exists() || n.Node().Target == nil {
		return CallTarget{}
	}

	return CallTarget{n.Node().Target}
}

// Answers is what an expression answers with: each arm of a switch expression, either branch of a conditional,
// each read the same way since an arm can itself pick; the expression itself otherwise.
func (n Node) Answers() []Node {
	switch n.Kind() {
	case "SwitchExpression":
		var all []Node
		for _, arm := range n.All() {
			if arm.Is("SwitchExpressionArm") {
				expressions := arm.Expressions()
				all = append(all, expressions[len(expressions)-1].Answers()...)
			}
		}

		return all
	case "ConditionalExpression":
		expressions := n.Expressions()

		return append(expressions[1].Answers(), expressions[2].Answers()...)
	}

	return []Node{n}
}

// ComparisonSubject is what the condition compares with a constant: `status` in `status == Status.Paid` or in
// `status is Status.Paid`; no node for a condition that is not such a comparison.
func (n Node) ComparisonSubject() Node {
	switch {
	case n.Is("IsPatternExpression") && n.At(1).Is("ConstantPattern"):
		return n.At(0)
	case n.Is("IsExpression") && n.At(1).IsConstant():
		return n.At(0)
	case !n.Is("EqualsExpression"):
		return Node{}
	}
	expressions := n.Expressions()
	left, right := expressions[0], expressions[1]
	if left.IsConstant() == right.IsConstant() {
		return Node{}
	}
	if left.IsConstant() {
		return right
	}

	return left
}

// Package csharp is the C# engine: the reads a C# detector asks of a node the Roslyn bridge wrote, and the analyses over the whole program — resolution, state flow and the namespace graph.
package csharp

import (
	"slices"
	"strconv"
	"strings"

	"github.com/jessegall/code-commandments/engine"
)

var functions = []string{
	"MethodDeclaration", "ConstructorDeclaration", "DestructorDeclaration", "OperatorDeclaration", "ConversionOperatorDeclaration",
	"PropertyDeclaration", "IndexerDeclaration",
	"LocalFunctionStatement", "GetAccessorDeclaration", "SetAccessorDeclaration", "InitAccessorDeclaration",
	"AddAccessorDeclaration", "RemoveAccessorDeclaration", "ParenthesizedLambdaExpression", "SimpleLambdaExpression", "AnonymousMethodExpression",
}

// Node is a C# node as a detector asks about it, the C# twin of a backend NodeMatch: Where(engine.As(csharp.Node.IsCall)).
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

func (n Node) ChildrenIn(field string) []Node {
	return nodes(n.Match.ChildrenIn(field))
}

func (n Node) All() []Node {
	return nodes(n.Match.Children())
}

func (n Node) At(position int) Node {
	children := n.Match.Children()
	if position < 0 || position >= len(children) {
		return Node{}
	}

	return Node{children[position]}
}

func nodes(matches []engine.Match) []Node {
	viewed := make([]Node, len(matches))
	for at, match := range matches {
		viewed[at] = Node{match}
	}

	return viewed
}

func (n Node) Is(kinds ...string) bool {
	return n.Exists() && slices.Contains(kinds, n.Kind())
}

func (n Node) IsExpression() bool {
	return n.Exists() && n.Node().Role == "expression"
}

func (n Node) IsStatement() bool {
	return n.Exists() && (n.Node().Role == "statement" || n.Is("LocalFunctionStatement"))
}

func (n Node) IsTypeNode() bool {
	return n.Exists() && n.Node().Role == "type"
}

func (n Node) Children() []Node {
	var kept []Node
	for _, child := range n.All() {
		if !child.IsExpression() {
			kept = append(kept, child)
		}
	}

	return kept
}

func (n Node) Expressions() []Node {
	var kept []Node
	for _, child := range n.All() {
		if child.IsExpression() {
			kept = append(kept, child)
		}
	}

	return kept
}

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

func (n Node) IsFunction() bool {
	return n.Is(functions...)
}

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

func (n Node) IsBailOut() bool {
	return n.Is("ReturnStatement", "ThrowStatement", "ContinueStatement", "BreakStatement", "YieldBreakStatement")
}

func (n Node) HasModifier(modifier string) bool {
	return n.Exists() && slices.Contains(n.Node().Modifiers, modifier)
}

func (n Node) DeclaredNames() []string {
	if n.Name() == "" || n.IsExpression() {
		return nil
	}

	return []string{n.Name()}
}

func (n Node) IsReturn() bool {
	return n.Is("ReturnStatement") || (n.Is("ArrowExpressionClause") && !n.isVoid())
}

func (n Node) ReturnedValue() Node {
	if !n.IsReturn() {
		return Node{}
	}
	if expressions := n.Expressions(); len(expressions) > 0 {
		return expressions[0]
	}

	return Node{}
}

func (n Node) IsExpressionStatement() bool {
	return n.Is("ExpressionStatement") || (n.Is("ArrowExpressionClause") && n.isVoid())
}

func (n Node) isVoid() bool {
	expressions := n.Expressions()

	return len(expressions) > 0 && expressions[0].Type().Name() == "global::System.Void"
}

func (n Node) IsCall() bool {
	return n.Is("InvocationExpression")
}

func (n Node) IsConstant() bool {
	return n.Exists() && n.Node().Constant
}

func (n Node) IsLiteral() bool {
	return strings.HasSuffix(n.Kind(), "LiteralExpression")
}

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

func (n Node) Operator() string {
	return n.Match.Operator()
}

func (n Node) IsStep() bool {
	return n.Exists() && slices.Contains(n.Node().Flags, "step")
}

func (n Node) ForgivesNull() bool {
	extras := n.Node().Extras

	return n.Exists() && extras != nil && extras.CSharp != nil && extras.CSharp.ForgivesNull
}

func (n Node) ForgivesAnOutOfATest() bool {
	if !n.ForgivesNull() {
		return false
	}

	name := n.At(0).WithoutParentheses().Name()
	if name == "" {
		return false
	}

	for _, ancestor := range n.Ancestors() {
		if guards(ancestor, n, name) {
			return true
		}
	}

	return false
}

func guards(test, read Node, name string) bool {
	parts := test.All()
	if !test.Is("IfStatement") || len(parts) < 2 || !boundByATest(parts[0], name) {
		return false
	}

	branch, err := parts[1].Span()
	if err != nil {
		return false
	}

	at, err := read.Span()

	return err == nil && branch.Contains(at)
}

func boundByATest(condition Node, name string) bool {
	for _, part := range append(condition.Flatten(), condition) {
		if !part.IsCall() || negatedWithin(part, condition) {
			continue
		}

		for _, argument := range part.Arguments() {
			if declaresOut(argument, name) {
				return true
			}
		}
	}

	return false
}

func negatedWithin(call, condition Node) bool {
	for _, ancestor := range call.Ancestors() {
		if ancestor.Is("LogicalNotExpression") {
			return true
		}

		if ancestor.Match == condition.Match {
			return false
		}
	}

	return false
}

func declaresOut(argument Node, name string) bool {
	for _, part := range append(argument.All(), argument) {
		if part.Is("SingleVariableDesignation") && part.Name() == name {
			return true
		}
	}

	return false
}

func (n Node) Symbol() string {
	if !n.Exists() {
		return ""
	}

	return n.Node().Symbol
}

func (n Node) IsInherited() bool {
	return n.Exists() && n.Node().Inherited
}

func (n Node) FillsOutsideTheSolution() bool {
	return n.Exists() && n.Node().FillsOutside
}

func (n Node) Target() CallTarget {
	if !n.Exists() || n.Node().Target == nil {
		return CallTarget{}
	}

	return CallTarget{n.Node().Target}
}

func (n Node) Answers() []Node {
	switch n.Kind() {
	case "SwitchExpression":
		var all []Node
		for _, arm := range n.All() {
			if !arm.Is("SwitchExpressionArm") {
				continue
			}
			expressions := arm.Expressions()
			all = append(all, expressions[len(expressions)-1].Answers()...)
		}

		return all
	case "ConditionalExpression":
		expressions := n.Expressions()

		return append(expressions[1].Answers(), expressions[2].Answers()...)
	}

	return []Node{n}
}

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

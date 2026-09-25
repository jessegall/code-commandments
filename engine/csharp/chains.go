package csharp

import (
	"slices"
)

// ScalarConversions is the conversion the call hands each scalar parameter of the method it calls, by position: a
// cast to a scalar type, `X.Parse(…)`, `Convert.ToX(…)` or `.ToString()` written as the whole argument. None for a
// call that names its arguments, whose positions are not the parameters'.
func (n Node) ScalarConversions(program *Program) map[int]string {
	conversions := map[int]string{}
	if !n.Target().Exists() || !n.PassesByPosition() {
		return conversions
	}
	parameters := program.ParametersOf(n)
	for position, argument := range n.Arguments() {
		if position >= len(parameters) || !slices.Contains(scalars, parameters[position]) {
			continue
		}
		if conversion := argument.conversion(); conversion != "" {
			conversions[position] = conversion
		}
	}

	return conversions
}

// PassesByPosition says whether the call passes every argument by position, no `name:`, so argument n fills
// parameter n.
func (n Node) PassesByPosition() bool {
	list := n.firstChild("ArgumentList")

	return list.Exists() && !slices.ContainsFunc(list.All(), func(argument Node) bool { return argument.firstChild("NameColon").Exists() })
}

// FillsScalarAt says whether the parameter the call fills at the position is a scalar: text, a number, a date, a flag.
func (n Node) FillsScalarAt(program *Program, position int) bool {
	parameters := program.ParametersOf(n)

	return position < len(parameters) && slices.Contains(scalars, parameters[position])
}

// ProjectionRoot is the name a member chain hangs off: `request` in `request.ChannelId` or `order.Customer.Name`;
// no node for anything that is not a chain of member reads rooted at a name.
func (n Node) ProjectionRoot() Node {
	switch {
	case !n.Is("SimpleMemberAccessExpression"):
		return Node{}
	case n.At(0).Is("IdentifierName"):
		return n.At(0)
	}

	return n.At(0).ProjectionRoot()
}

// ReceiverName is the name the call is made on: `store` for `store.Persist(…)`; empty for a call made on nothing
// named.
func (n Node) ReceiverName() string {
	callee := n.At(0)
	if !callee.Is("SimpleMemberAccessExpression") {
		return ""
	}
	if callee.At(0).Is("IdentifierName") {
		return callee.At(0).Name()
	}

	return callee.At(0).ProjectionRoot().Name()
}

// ProjectionPath is the member path a projection reads off its root: `.Customer.Name` for `order.Customer.Name`;
// empty for anything that is not a projection.
func (n Node) ProjectionPath() string {
	switch {
	case !n.Is("SimpleMemberAccessExpression"):
		return ""
	case n.At(0).Is("IdentifierName"):
		return "." + n.At(1).Name()
	}

	return n.At(0).ProjectionPath() + "." + n.At(1).Name()
}

// CalledName is the name of the member the call calls: `Persist` for `store.Persist(…)` and `Persist(…)` alike.
func (n Node) CalledName() string {
	return n.At(0).ReferencedName()
}

// ReferencedName is the name the reference ends in: `Persist` for `Persist` and for `store.Persist` alike; empty
// for anything that is not a name or a member read.
func (n Node) ReferencedName() string {
	switch {
	case n.Is("IdentifierName"):
		return n.Name()
	case n.Is("SimpleMemberAccessExpression"):
		return n.At(1).Name()
	}

	return ""
}

// conversion is the conversion the expression is, when it is one: a cast to a scalar type, `X.Parse(…)` on a
// scalar type, `Convert.ToX(…)`, or a value's `.ToString()`; empty when it is none, or when what it converts is a
// constant: a literal written in another type is spelled, not held.
func (n Node) conversion() string {
	expression := n.WithoutParentheses()
	converted := expression.converted()
	if !converted.Exists() || converted.IsConstant() {
		return ""
	}
	var called CallTarget
	if expression.IsCall() {
		called = expression.Target()
	}
	switch {
	case expression.Is("CastExpression") && slices.Contains(scalars, expression.Type().Name()):
		return "(" + expression.Type().Name() + ")"
	case called.Name() == "ToString" && converted.Type().IsValueType():
		return "ToString()"
	case called.Name() == "Parse" && slices.Contains(scalars, called.Type()):
		return called.Type() + ".Parse"
	case called.Type() == "global::System.Convert":
		return "Convert." + called.Name()
	}

	return ""
}

// converted is what the cast or call converts: the operand of a cast, the receiver of a `.ToString()` taking no
// arguments, or the first argument of any other call; no node for anything else.
func (n Node) converted() Node {
	arguments := n.Arguments()
	switch {
	case n.Is("CastExpression"):
		if expressions := n.Expressions(); len(expressions) > 0 {
			return expressions[0]
		}

		return Node{}
	case !n.IsCall():
		return Node{}
	case n.Target().Name() == "ToString" && len(arguments) == 0 && n.At(0).Is("SimpleMemberAccessExpression"):
		return n.At(0).At(0)
	case len(arguments) >= 1:
		return arguments[0]
	}

	return Node{}
}

// Parameters is the parameters the member, local function or lambda declares, in order.
func (n Node) Parameters() []Node {
	return n.parameters()
}

// ChainRoot is the name a chain of reads and calls hangs off: `workflow` in `workflow.Graph.Node(id)` or
// `workflow.Graph.Nodes[id]`; no node for a chain that starts anywhere but a name.
func (n Node) ChainRoot() Node {
	switch {
	case n.Is("IdentifierName"):
		return n
	case n.Is("SimpleMemberAccessExpression", "InvocationExpression", "ElementAccessExpression", "SuppressNullableWarningExpression"):
		return n.At(0).ChainRoot()
	}

	return Node{}
}

// IsGuardThatOnlyThrows says whether the statement is an `if` with no `else` whose every statement throws: a
// guard that refuses and nothing more.
func (n Node) IsGuardThatOnlyThrows() bool {
	if !n.Is("IfStatement") || n.firstChild("ElseClause").Exists() {
		return false
	}
	children := n.Children()
	if len(children) == 0 {
		return false
	}
	thrown := []Node{children[0]}
	if children[0].Is("Block") {
		thrown = children[0].Children()
	}

	return len(thrown) > 0 && !slices.ContainsFunc(thrown, func(statement Node) bool { return !statement.Is("ThrowStatement") })
}

// LookupRootKeyedBy is the name the lookup is made on, when it is keyed solely by one of the keys: `workflow` for
// `workflow.Graph.Node(nodeId)` and `workflow.Graph.Nodes[nodeId]`; no node for anything that is not such a lookup.
func (n Node) LookupRootKeyedBy(keys []string) Node {
	arguments := n.Arguments()
	if len(arguments) != 1 || !arguments[0].Is("IdentifierName") || !slices.Contains(keys, arguments[0].Name()) {
		return Node{}
	}
	switch {
	case n.Is("ElementAccessExpression"):
		return n.At(0).ChainRoot()
	case n.IsCall() && n.At(0).Is("SimpleMemberAccessExpression"):
		return n.At(0).At(0).ChainRoot()
	}

	return Node{}
}

// IsResolverReturning says whether the body is the resolver of the local: the lookup kept in it, guards that only
// throw, and the local returned. That is the one place a resolution and its refusal belong.
func (n Node) IsResolverReturning(local string) bool {
	statements := n.Children()
	if len(statements) < 2 {
		return false
	}
	last := statements[len(statements)-1]
	if expressions := last.Expressions(); !last.Is("ReturnStatement") || len(expressions) == 0 || expressions[0].Name() != local {
		return false
	}

	return !slices.ContainsFunc(statements[1:len(statements)-1], func(guard Node) bool { return !guard.IsGuardThatOnlyThrows() })
}

// StringConstant is a string constant a type declares, with the name that holds it.
type StringConstant struct {
	Value string
	Name  string
}

// StringConstants is the string constants the type declares, `public const string Colon = ":"`, each value once,
// with the first name that holds it.
func (n Node) StringConstants() []StringConstant {
	var constants []StringConstant
	for _, field := range n.All() {
		if !field.IsConstField() {
			continue
		}
		for _, declarator := range field.Descendants() {
			if !declarator.Is("VariableDeclarator") {
				continue
			}
			value := declarator.firstChild("EqualsValueClause").At(0).WithoutParentheses()
			if !value.Is("StringLiteralExpression") || slices.ContainsFunc(constants, func(held StringConstant) bool { return held.Value == value.Text() }) {
				continue
			}
			constants = append(constants, StringConstant{Value: value.Text(), Name: declarator.Name()})
		}
	}

	return constants
}

// MayFillAContract says whether the type may fill a contract: it names a base class or an interface, it is
// `partial`, or one of its members implements or overrides one. Then it is a polymorphic component whose behaviour
// is meant to act on other types.
func (n Node) MayFillAContract() bool {
	return n.HasModifier("partial") || slices.ContainsFunc(n.All(), func(child Node) bool {
		return child.Is("BaseList") || (child.IsMember() && child.IsInherited())
	})
}

// LoopsOver is every `foreach` in the code over a collection the name holds: `foreach (var line in order.Lines)`.
func (n Node) LoopsOver(name string) []Node {
	var loops []Node
	for _, node := range n.Descendants() {
		if expressions := node.Expressions(); node.Is("ForEachStatement") && len(expressions) > 0 && expressions[0].ChainRoot().Name() == name {
			loops = append(loops, node)
		}
	}

	return loops
}

// QueriesCollectionOf says whether the code queries a collection the name holds from outside: a LINQ call such as
// `order.Lines.Sum(…)` on it.
func (n Node) QueriesCollectionOf(name string) bool {
	return slices.ContainsFunc(n.expressionParts(), func(call Node) bool {
		return call.IsCall() && call.Target().Type() == "global::System.Linq.Enumerable" &&
			call.At(0).Is("SimpleMemberAccessExpression") && call.At(0).At(0).ChainRoot().Name() == name
	})
}

// WritesMemberOf says whether the code writes one of the name's members: `order.Status = …`, `order.Total += …`,
// `order.Strikes++`.
func (n Node) WritesMemberOf(name string) bool {
	return slices.ContainsFunc(n.expressionParts(), func(write Node) bool {
		return write.IsWrite() && write.At(0).Is("SimpleMemberAccessExpression") && write.At(0).ChainRoot().Name() == name
	})
}

// MemberReachesOn is how often the code reads a member of each of the names: `order.Lines` counts once for `order`.
func (n Node) MemberReachesOn(names []string) map[string]int {
	reaches := map[string]int{}
	for _, read := range n.expressionParts() {
		if read.Is("SimpleMemberAccessExpression") && read.At(0).Is("IdentifierName") && slices.Contains(names, read.At(0).Name()) {
			reaches[read.At(0).Name()]++
		}
	}

	return reaches
}

// expressionParts is every expression in the code, however deep: its statements' expressions and theirs.
func (n Node) expressionParts() []Node {
	var parts []Node
	for _, expression := range n.OutermostExpressions() {
		parts = append(parts, expression.Flatten()...)
	}

	return parts
}

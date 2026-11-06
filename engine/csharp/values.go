package csharp

import (
	"slices"
	"strings"
)

const stringType = "global::System.String"

var genericExceptions = []string{"global::System.Exception", "global::System.SystemException", "global::System.ApplicationException", "global::System.InvalidOperationException"}

var dictionaries = []string{
	"global::System.Collections.Generic.Dictionary<", "global::System.Collections.Generic.IDictionary<",
	"global::System.Collections.Generic.IReadOnlyDictionary<", "global::System.Collections.Concurrent.ConcurrentDictionary<",
	"global::System.Collections.Immutable.ImmutableDictionary<", "global::System.Collections.Immutable.IImmutableDictionary<",
	"global::System.Collections.Frozen.FrozenDictionary<",
}

var jsonObjects = []string{"global::System.Text.Json.Nodes.JsonNode", "global::System.Text.Json.Nodes.JsonObject"}

var scalars = []string{
	"global::System.String", "global::System.Int16", "global::System.Int32", "global::System.Int64", "global::System.Decimal",
	"global::System.Double", "global::System.Single", "global::System.Boolean", "global::System.Char", "global::System.Byte",
	"global::System.DateTime", "global::System.DateTimeOffset", "global::System.DateOnly", "global::System.TimeOnly",
	"global::System.TimeSpan", "global::System.Guid",
}

func isDictionary(name string) bool {
	return slices.ContainsFunc(dictionaries, func(prefix string) bool { return strings.HasPrefix(name, prefix) })
}

func (n Node) IsBranchingConstruct() bool {
	return n.Is("IfStatement", "ForStatement", "ForEachStatement", "ForEachVariableStatement", "WhileStatement", "DoStatement", "SwitchStatement")
}

func (n Node) IsBroadCatch() bool {
	if !n.Is("CatchClause") || n.firstChild("CatchFilterClause").Exists() {
		return false
	}
	declaration := n.firstChild("CatchDeclaration")

	return !declaration.Exists() || declaration.Type().Name() == "global::System.Exception"
}

func (n Node) firstChild(kinds ...string) Node {
	for _, child := range n.All() {
		if child.Is(kinds...) {
			return child
		}
	}

	return Node{}
}

func (n Node) Swallows() bool {
	body := n.firstChild("Block")
	if !body.Exists() {
		return false
	}
	statements := body.Children()
	switch {
	case len(statements) > 1:
		return false
	case len(statements) == 0 || statements[0].Is("ContinueStatement"):
		return true
	case statements[0].Is("ReturnStatement"):
		value := statements[0].ReturnedValue()

		return !value.Exists() || value.IsAbsenceValue()
	}

	return false
}

func (n Node) IsAbsenceValue() bool {
	switch {
	case n.Is("NullLiteralExpression", "DefaultLiteralExpression", "DefaultExpression", "FalseLiteralExpression"):
		return true
	case n.Is("StringLiteralExpression"):
		return n.Text() == ""
	case n.Is("CollectionExpression"):
		return len(n.All()) == 0
	case n.IsCall():
		return n.Target().Name() == "Empty" && slices.Contains([]string{"global::System.Array", "global::System.Linq.Enumerable"}, n.Target().Type())
	}

	return false
}

func (n Node) IsGenericThrowWithMessage() bool {
	if !n.Is("ThrowStatement", "ThrowExpression") {
		return false
	}
	expressions := n.Expressions()
	if len(expressions) == 0 || !expressions[0].Is("ObjectCreationExpression", "ImplicitObjectCreationExpression") {
		return false
	}
	created := expressions[0]

	return slices.Contains(genericExceptions, created.Target().Type()) && len(created.firstChild("ArgumentList").All()) > 0
}

func (n Node) IsEmptyScalar() bool {
	switch {
	case n.Is("StringLiteralExpression"):
		return n.Text() == ""
	case n.Is("NumericLiteralExpression"):
		return n.Text() == "0"
	case n.Is("FalseLiteralExpression"):
		return true
	case n.Is("SimpleMemberAccessExpression"):
		return n.Type().Name() == stringType && n.At(1).Name() == "Empty"
	}

	return false
}

func (n Node) IsBlankStringDefault() bool {
	if !n.Is("Parameter", "PropertyDeclaration") {
		return false
	}

	return slices.ContainsFunc(n.All(), func(child Node) bool {
		return child.Is("EqualsValueClause") && child.At(0).IsBlankString()
	})
}

func (n Node) TestsBlanknessOf(name string) bool {
	switch {
	case n.IsCall():
		return n.Target().Type() == stringType && slices.Contains([]string{"IsNullOrEmpty", "IsNullOrWhiteSpace"}, n.Target().Name()) &&
			slices.ContainsFunc(n.Arguments(), func(argument Node) bool { return argument.names(name) })
	case n.Is("IsPatternExpression"):
		return n.At(0).names(name) && n.At(1).At(0).IsBlankString()
	case !n.Is("EqualsExpression", "NotEqualsExpression"):
		return false
	}
	left, right := n.At(0), n.At(1)

	return (left.names(name) && right.IsBlankString()) || (right.names(name) && left.IsBlankString()) ||
		(left.isLengthOf(name) && right.Text() == "0") || (right.isLengthOf(name) && left.Text() == "0")
}

func (n Node) IsBlankString() bool {
	return n.Type().Name() == stringType && n.IsEmptyScalar()
}

func (n Node) names(name string) bool {
	return (n.Is("IdentifierName") && n.Name() == name) ||
		(n.Is("SimpleMemberAccessExpression") && n.At(0).Is("ThisExpression") && n.At(1).Name() == name)
}

func (n Node) isLengthOf(name string) bool {
	return n.Is("SimpleMemberAccessExpression") && n.At(0).names(name) && n.At(1).Name() == "Length"
}

func (n Node) WithoutNullableUnwrap() Node {
	inner := n.WithoutParentheses()
	switch {
	case inner.Is("SuppressNullableWarningExpression"):
		return inner.At(0).WithoutNullableUnwrap()
	case inner.Is("SimpleMemberAccessExpression") && inner.At(1).Name() == "Value" && (inner.At(0).Type().IsNullable() || strings.HasSuffix(inner.At(0).Type().Name(), "?")):
		return inner.At(0).WithoutNullableUnwrap()
	}

	return inner
}

func (n Node) WithoutParentheses() Node {
	if n.Is("ParenthesizedExpression") {
		return n.At(0).WithoutParentheses()
	}

	return n
}

func (n Node) IsNestedConditional() bool {
	return n.Is("ConditionalExpression") && slices.ContainsFunc(n.All()[1:], func(branch Node) bool {
		return branch.WithoutParentheses().Is("ConditionalExpression")
	})
}

func (n Node) IsEmptyCollection() bool {
	switch {
	case n.Is("CollectionExpression"):
		return len(n.Expressions()) == 0 && !n.firstChild("ExpressionElement", "SpreadElement").Exists()
	case n.IsCall():
		return n.Target().Name() == "Empty" && slices.Contains([]string{"global::System.Linq.Enumerable", "global::System.Array"}, n.Target().Type())
	case n.Is("ObjectCreationExpression"):
		return len(n.Arguments()) == 0 && !slices.ContainsFunc(n.All(), func(child Node) bool { return strings.HasSuffix(child.Kind(), "InitializerExpression") })
	}

	return false
}

func (n Node) IsSameValueAs(other Node) bool {
	if n.IsEmptyScalar() && other.IsEmptyScalar() {
		return n.Type().Name() == other.Type().Name()
	}

	return n.IsLiteral() && n.Kind() == other.Kind() && n.Text() == other.Text()
}

func (n Node) Fallback() Node {
	if n.Is("CoalesceExpression") {
		return n.Expressions()[1]
	}
	if !n.Is("ConditionalExpression") {
		return Node{}
	}
	expressions := n.Expressions()
	switch {
	case expressions[0].IsNullTest():
		return expressions[1]
	case expressions[0].IsNotNullTest():
		return expressions[2]
	}

	return Node{}
}

func (n Node) IsLookup() bool {
	if n.Is("ElementAccessExpression") {
		return isDictionary(n.At(0).Type().Name())
	}

	return n.IsCall() && slices.Contains([]string{"TryGetValue", "GetValueOrDefault"}, n.Target().Name()) && isDictionary(n.mapReceiverType())
}

func (n Node) mapReceiverType() string {
	if n.At(0).Is("SimpleMemberAccessExpression") {
		return n.At(0).At(0).Type().Name()
	}

	return n.Target().Type()
}

func (n Node) Arguments() []Node {
	var arguments []Node
	for _, argument := range n.firstChild("ArgumentList", "BracketedArgumentList").All() {
		if expressions := argument.Expressions(); len(expressions) > 0 {
			arguments = append(arguments, expressions[0])
		}
	}

	return arguments
}

func (n Node) RethrowsUnchanged() bool {
	body := n.firstChild("Block")
	statements := body.Children()
	if !n.Is("CatchClause") || len(statements) != 1 || !statements[0].Is("ThrowStatement") {
		return false
	}
	if !n.isTheLastClause() {
		return false
	}

	return statements[0].throwsWhatWasCaughtBy(n)
}

func (n Node) isTheLastClause() bool {
	clauses := n.Parent().ChildrenIn("Catches")

	return len(clauses) > 0 && clauses[len(clauses)-1].Node() == n.Node()
}

func (n Node) throwsWhatWasCaughtBy(clause Node) bool {
	thrown := n.Expressions()
	if len(thrown) == 0 {
		return true
	}
	caught := clause.firstChild("CatchDeclaration").Name()

	return len(thrown) == 1 && thrown[0].Is("IdentifierName") && caught != "" && thrown[0].Name() == caught
}

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

func (n Node) firstChild(kinds ...string) Node {
	for _, child := range n.All() {
		if child.Is(kinds...) {
			return child
		}
	}

	return Node{}
}

func (n Node) IsBroadCatch() bool {
	if !n.Is("CatchClause") || n.firstChild("CatchFilterClause").Exists() {
		return false
	}
	declaration := n.firstChild("CatchDeclaration")
	return !declaration.Exists() || declaration.Type().Name() == "global::System.Exception"
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

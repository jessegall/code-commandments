package csharp

import (
	"slices"
	"strings"
)

// stringType is the string type, by the name the compiler gives it.
const stringType = "global::System.String"

// genericExceptions are the exceptions that name no failure a caller could catch by meaning.
var genericExceptions = []string{"global::System.Exception", "global::System.SystemException", "global::System.ApplicationException", "global::System.InvalidOperationException"}

// dictionaries are the dictionary types a lookup reads, by the name the compiler gives them without their
// arguments.
var dictionaries = []string{
	"global::System.Collections.Generic.Dictionary<", "global::System.Collections.Generic.IDictionary<",
	"global::System.Collections.Generic.IReadOnlyDictionary<", "global::System.Collections.Concurrent.ConcurrentDictionary<",
	"global::System.Collections.Immutable.ImmutableDictionary<", "global::System.Collections.Immutable.IImmutableDictionary<",
	"global::System.Collections.Frozen.FrozenDictionary<",
}

// jsonObjects are the JSON object types read by a string indexer.
var jsonObjects = []string{"global::System.Text.Json.Nodes.JsonNode", "global::System.Text.Json.Nodes.JsonObject"}

// scalars are the value types a clump is made of: what a parameter holds when it carries one datum, not an object.
var scalars = []string{
	"global::System.String", "global::System.Int16", "global::System.Int32", "global::System.Int64", "global::System.Decimal",
	"global::System.Double", "global::System.Single", "global::System.Boolean", "global::System.Char", "global::System.Byte",
	"global::System.DateTime", "global::System.DateTimeOffset", "global::System.DateOnly", "global::System.TimeOnly",
	"global::System.TimeSpan", "global::System.Guid",
}

// isDictionary says whether the type is one of the dictionaries a lookup reads.
func isDictionary(name string) bool {
	return slices.ContainsFunc(dictionaries, func(prefix string) bool { return strings.HasPrefix(name, prefix) })
}

// IsBranchingConstruct says whether the statement opens a choice the reader holds open: an `if`, a loop, a
// `switch`. A `try`, a `using` or a `lock` is a boundary, not a choice.
func (n Node) IsBranchingConstruct() bool {
	return n.Is("IfStatement", "ForStatement", "ForEachStatement", "ForEachVariableStatement", "WhileStatement", "DoStatement", "SwitchStatement")
}

// IsBroadCatch says whether the `catch` catches everything, with no type or `Exception` itself, and no `when`
// filter saying which failure it means.
func (n Node) IsBroadCatch() bool {
	if !n.Is("CatchClause") || n.firstChild("CatchFilterClause").Exists() {
		return false
	}
	declaration := n.firstChild("CatchDeclaration")

	return !declaration.Exists() || declaration.Type().Name() == "global::System.Exception"
}

// firstChild is the first child of one of the kinds, expressions included; no node when none is.
func (n Node) firstChild(kinds ...string) Node {
	for _, child := range n.All() {
		if child.Is(kinds...) {
			return child
		}
	}

	return Node{}
}

// Swallows says whether the `catch` makes the failure vanish: an empty body, a `continue`, or a `return` of
// nothing or of a value that says "nothing".
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

// IsAbsenceValue says whether the value says "nothing": `null`, `default`, `false`, `""`, an empty collection
// expression, or `Array.Empty<T>()` / `Enumerable.Empty<T>()`.
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

// IsGenericThrowWithMessage says whether the `throw` builds an exception that names no failure and describes
// it in a message written at the throw. The type is the constructor the compiler resolved, never the spelling.
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

// IsEmptyScalar says whether the value says "nothing" in a slot a type demands: `""`, `string.Empty`, `0`, `false`.
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

// IsBlankStringDefault says whether the parameter or property is defaulted to a blank string: `string note = ""`,
// `= string.Empty`.
func (n Node) IsBlankStringDefault() bool {
	if !n.Is("Parameter", "PropertyDeclaration") {
		return false
	}

	return slices.ContainsFunc(n.All(), func(child Node) bool {
		return child.Is("EqualsValueClause") && child.At(0).IsBlankString()
	})
}

// TestsBlanknessOf says whether the expression asks whether the name is blank: `name == ""`,
// `name != string.Empty`, `name is ""`, `name.Length == 0`, or `string.IsNullOrEmpty(name)` and its whitespace twin.
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

// IsBlankString says whether the value is the blank string: `""` or `string.Empty`.
func (n Node) IsBlankString() bool {
	return n.Type().Name() == stringType && n.IsEmptyScalar()
}

// names says whether the expression reads the name: the bare name, or `this.` it.
func (n Node) names(name string) bool {
	return (n.Is("IdentifierName") && n.Name() == name) ||
		(n.Is("SimpleMemberAccessExpression") && n.At(0).Is("ThisExpression") && n.At(1).Name() == name)
}

// isLengthOf says whether the expression is `name.Length`.
func (n Node) isLengthOf(name string) bool {
	return n.Is("SimpleMemberAccessExpression") && n.At(0).names(name) && n.At(1).Name() == "Length"
}

// WithoutNullableUnwrap is the expression with any unwrapping of a nullable taken off, `start!.Value`, `start!`
// and `start.Value` read as `start`, and parentheses with it.
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

// WithoutParentheses is the expression with any parentheses around it taken off.
func (n Node) WithoutParentheses() Node {
	if n.Is("ParenthesizedExpression") {
		return n.At(0).WithoutParentheses()
	}

	return n
}

// IsNestedConditional says whether the conditional has another conditional as one of its branches:
// `a ? b : c ? d : e`.
func (n Node) IsNestedConditional() bool {
	return n.Is("ConditionalExpression") && slices.ContainsFunc(n.All()[1:], func(branch Node) bool {
		return branch.WithoutParentheses().Is("ConditionalExpression")
	})
}

// IsEmptyCollection says whether the value is an empty collection written out: `[]`, `Enumerable.Empty<T>()`,
// `Array.Empty<T>()`, or a `new List<T>()` with nothing in it.
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

// IsSameValueAs says whether the literal is the same value as the other: the same literal written again, or two
// spellings of one empty value, `""` and `string.Empty`.
func (n Node) IsSameValueAs(other Node) bool {
	if n.IsEmptyScalar() && other.IsEmptyScalar() {
		return n.Type().Name() == other.Type().Name()
	}

	return n.IsLiteral() && n.Kind() == other.Kind() && n.Text() == other.Text()
}

// Fallback is what the expression falls back to when its value is missing: the right side of `x ?? fallback`,
// or the branch a null test takes on a miss; no node for anything else.
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

// IsLookup says whether the expression reads a dictionary by key, `TryGetValue`, `GetValueOrDefault` or its
// indexer, as the compiler resolved the receiver.
func (n Node) IsLookup() bool {
	if n.Is("ElementAccessExpression") {
		return isDictionary(n.At(0).Type().Name())
	}

	return n.IsCall() && slices.Contains([]string{"TryGetValue", "GetValueOrDefault"}, n.Target().Name()) && isDictionary(n.mapReceiverType())
}

// mapReceiverType is the type of the map a call is made on: the receiver's, since `GetValueOrDefault` is an
// extension method whose own type is the static class declaring it; for a call on nothing named, the method's own.
func (n Node) mapReceiverType() string {
	if n.At(0).Is("SimpleMemberAccessExpression") {
		return n.At(0).At(0).Type().Name()
	}

	return n.Target().Type()
}

// Arguments is the argument expressions a call, object creation or indexer is handed, in order.
func (n Node) Arguments() []Node {
	var arguments []Node
	for _, argument := range n.firstChild("ArgumentList", "BracketedArgumentList").All() {
		if expressions := argument.Expressions(); len(expressions) > 0 {
			arguments = append(arguments, expressions[0])
		}
	}

	return arguments
}

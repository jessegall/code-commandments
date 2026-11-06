package csharp

import (
	"slices"
)

func (n Node) ScalarConversions() map[int]string {
	conversions := map[int]string{}
	if !n.Target().Exists() || !n.PassesByPosition() {
		return conversions
	}
	parameters := n.Target().Parameters()
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

func (n Node) PassesByPosition() bool {
	list := n.firstChild("ArgumentList")

	return list.Exists() && !slices.ContainsFunc(list.All(), func(argument Node) bool { return argument.firstChild("NameColon").Exists() })
}

func (n Node) FillsScalarAt(position int) bool {
	parameters := n.Target().Parameters()

	return position < len(parameters) && slices.Contains(scalars, parameters[position])
}

func (n Node) ProjectionRoot() Node {
	switch {
	case !n.Is("SimpleMemberAccessExpression"):
		return Node{}
	case n.At(0).Is("IdentifierName"):
		return n.At(0)
	}

	return n.At(0).ProjectionRoot()
}

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

func (n Node) ProjectionPath() string {
	switch {
	case !n.Is("SimpleMemberAccessExpression"):
		return ""
	case n.At(0).Is("IdentifierName"):
		return "." + n.At(1).Name()
	}

	return n.At(0).ProjectionPath() + "." + n.At(1).Name()
}

func (n Node) CalledName() string {
	return n.At(0).ReferencedName()
}

func (n Node) ReferencedName() string {
	switch {
	case n.Is("IdentifierName"):
		return n.Name()
	case n.Is("SimpleMemberAccessExpression"):
		return n.At(1).Name()
	}

	return ""
}

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

func (n Node) Parameters() []Node {
	return n.parameters()
}

func (n Node) ChainRoot() Node {
	switch {
	case n.Is("IdentifierName"):
		return n
	case n.Is("SimpleMemberAccessExpression", "InvocationExpression", "ElementAccessExpression", "SuppressNullableWarningExpression"):
		return n.At(0).ChainRoot()
	}

	return Node{}
}

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

func (n Node) MayFillAContract() bool {
	return n.HasModifier("partial") || slices.ContainsFunc(n.All(), func(child Node) bool {
		return child.Is("BaseList") || (child.IsMember() && child.IsInherited())
	})
}

const enumerable = "global::System.Linq.Enumerable"

func (n Node) LoopsOver(name string) []Node {
	var loops []Node
	for _, node := range n.Descendants() {
		if expressions := node.Expressions(); node.Is("ForEachStatement") && len(expressions) > 0 && expressions[0].ChainRoot().Name() == name {
			loops = append(loops, node)
		}
	}

	return loops
}

func (n Node) QueriesCollectionOf(name string) bool {
	return slices.ContainsFunc(n.expressionParts(), func(call Node) bool {
		return call.IsCall() && queries(call) && call.At(0).At(0).ChainRoot().Name() == name
	})
}

func (n Node) QueriedName() string {
	argument := n.Parent()
	if !argument.Is("Argument") {
		return ""
	}

	call := argument.Parent().Parent()
	if !call.IsCall() || !queries(call) {
		return ""
	}

	return call.At(0).At(0).ChainRoot().Name()
}

func queries(call Node) bool {
	return call.Target().Type() == enumerable && call.At(0).Is("SimpleMemberAccessExpression")
}

func (n Node) LambdaNames() []string {
	var names []string
	for _, part := range append(n.All(), n.firstChild("ParameterList").All()...) {
		if part.Is("Parameter") && part.Name() != "" {
			names = append(names, part.Name())
		}
	}

	return names
}

func (n Node) WritesMemberOf(name string) bool {
	return slices.ContainsFunc(n.expressionParts(), func(write Node) bool {
		return write.IsWrite() && write.At(0).Is("SimpleMemberAccessExpression") && write.At(0).ChainRoot().Name() == name
	})
}

func (n Node) MemberReadsOn(names []string) map[string][]Node {
	reads := map[string][]Node{}
	for _, read := range n.expressionParts() {
		if read.Is("SimpleMemberAccessExpression") && read.At(0).Is("IdentifierName") && slices.Contains(names, read.At(0).Name()) {
			reads[read.At(0).Name()] = append(reads[read.At(0).Name()], read)
		}
	}

	return reads
}

func (n Node) MemberReachesOn(names []string) map[string]int {
	reaches := map[string]int{}
	for name, reads := range n.MemberReadsOn(names) {
		reaches[name] = len(reads)
	}

	return reaches
}

func (n Node) expressionParts() []Node {
	var parts []Node
	for _, expression := range n.OutermostExpressions() {
		parts = append(parts, expression.Flatten()...)
	}

	return parts
}

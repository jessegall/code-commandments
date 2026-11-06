package csharp

import (
	"crypto/sha1"
	"encoding/hex"
	"slices"
	"sort"
	"strings"
)

func (n Node) isNullPattern() bool {
	switch {
	case n.Is("ConstantPattern"):
		return n.At(0).Is("NullLiteralExpression")
	case n.Is("NotPattern"):
		return n.At(0).isNullPattern()
	}

	return false
}

func (n Node) OwnMembersTestedForNull(own []string) []string {
	var tested []string
	for _, expression := range n.OutermostExpressions() {
		for _, test := range expression.Flatten() {
			var sides []Node
			switch {
			case test.Is("IsPatternExpression") && test.At(1).isNullPattern():
				sides = []Node{test.At(0)}
			case test.Is("EqualsExpression", "NotEqualsExpression") && slices.ContainsFunc(test.All(), func(side Node) bool { return side.Is("NullLiteralExpression") }):
				sides = test.All()
			}
			for _, side := range sides {
				name := side.WithoutParentheses().MemberName()
				if !slices.Contains(own, name) || slices.Contains(tested, name) {
					continue
				}
				tested = append(tested, name)
			}
		}
	}

	return tested
}

func (n Node) ReadsMember(names []string) bool {
	return slices.ContainsFunc(names, n.names)
}

func (n Node) writtenTypeNode() Node {
	for _, child := range n.All() {
		if child.IsTypeNode() {
			return child
		}
	}

	return Node{}
}

func (n Node) ReturnsPositionalTuple() bool {
	if !n.Is("MethodDeclaration", "LocalFunctionStatement", "PropertyDeclaration") {
		return false
	}
	result := n.writtenTypeNode()
	if result.Is("NullableType") {
		result = result.At(0)
	}
	generic := result
	if generic.Is("QualifiedName") {
		all := generic.All()
		generic = all[len(all)-1]
	}
	awaited := result
	if n.HasModifier("async") && generic.Is("GenericName") {
		awaited = generic.At(0).At(0)
	}
	if !awaited.Is("TupleType") || slices.ContainsFunc(awaited.All(), func(element Node) bool { return element.Name() != "" }) {
		return false
	}
	var types []string
	for _, element := range awaited.All() {
		hash := ExpressionHash(element.At(0))
		if slices.Contains(types, hash) {
			return true
		}
		types = append(types, hash)
	}

	return false
}

func (n Node) BlankArgumentPositions() []int {
	var positions []int
	for position, argument := range n.firstChild("ArgumentList").All() {
		if argument.firstChild("NameColon").Exists() {
			break
		}
		if expressions := argument.Expressions(); len(expressions) > 0 && expressions[0].IsBlankString() {
			positions = append(positions, position)
		}
	}

	return positions
}

func (n Node) MembersInitializedBlank() []string {
	var blank []string
	for _, initializer := range n.All() {
		if !initializer.Is("ObjectInitializerExpression") {
			continue
		}
		for _, entry := range initializer.All() {
			if entry.Is("SimpleAssignmentExpression") && entry.At(0).Is("IdentifierName") && entry.At(1).IsBlankString() {
				blank = append(blank, entry.At(0).Name())
			}
		}
	}

	return blank
}

func (n Node) parameters() []Node {
	var parameters []Node
	for _, list := range n.All() {
		if list.Is("ParameterList") {
			parameters = append(parameters, list.All()...)
		}
	}

	return parameters
}

func (n Node) RequiredTextNames() []string {
	var names []string
	members := n.parameters()
	for _, property := range n.All() {
		if property.Is("PropertyDeclaration") {
			members = append(members, property)
		}
	}
	for _, member := range members {
		if member.Name() != "" && slices.ContainsFunc(member.All(), func(written Node) bool { return written.Is("PredefinedType") && written.Name() == "string" }) {
			names = append(names, member.Name())
		}
	}

	return names
}

func (n Node) IsStateMember() bool {
	return n.Is("FieldDeclaration") || (n.Is("PropertyDeclaration") && !n.HasModifier("abstract") && n.isStoredProperty())
}

func (n Node) IsConstantMember() bool {
	return n.Is("FieldDeclaration") && (n.HasModifier("const") || (n.HasModifier("static") && n.HasModifier("readonly")))
}

func (n Node) IsInstanceStateMember() bool {
	return n.IsStateMember() && !n.HasModifier("static") && !n.HasModifier("const")
}

func (n Node) isStoredProperty() bool {
	accessors := n.firstChild("AccessorList").All()

	return n.firstChild("EqualsValueClause").Exists() ||
		(len(accessors) > 0 && !slices.ContainsFunc(accessors, func(accessor Node) bool { return len(accessor.All()) > 0 }))
}

func (n Node) IsStatePredicate() bool {
	declared := n.writtenTypeNode()
	parameters := n.firstChild("ParameterList")

	return declared.Is("PredefinedType") && declared.Name() == "bool" &&
		(n.Is("PropertyDeclaration") || (n.Is("MethodDeclaration") && parameters.Exists() && len(parameters.All()) == 0))
}

func (n Node) SwitchesOnAFlag() bool {
	var flags []string
	for _, parameter := range n.parameters() {
		if parameter.Type().Name() == "global::System.Boolean" {
			flags = append(flags, parameter.Name())
		}
	}
	body := n.FunctionBody()
	if !body.Exists() || body.soleReturnedValue().WithoutParentheses().isValueChoice() {
		return false
	}
	condition := body.twoWayCondition()

	return condition.Exists() && slices.ContainsFunc(flags, condition.decidesOn)
}

func (n Node) isValueChoice() bool {
	if !n.Is("ConditionalExpression") {
		return false
	}
	for _, side := range []Node{n.At(1), n.At(2)} {
		side = side.WithoutParentheses()
		if !side.IsConstant() && !side.isValueChoice() {
			return false
		}
	}

	return true
}

func (n Node) twoWayCondition() Node {
	if all := n.All(); n.Is("Block") && len(all) == 1 && all[0].Is("IfStatement") && all[0].firstChild("ElseClause").Exists() {
		return all[0].At(0)
	}
	if returned := n.soleReturnedValue().WithoutParentheses(); returned.Is("ConditionalExpression") {
		return returned.At(0)
	}

	return Node{}
}

func (n Node) soleReturnedValue() Node {
	switch all := n.All(); {
	case n.Is("ArrowExpressionClause"):
		return n.At(0)
	case n.Is("Block") && len(all) == 1 && all[0].Is("ReturnStatement"):
		return all[0].At(0)
	}

	return Node{}
}

func (n Node) decidesOn(name string) bool {
	test := n.WithoutParentheses()

	return test.names(name) || (test.Is("LogicalNotExpression") && test.At(0).WithoutParentheses().names(name))
}

func (n Node) Conjuncts() []Node {
	if !n.Is("LogicalAndExpression") {
		return []Node{n}
	}
	var conjuncts []Node
	for _, side := range n.All() {
		conjuncts = append(conjuncts, side.WithoutParentheses().Conjuncts()...)
	}

	return conjuncts
}

func (n Node) IsSubstantiveGuard() bool {
	if !n.Is("LogicalAndExpression") {
		return false
	}
	conjuncts := n.Conjuncts()
	reaches := 0
	for _, conjunct := range conjuncts {
		for _, node := range conjunct.Flatten() {
			if node.Is("SimpleMemberAccessExpression") {
				reaches++
			}
		}
	}

	return reaches >= 2 && slices.ContainsFunc(conjuncts, func(conjunct Node) bool { return !conjunct.Is("IsPatternExpression", "IsExpression") })
}

func (n Node) IsTypeCheck() bool {
	if n.Is("IsExpression") {
		return true
	}
	if !n.Is("IsPatternExpression") {
		return false
	}
	pattern := n.At(1)

	return pattern.Is("DeclarationPattern", "TypePattern") || (pattern.Is("RecursivePattern") && pattern.writtenTypeNode().Exists())
}

func (n Node) IsTypeNarrowingGuard() bool {
	if !n.Is("LogicalAndExpression") {
		return false
	}
	checks := 0
	for _, conjunct := range n.Conjuncts() {
		if conjunct.IsTypeCheck() {
			checks++
		}
	}

	return checks >= 2
}

func (n Node) GuardFingerprint() string {
	var hashes []string
	for _, conjunct := range n.Conjuncts() {
		hashes = append(hashes, ExpressionHash(conjunct))
	}
	sort.Strings(hashes)
	sum := sha1.Sum([]byte(strings.Join(hashes, "|")))

	return hex.EncodeToString(sum[:])
}

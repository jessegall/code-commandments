package csharp

import (
	"slices"
	"sort"
	"strings"

	"github.com/jessegall/code-commandments/prose"
)

func (n Node) ConstantChanges() []string {
	if !n.Is("WithExpression") || n.At(0).Is("ThisExpression") {
		return nil
	}
	var slots []string
	for _, change := range n.At(1).All() {
		if !change.Is("SimpleAssignmentExpression") {
			continue
		}
		if !change.At(1).IsConstant() {
			return nil
		}
		slots = append(slots, change.At(0).Name()+"="+ExpressionHash(change.At(1)))
	}
	sort.Strings(slots)

	return slots
}

func (n Node) switchPatterns() []Node {
	var patterns []Node
	switch {
	case n.Is("SwitchExpression"):
		for _, arm := range n.All()[1:] {
			patterns = append(patterns, arm.At(0))
		}
	case n.Is("SwitchStatement"):
		for _, section := range n.All()[1:] {
			for _, label := range section.All() {
				if !label.Is("CasePatternSwitchLabel", "CaseSwitchLabel") {
					continue
				}
				patterns = append(patterns, label.At(0))
			}
		}
	}

	return patterns
}

func typeNamesIn(pattern Node) []string {
	var names []string
	for _, part := range pattern.All() {
		if !part.IsTypeNode() || !part.Type().Exists() {
			continue
		}
		names = append(names, part.Type().Name())
	}

	return names
}

func (n Node) SwitchedTypes() []string {
	var types, named []string
	for _, pattern := range n.switchPatterns() {
		if pattern.Is("DeclarationPattern", "TypePattern", "RecursivePattern") {
			types = append(types, typeNamesIn(pattern)...)
		}
		if pattern.isTypeName() {
			named = append(named, pattern.Type().Name())
		}
	}

	return append(types, named...)
}

func (n Node) isTypeName() bool {
	return n.Is("IdentifierName", "QualifiedName") && !n.IsConstant() && n.Type().Exists()
}

func (n Node) SwitchedSubjectType() string {
	return strings.TrimSuffix(n.At(0).Type().Name(), "?")
}

func (n Node) IsSwitchOverOwnCases() bool {
	subject := n.SwitchedSubjectType()
	switched := n.SwitchedTypes()

	return len(switched) > 0 && !slices.ContainsFunc(switched, func(tested string) bool { return !strings.HasPrefix(tested, subject+".") })
}

func (n Node) IsTranslatingEveryArm() bool {
	if !n.Is("SwitchExpression") {
		return false
	}
	var arms []Node
	for _, arm := range n.All()[1:] {
		if !arm.At(0).Is("DiscardPattern") {
			arms = append(arms, arm)
		}
	}

	return len(arms) > 0 && !slices.ContainsFunc(arms, func(arm Node) bool { return !translates(arm) })
}

func (n Node) Designation() string {
	for _, part := range append(n.All(), n) {
		if part.Is("SingleVariableDesignation") && part.Name() != "" {
			return part.Name()
		}
	}

	return ""
}

func translates(arm Node) bool {
	all := arm.All()
	answer := all[len(all)-1].WithoutParentheses()

	if !answer.Is(translatesTo...) {
		return false
	}

	if answer.Is("IdentifierName") {
		return true
	}

	matched := arm.At(0).Designation()

	return matched == "" || answer.ChainRoot().Name() != matched
}

var translatesTo = []string{
	"ObjectCreationExpression", "ImplicitObjectCreationExpression",
	"StringLiteralExpression", "NumericLiteralExpression", "CharacterLiteralExpression",
	"TrueLiteralExpression", "FalseLiteralExpression", "NullLiteralExpression",
	"TupleExpression", "SimpleMemberAccessExpression", "IdentifierName",
}

func (n Node) StoresInOwnState() bool {
	if !n.Is("SwitchStatement") {
		return false
	}

	sections := n.ChildrenIn("Sections")
	if len(sections) == 0 {
		return false
	}

	for _, section := range sections {
		for _, statement := range section.ChildrenIn("Statements") {
			if !writesOwnState(statement) {
				return false
			}
		}
	}

	return true
}

func writesOwnState(statement Node) bool {
	switch {
	case statement.Is("BreakStatement"):
		return true
	case statement.Is("Block"):
		return !slices.ContainsFunc(statement.ChildrenIn("Statements"), func(inside Node) bool { return !writesOwnState(inside) })
	case statement.Is("ExpressionStatement"):
		assigned := statement.At(0)

		return assigned.Is("SimpleAssignmentExpression") && assigned.At(0).MemberName() != ""
	}

	return false
}

func (n Node) MemberName() string {
	switch {
	case n.Is("IdentifierName"):
		return n.Name()
	case n.Is("SimpleMemberAccessExpression") && n.At(0).Is("ThisExpression"):
		return n.At(1).Name()
	}

	return ""
}

func (n Node) IsShadowedIn(member Node) bool {
	return n.Is("IdentifierName") && slices.Contains(member.OwnNames(), n.Name())
}

func (n Node) OwnStateReferences(own []string) []Node {
	if n.Is("SimpleMemberAccessExpression") {
		if n.At(0).Is("ThisExpression") && slices.Contains(own, n.At(1).Name()) {
			return []Node{n}
		}

		return n.At(0).OwnStateReferences(own)
	}
	if n.Is("IdentifierName") {
		if n.IsExpression() && slices.Contains(own, n.Name()) {
			return []Node{n}
		}

		return nil
	}
	initializing := strings.HasSuffix(n.Kind(), "InitializerExpression")
	var references []Node
	for _, part := range n.All() {
		if initializing && part.Is("SimpleAssignmentExpression") {
			part = part.At(1)
		}
		references = append(references, part.OwnStateReferences(own)...)
	}

	return references
}

func (n Node) DeclaresNullableState() bool {
	if n.Is("FieldDeclaration") {
		return n.At(0).At(0).Is("NullableType")
	}

	return n.writtenTypeNode().Is("NullableType")
}

func (n Node) holders() []Node {
	switch {
	case n.Is("FieldDeclaration"):
		var declarators []Node
		for _, node := range n.Descendants() {
			if node.Is("VariableDeclarator") {
				declarators = append(declarators, node)
			}
		}

		return declarators
	case n.Is("PropertyDeclaration"):
		return []Node{n}
	}

	return nil
}

func (n Node) HeldStateNames() []string {
	holders := []Node{n}
	if n.Is("FieldDeclaration") {
		holders = n.holders()
	}
	var names []string
	for _, holder := range holders {
		if holder.Name() != "" {
			names = append(names, holder.Name())
		}
	}

	return names
}

func (n Node) InitialValuesOf(name string) []Node {
	var values []Node
	for _, holder := range n.holders() {
		if holder.Name() != name {
			continue
		}
		for _, clause := range holder.All() {
			if clause.Is("EqualsValueClause") {
				values = append(values, clause.At(0))
			}
		}
	}

	return values
}

func (n Node) SignatureWords() []string {
	names := []string{n.Name()}
	for _, child := range n.All() {
		if child.Is("Block", "ArrowExpressionClause", "AttributeList") || child.IsMember() {
			continue
		}
		names = append(names, child.Name())
		for _, part := range child.Descendants() {
			names = append(names, part.Name())
		}
	}
	var written []string
	for _, name := range names {
		if name != "" {
			written = append(written, name)
		}
	}

	return prose.Words(strings.Join(written, " "))
}

func (n Node) NamespaceName() string {
	var parts []string
	for around := n; around.Exists(); around = around.Parent() {
		if around.Is("NamespaceDeclaration", "FileScopedNamespaceDeclaration") {
			parts = append([]string{around.Child("Name").Written()}, parts...)
		}
	}

	return strings.Join(parts, ".")
}

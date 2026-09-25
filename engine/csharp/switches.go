package csharp

import (
	"slices"
	"sort"
	"strings"

	"github.com/jessegall/code-commandments/prose"
)

// ConstantChanges is what the `with` copy changes, when every change is a constant, `Status=…` for
// `order with { Status = Status.Shipped }`, sorted; none for a change worked out at the site, for a copy of
// `this`, or for anything but a `with`.
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

// switchPatterns is the tests of the switch's cases: each arm's pattern, each `case` label's value.
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
				if label.Is("CasePatternSwitchLabel", "CaseSwitchLabel") {
					patterns = append(patterns, label.At(0))
				}
			}
		}
	}

	return patterns
}

// SwitchedTypes is the types the `switch` asks its subject to be, one per case that tests a type: `Circle` and
// `Square` in `shape switch { Circle c => …, Square s => … }`, as the compiler resolved them.
func (n Node) SwitchedTypes() []string {
	var types, named []string
	for _, pattern := range n.switchPatterns() {
		if pattern.Is("DeclarationPattern", "TypePattern", "RecursivePattern") {
			for _, part := range pattern.All() {
				if part.IsTypeNode() && part.Type().Exists() {
					types = append(types, part.Type().Name())
				}
			}
		}
		if pattern.isTypeName() {
			named = append(named, pattern.Type().Name())
		}
	}

	return append(types, named...)
}

// isTypeName says whether a `case` label's value names a type: `Circle` in `case Circle:`. A label's value must
// be a constant, so a name there that is none can only be a type.
func (n Node) isTypeName() bool {
	return n.Is("IdentifierName", "QualifiedName") && !n.IsConstant() && n.Type().Exists()
}

// SwitchedSubjectType is the type of the value the `switch` decides on, as the compiler resolved it, nullability
// aside.
func (n Node) SwitchedSubjectType() string {
	return strings.TrimSuffix(n.At(0).Type().Name(), "?")
}

// IsSwitchOverOwnCases says whether every type the switch tests is declared inside the type it switches on: a
// closed union written as one type, meant to be consumed by switching over its cases, as an enum is.
func (n Node) IsSwitchOverOwnCases() bool {
	subject := n.SwitchedSubjectType()
	switched := n.SwitchedTypes()

	return len(switched) > 0 && !slices.ContainsFunc(switched, func(tested string) bool { return !strings.HasPrefix(tested, subject+".") })
}

// IsTranslatingEveryArm says whether every arm of the switch expression builds a new object: a mapper turning
// each type into another.
func (n Node) IsTranslatingEveryArm() bool {
	if !n.Is("SwitchExpression") {
		return false
	}
	var answers []Node
	for _, arm := range n.All()[1:] {
		if !arm.At(0).Is("DiscardPattern") {
			all := arm.All()
			answers = append(answers, all[len(all)-1].WithoutParentheses())
		}
	}

	return len(answers) > 0 && !slices.ContainsFunc(answers, func(answer Node) bool {
		return !answer.Is("ObjectCreationExpression", "ImplicitObjectCreationExpression")
	})
}

// MemberName is the own member the reference names: `cents` for a bare `cents` or for `this.cents`; empty for
// anything else, `other.cents` included.
func (n Node) MemberName() string {
	switch {
	case n.Is("IdentifierName"):
		return n.Name()
	case n.Is("SimpleMemberAccessExpression") && n.At(0).Is("ThisExpression"):
		return n.At(1).Name()
	}

	return ""
}

// IsShadowedIn says whether the bare name is one the member declares a local or parameter of its own under, so
// it does not name the type's field. `this.x` always names the field.
func (n Node) IsShadowedIn(member Node) bool {
	return n.Is("IdentifierName") && slices.Contains(member.OwnNames(), n.Name())
}

// OwnStateReferences is the places in the expression that name one of the type's own members, bare or through
// `this.`, never the member side of `other.x`, nor the member an object initializer sets on the object it builds.
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

// DeclaresNullableState says whether the field or property is declared with a type that admits `null`: `Batch?`,
// `int?`.
func (n Node) DeclaresNullableState() bool {
	if n.Is("FieldDeclaration") {
		return n.At(0).At(0).Is("NullableType")
	}

	return n.writtenTypeNode().Is("NullableType")
}

// holders is the declarators of a field, or the property itself.
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

// HeldStateNames is the names the field or property holds state under.
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

// InitialValuesOf is the value the field or property declaration starts the name with, where it gives one.
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

// SignatureWords is the words the member's signature already says: its name, its parameters' and type
// parameters' names, and the names of the types it takes and returns; what a doc comment repeating it would say.
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

// NamespaceName is the name of the namespace the declaration declares, as C# code writes it: `Shop.Orders`,
// nested declarations joined.
func (n Node) NamespaceName() string {
	var parts []string
	for around := n; around.Exists(); around = around.Parent() {
		if around.Is("NamespaceDeclaration", "FileScopedNamespaceDeclaration") {
			parts = append([]string{around.Child("Name").Written()}, parts...)
		}
	}

	return strings.Join(parts, ".")
}

package engine

import "slices"

func (m Match) Methods() []Match {
	if !m.Is(TypeDeclaration) {
		return nil
	}
	if m.grammar().MethodOwner == nil {
		return slices.DeleteFunc(m.Members(), func(member Match) bool { return !member.Is(Function) })
	}
	return methodsByOwner(m.Codebase().Of(m.Source().Language()))[m.Identity()]
}

func (m Match) DeclaringType() Match {
	if owner := m.EnclosingType(); owner.Exists() {
		return owner
	}
	method := m
	if !method.Is(Function) {
		method = m.EnclosingFunction()
	}
	if method.grammar().MethodOwner == nil {
		return Match{}
	}
	for _, declaration := range method.Codebase().Declarations(method.methodOwner()) {
		if declaration.Is(TypeDeclaration) {
			return declaration
		}
	}
	return Match{}
}

func (m Match) IsMiddleMan() bool {
	kind := m.TypeKind()
	if kind != "class" && kind != "struct" || m.HasModifier("abstract") || len(m.Extends()) != 0 || len(m.Implements()) != 0 {
		return false
	}
	field := ""
	forwarded := 0
	for _, method := range m.Methods() {
		if method.IsConstructor() || method.IsImplicit() {
			continue
		}
		delegate := method.forwardedField()
		if delegate == "" || field != "" && delegate != field {
			return false
		}
		field = delegate
		forwarded++
	}
	return forwarded >= 2
}

func (m Match) forwardedField() string {
	body := m.Body()
	if len(body) != 1 || m.isPrivateMember() {
		return ""
	}
	call := statementValue(body[0])
	receiver := call.Receiver()
	if !call.Is(Call) || !receiver.Is(MemberAccess) || !receiver.Receiver().isSelfReceiver() {
		return ""
	}
	if !receiver.hasLocalTypeDeclaration() {
		return ""
	}
	arguments := call.Arguments()
	parameters := slices.DeleteFunc(m.Parameters(), func(parameter Match) bool { return parameter.isSelfReceiver() })
	if len(arguments) != len(parameters) {
		return ""
	}
	for at, argument := range arguments {
		if !argument.Is(Identifier) || argument.Name() != parameters[at].Name() {
			return ""
		}
	}
	return receiver.MemberName()
}

func statementValue(statement Match) Match {
	if value := statement.returnedValue(); value.Exists() {
		return value
	}
	if children := statement.Children(); statement.Is(ExpressionStatement) && len(children) == 1 {
		return children[0]
	}
	return Match{}
}

func (m Match) RefusesInheritedMethod() bool {
	body := m.Body()
	return m.IsOverride() && len(body) == 1 && statementThrows(body[0])
}

func statementThrows(statement Match) bool {
	if statement.Is(Throw) || statement.Thrown().Exists() {
		return true
	}
	return statementValue(statement).Is(Throw)
}

func (m Match) ReachesThroughOwnedTypes() bool {
	if !m.Is(Call) && !m.Is(MemberAccess) || !m.Receiver().Exists() {
		return false
	}
	parent := m.Parent()
	if (parent.Is(MemberAccess) || parent.Is(Call)) && parent.Receiver().node == m.node ||
		parent.Is(Call) && parent.calledNode().node == m.node {
		return false
	}
	owner := m.EnclosingFunction().methodOwner()
	seen := map[string]bool{}
	for receiver := m.Receiver(); receiver.Exists(); receiver = receiver.Receiver() {
		if typed := receiver.typeDeclaration(); typed.Exists() && typed.Identity() != owner {
			seen[typed.Identity()] = true
		}
	}
	return len(seen) >= 3
}

func (m Match) ReadsUndeclaredMember() bool {
	if !m.Is(MemberAccess) && !m.Is(Call) {
		return false
	}
	if lookup := m.grammar().MemberLookup; lookup != nil {
		symbol, grants := lookup(m)
		types, known := m.Types()
		return known && grants != nil && TypeLacksMember(types, symbol, grants)
	}
	if !m.Is(MemberAccess) || m.MemberName() == "" || m.Declared().Exists() {
		return false
	}
	receiver := m.Receiver()
	declaration := receiver.typeDeclaration()
	types, known := receiver.Types()
	if !known || !declaration.Exists() {
		return false
	}
	name := m.MemberName()
	return TypeLacksMember(types, declaration.Identity(), func(owner string) bool {
		declared, _ := types.Declaration(owner)
		if dynamic := declared.grammar().DynamicMembers; dynamic != nil && dynamic(declared) {
			return true
		}
		if slices.ContainsFunc(declared.Members(), func(member Match) bool { return member.DeclaredName() == name }) {
			return true
		}
		return slices.ContainsFunc(declared.Descendants(), func(node Match) bool {
			left, _ := node.assignmentSides()
			return left.Is(MemberAccess) && left.Receiver().isSelfReceiver() && left.MemberName() == name
		})
	})
}

func (m Match) isPrivateMember() bool {
	if read := m.grammar().PrivateMember; read != nil {
		return read(m)
	}
	return m.HasModifier("private")
}

func (m Match) IsUnusedPrivateMember() bool {
	if m.DeclaredName() == "" || !m.isPrivateMember() || m.IsImplicit() || m.IsOverride() || m.IsConstructor() {
		return false
	}
	if m.Is(Function) {
		return m.methodOwner() != "" && len(m.Referrers()) == 0 && len(m.Readers()) == 0
	}
	if read := m.grammar().Collaborator; read != nil && read(m) {
		return false
	}
	return m.IsUnreadMember()
}

func (m Match) IsUnreadCollaborator() bool {
	read := m.grammar().Collaborator
	return read != nil && read(m) && m.IsUnreadMember()
}

func (m Match) IsStateFreeGetter() bool {
	read := m.grammar().Getter
	if read == nil || !read(m) || m.IsOverride() {
		return false
	}
	return !slices.ContainsFunc(m.OwnDescendants(), func(node Match) bool {
		return node.isSelfReceiver() || fieldOnSelf(node).Exists()
	})
}

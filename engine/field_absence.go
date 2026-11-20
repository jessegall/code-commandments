package engine

import "slices"

func (m Match) MasksFieldInvariant() bool {
	if read := m.grammar().MaskedInvariant; read != nil {
		return read(m)
	}
	fallback := m.Fallback()
	if !fallback.Is(Literal) || fallback.LiteralKind() == "null" || fallback.LiteralKind() == "undefined" {
		return false
	}
	parts := m.Children()
	if len(parts) != 2 {
		return false
	}
	read := parts[0]
	if !read.Is(NullSafe) && !slices.ContainsFunc(read.Descendants(), func(link Match) bool { return link.Is(NullSafe) }) {
		return false
	}
	field := fieldOnSelf(read)
	if !field.Exists() || !fieldDeclaration(field).AdmitsAbsence() {
		return false
	}
	return setsFieldOutsideConstructor(m.EnclosingType(), field.NameRead())
}

func fieldOnSelf(node Match) Match {
	for read := node; read.Exists(); read = read.Receiver() {
		if read.Is(MemberAccess) && read.Receiver().isSelfReceiver() {
			return read
		}
	}
	return Match{}
}

func (m Match) isSelfReceiver() bool {
	if m.Is(SelfReference) {
		return true
	}
	read := m.grammar().SelfReceiver
	return read != nil && read(m)
}

func fieldDeclaration(read Match) Match {
	if declared := read.Declared(); declared.Exists() {
		return declared
	}
	for _, member := range read.EnclosingType().Members() {
		if member.Name() == read.NameRead() {
			return member
		}
		for _, child := range member.Children() {
			if child.Name() == read.NameRead() && child.AdmitsAbsence() {
				return child
			}
		}
	}
	return Match{}
}

func setsFieldOutsideConstructor(owner Match, name string) bool {
	for _, method := range owner.Members() {
		if !method.Is(Function) || method.IsConstructor() {
			continue
		}
		for _, node := range method.OwnDescendants() {
			if !node.Is(Assignment) {
				continue
			}
			parts := node.Children()
			if len(parts) > 0 && fieldOnSelf(parts[0]).NameRead() == name {
				return true
			}
		}
	}
	return false
}

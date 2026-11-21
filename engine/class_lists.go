package engine

import "slices"

func (m Match) ConcreteClassReference() string {
	name := m.Named()
	if name != "" && slices.ContainsFunc(m.Codebase().Declarations(name), Match.IsConcreteClass) {
		return name
	}

	return ""
}

func (m Match) IsConcreteClass() bool {
	return m.TypeKind() == "class" && !m.HasModifier("abstract")
}

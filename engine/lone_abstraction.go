package engine

func (m Match) IsLoneAbstraction() bool {
	read := m.grammar()
	types, typed := m.Types()
	if m.TypeKind() != "interface" || !typed || !read.DeclaresMethods(m) {
		return false
	}
	implementers := Implementers(types, m.Identity())
	if len(implementers) != 1 || implementers[0].TypeKind() != "class" {
		return false
	}
	language := m.file.Language()
	module := ModuleOf(language, implementers[0].Identity())
	for _, namespace := range append(read.NamespacesNaming(m), read.NamespaceOf(m.Identity())) {
		if ModuleOf(language, namespace) != module {
			return false
		}
	}

	return true
}

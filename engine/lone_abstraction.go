package engine

import "slices"

func (m Match) IsLoneAbstraction() bool {
	read := m.grammar()
	types, typed := m.Types()
	if !m.IsInterfaceType() || !typed || !m.declaresMethods() {
		return false
	}
	implementers := Implementers(types, m.Identity())
	implementers = slices.DeleteFunc(implementers, Match.IsInterfaceType)
	if len(implementers) != 1 || implementers[0].IsTest() || implementers[0].IsInDependency() {
		return false
	}
	module := implementers[0].moduleName()
	if module == "" || m.moduleName() != module {
		return false
	}
	if read.NamespacesNaming != nil {
		for _, namespace := range read.NamespacesNaming(m) {
			if ModuleOf(m.file.Language(), namespace) != module {
				return false
			}
		}
		return true
	}
	for _, reference := range m.Referrers() {
		if !reference.IsTest() && reference.moduleName() != module {
			return false
		}
	}
	return true
}

func (m Match) IsInterfaceType() bool {
	kind := m.TypeKind()
	return kind == "interface" || kind == "protocol"
}

func (m Match) declaresMethods() bool {
	if read := m.grammar().DeclaresMethods; read != nil {
		return read(m)
	}
	return slices.ContainsFunc(m.Members(), func(member Match) bool { return member.Is(Function) })
}

func (m Match) moduleName() string {
	if !m.Exists() {
		return ""
	}
	if read := m.grammar().ModuleLayer; read != nil {
		return read(m.Root())
	}
	if m.grammar().NamespaceOf != nil {
		return ModuleOf(m.file.Language(), m.Namespace())
	}
	return ModuleOf(m.file.Language(), outermostSymbol(m))
}

package engine

func (m Match) InjectsAnotherModulesConcreteService() bool {
	read := m.grammar()
	if !m.Is(Parameter) || read.IsConstructor == nil || !read.IsConstructor(m.Parent()) || read.HoldsPublicState(m) {
		return false
	}
	types, typed := m.Types()
	if !typed {
		return false
	}
	class, declared := types.Declaration(read.DeclaredClass(m))
	if !declared || !class.IsConcreteService() {
		return false
	}
	language := m.file.Language()

	return ModuleOf(language, class.Identity()) != ModuleOf(language, m.EnclosingType().Identity())
}

func (m Match) IsConcreteService() bool {
	read := m.grammar()

	return m.TypeKind() == "class" && !m.HasModifier("abstract") && !read.IsValueClass(m) && !read.IsRecord(m)
}

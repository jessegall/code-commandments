package engine

func (m Match) InjectsAnotherModulesConcreteService() bool {
	read := m.grammar()
	constructor := m.DeclaringFunction()
	if !m.Is(Parameter) || !constructor.IsConstructor() || read.HoldsPublicState != nil && read.HoldsPublicState(m) {
		return false
	}
	class := m.typeDeclaration()
	if read.DeclaredClass != nil {
		if types, known := m.Types(); known {
			class, _ = types.Declaration(read.DeclaredClass(m))
		}
	}
	if !class.IsConcreteService() {
		return false
	}
	return class.moduleName() != constructor.moduleName()
}

func (m Match) IsConcreteService() bool {
	read := m.grammar()
	kind := m.TypeKind()
	return (kind == "class" || kind == "struct") && !m.IsInDependency() && !m.HasModifier("abstract") &&
		(read.IsValueClass == nil || !read.IsValueClass(m)) && (read.IsRecord == nil || !read.IsRecord(m))
}

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
	owner, supplier := constructor.moduleName(), class.moduleName()
	if owner == "" || supplier == "" || owner == supplier {
		return false
	}
	if read.SameModule != nil && read.SameModule(constructor, class) {
		return false
	}

	return class.IsConcreteService() && (read.ReceivesContext == nil || !read.ReceivesContext(m, class))
}

func (m Match) IsConcreteService() bool {
	read := m.grammar()
	kind := m.TypeKind()
	return (kind == "class" || kind == "struct") && !m.IsInDependency() && !m.HasModifier("abstract") &&
		(read.IsValueClass == nil || !read.IsValueClass(m)) && (read.IsRecord == nil || !read.IsRecord(m))
}

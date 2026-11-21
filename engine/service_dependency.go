package engine

func (m Match) InjectsAnotherModulesConcreteService() bool {
	constructor := m.DeclaringFunction()
	if !m.Is(Parameter) || !constructor.IsConstructor() {
		return false
	}
	class := m.typeDeclaration()
	owner, supplier := constructor.moduleName(), class.moduleName()
	if owner == "" || supplier == "" || owner == supplier {
		return false
	}
	return class.IsConcreteService()
}

func (m Match) IsConcreteService() bool {
	kind := m.TypeKind()
	return (kind == "class" || kind == "struct") && !m.IsInDependency() && !m.HasModifier("abstract")
}

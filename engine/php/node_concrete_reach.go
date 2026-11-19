package php

func (n Node) InjectsAnotherModulesConcreteService() bool {
	method := n.Parent()
	if n.Kind() != "Param" || method.Kind() != "Stmt_ClassMethod" || method.Name() != "__construct" || n.HasModifier("public") {
		return false
	}
	class := Written(n.Node().Declared).Class()
	program := ProgramOf(n.Codebase())
	declaration, declared := program.Declaration(class)
	if !declared || declaration.Kind() != "Stmt_Class" || declaration.HasModifier("abstract") {
		return false
	}

	return !program.ClassIsValueType(class) && !program.ClassIsRecord(class) && ModuleOf(class) != ModuleOf(EnclosingClassName(n.Match))
}

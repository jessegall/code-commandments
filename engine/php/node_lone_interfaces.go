package php

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

func (n Node) IsLoneAbstraction() bool {
	if n.Kind() != "Stmt_Interface" || !slices.ContainsFunc(n.ChildrenIn("stmts"), func(member engine.Match) bool { return member.Kind() == "Stmt_ClassMethod" }) {
		return false
	}
	program, contract := ProgramOf(n.Codebase()), n.Node().Symbol
	implementer, lone := program.loneImplementer(contract)
	if !lone {
		return false
	}
	module := ModuleOf(implementer)
	for _, namespace := range append(TypeReferencesOf(n.Codebase()).NamespacesNaming(contract), NamespaceOf(contract)) {
		if ModuleOf(namespace) != module {
			return false
		}
	}

	return true
}

func (p *Program) loneImplementer(contract string) (string, bool) {
	var honouring []string
	for name, declaration := range p.Declarations() {
		if name == contract || !slices.Contains(p.Contracts(name), contract) {
			continue
		}
		honouring = append(honouring, name)
		if declaration.Kind() != "Stmt_Class" || len(honouring) > 1 {
			return "", false
		}
	}

	return firstOf(honouring)
}

func firstOf(names []string) (string, bool) {
	if len(names) == 0 {
		return "", false
	}

	return names[0], true
}

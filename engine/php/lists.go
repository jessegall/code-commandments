package php

import (
	"github.com/jessegall/code-commandments/contract"
	"github.com/jessegall/code-commandments/engine"
)

func init() {
	engine.ListAs(contract.PHP, engine.Lists{
		Arguments:     engine.InFields("args"),
		Members:       engine.InFields("stmts"),
		Extends:       engine.InFields("extends"),
		Implements:    engine.InFields("implements"),
		Annotations:   attributes,
		TypeKind:      engine.Kinds(map[string]string{"Stmt_Class": "class", "Stmt_Interface": "interface", "Stmt_Enum": "enum", "Stmt_Trait": "trait"}),
		ReturnType:    engine.InField("returnType"),
		ParameterType: engine.InField("type"),
	})
}

// attributes are the names of the attributes a declaration carries, in every group.
func attributes(declaration engine.Match) []engine.Match {
	var names []engine.Match
	for _, group := range declaration.ChildrenIn("attrGroups") {
		for _, attribute := range group.ChildrenIn("attrs") {
			names = append(names, attribute.Child("name"))
		}
	}

	return names
}

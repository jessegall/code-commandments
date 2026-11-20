package php

import "github.com/jessegall/code-commandments/engine"

func nullableFieldDeclarations(class engine.Match) []engine.Match {
	var fields []engine.Match
	for _, param := range ConstructorParams(class) {
		if len(param.Node().Modifiers) > 0 && Written(param.Node().Declared).IsNullable() && phantomFieldName(param) != "" {
			fields = append(fields, param)
		}
	}
	for _, member := range (Node{Match: class}).ChildrenIn("stmts") {
		if member.Kind() != "Stmt_Property" || !Written(member.Node().Declared).IsNullable() {
			continue
		}
		fields = append(fields, (Node{Match: member}).ChildrenIn("props")...)
	}

	return fields
}

func phantomFieldName(field engine.Match) string {
	switch field.Kind() {
	case "Param":
		return field.Child("var").Name()
	case "PropertyItem":
		return field.Name()
	}

	return ""
}

func phantomFields(class engine.Match) []engine.Match {
	if class.Kind() != "Stmt_Class" {
		return nil
	}
	flow := ValueFlowOf(class.Codebase())
	var fields []engine.Match
	for _, field := range nullableFieldDeclarations(class) {
		if (Node{Match: field}).DeclaresNullableWireType() {
			continue
		}
		verdict := flow.Verdict(class.Identity(), phantomFieldName(field))
		if verdict.Assume > 0 && verdict.Guard == 0 {
			fields = append(fields, field)
		}
	}
	return fields
}
func absenceTrace(finding engine.Match) []string {
	field := phantomFieldName(finding)
	if field == "" {
		return nil
	}
	return ValueFlowOf(finding.Codebase()).ChainPath(EnclosingClassName(finding), field)
}
func nullableLookup(match engine.Match) bool {
	node := Node{Match: match}
	return node.IsCoalesce() && node.IsReturnedValue() && node.CoalesceRight().IsNull() && node.CoalesceLeft().IsOwnedKeyedLookup() && !node.IsFallbackOfAFirstMatch() && !ProgramOf(match.Codebase()).OverridesMethod(EnclosingClassName(match), EnclosingFunctionName(match))
}

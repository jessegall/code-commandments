package typescript

import "github.com/jessegall/code-commandments/engine"

func EnumValues(declared engine.Match) []string {
	var values []string
	switch declared.Kind() {
	case "EnumDeclaration":
		for _, member := range declared.ChildrenIn("members") {
			literal := member.Child("initializer")
			if value, text := literal.Text(); text && literal.LiteralKind() == "string" {
				values = append(values, value)
			}
		}
	case "TypeAliasDeclaration":
		union := declared.Child("type")
		if union.Kind() != "UnionType" {
			return nil
		}
		for _, variant := range union.ChildrenIn("types") {
			literal := variant.Child("literal")
			text, stringValue := literal.Text()
			if !stringValue || literal.LiteralKind() != "string" {
				return nil
			}
			values = append(values, text)
		}
	}
	return values
}

func EnumCase(reference engine.Match) (string, string) {
	if reference.Kind() != "PropertyAccessExpression" {
		return "", ""
	}
	owner := reference.Receiver().Refers()
	if owner == "" {
		return "", ""
	}
	for _, declaration := range reference.Codebase().Declarations(owner) {
		if declaration.Kind() != "EnumDeclaration" {
			continue
		}
		for _, member := range declaration.ChildrenIn("members") {
			if member.Name() == reference.NameRead() {
				return owner, member.Name()
			}
		}
	}
	return "", ""
}

func EnumMembership(call engine.Match) []string {
	if !call.Is(engine.Call) || call.CalleeName() != "includes" || call.Receiver().Kind() != "ArrayLiteralExpression" {
		return nil
	}
	return stringsOf(call.Receiver().ChildrenIn("elements"))
}

func EnumArms(statement engine.Match) []string {
	if statement.Kind() != "SwitchStatement" {
		return nil
	}
	var arms []engine.Match
	for _, clause := range statement.Child("caseBlock").ChildrenIn("clauses") {
		if clause.Kind() == "CaseClause" {
			arms = append(arms, clause.Child("expression"))
		}
	}
	return stringsOf(arms)
}

func stringsOf(expressions []engine.Match) []string {
	var values []string
	for _, expression := range expressions {
		text, literal := expression.Text()
		if !literal || expression.LiteralKind() != "string" {
			return nil
		}
		values = append(values, text)
	}
	return values
}

package typescript

import "github.com/jessegall/code-commandments/engine"

func SideTables(codebase *engine.Codebase) []engine.Match {
	return (engine.SideTableReading{
		Declared: In(codebase).WhereKind("VariableDeclaration").Where(engine.As(Node.IsModuleLevelDeclaration)).Get(),
		Checked:  In(codebase).WhereKind("CallExpression").Get(),
		Registered: func(m engine.Match) []string {
			var registered []string
			for _, names := range (Node{Match: m}).KeyedInstanceNames() {
				registered = append(registered, names...)
			}
			return registered
		},
		Listed:    func(m engine.Match) []string { return (Node{Match: m}).StringMembers() },
		Name:      engine.Match.Name,
		AsksAbout: func(m engine.Match, name string) bool { return (Node{Match: m}).AsksAboutOne(name) },
	}).Findings()
}

func DispatchTest(test engine.Match) (engine.Match, string) {
	if test.Operator() != "instanceof" {
		return engine.Match{}, ""
	}
	class := test.Child("right").ConcreteClassReference()
	return test.Child("left"), class
}

func IsConstClassEnum(declared engine.Match) bool {
	if declared.TypeKind() != "class" || len(declared.Extends()) > 0 {
		return false
	}
	members := declared.Members()
	if len(members) < 2 {
		return false
	}
	for _, member := range members {
		if member.Kind() != "PropertyDeclaration" || !member.HasModifier("static") || !member.HasModifier("readonly") || !member.Child("initializer").Is(engine.Literal) {
			return false
		}
	}
	for _, reference := range declared.Referrers() {
		access := reference.Parent()
		if access.Kind() == "PropertyAccessExpression" && access.Receiver().Node() == reference.Node() &&
			(access.Parent().Is(engine.Comparison) || access.Parent().Kind() == "CaseClause") {
			return true
		}
	}
	return false
}

func KeepsEnumSideTable(declared engine.Match) bool {
	if declared.Kind() != "VariableDeclaration" || !declared.HasFlag("constant") || declared.Node().Symbol == "" {
		return false
	}
	literal := declared.Child("initializer")
	if literal.Kind() != "ArrayLiteralExpression" {
		return false
	}
	owner := ""
	names := map[string]bool{}
	for _, entry := range literal.ChildrenIn("elements") {
		symbol, name := EnumCase(entry)
		if symbol == "" || owner != "" && owner != symbol {
			return false
		}
		owner = symbol
		names[name] = true
	}
	if len(names) < 2 {
		return false
	}
	for _, enum := range declared.Codebase().Declarations(owner) {
		if len(names) < len(enum.ChildrenIn("members")) {
			for _, reference := range declared.Referrers() {
				access := reference.Parent()
				if access.Parent().Is(engine.Call) && access.NameRead() == "includes" && access.Receiver().Node() == reference.Node() {
					return true
				}
			}
		}
	}
	return false
}

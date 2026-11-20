package csharp

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

func typeDispatch(m engine.Match) bool {
	n := Node{Match: m}
	switched := n.SwitchedTypes()
	seen := map[string]bool{}
	for _, name := range switched {
		seen[name] = true
	}
	if len(seen) < 2 || n.IsTranslatingEveryArm() || n.StoresInOwnState() || n.IsSwitchOverOwnCases() || n.IsWithinNamedConstructor() {
		return false
	}
	program := Of(m.Codebase())
	return program.DeclaresType(n.SwitchedSubjectType()) && !slices.ContainsFunc(switched, func(class string) bool { return !program.DeclaresType(class) })
}
func sideTables(codebase *engine.Codebase) []engine.Match {
	return (engine.SideTableReading{
		Declared: In(codebase).WhereKind("VariableDeclarator").Where(engine.As(Node.IsFieldDeclarator)).Get(), Checked: In(codebase).WhereKind("InvocationExpression").Get(),
		Registered: func(m engine.Match) []string { _, names := (Node{Match: m}).InstanceNames(); return names },
		Listed:     func(m engine.Match) []string { return (Node{Match: m}).StringMembers() },
		Name:       engine.Match.Name,
		AsksAbout:  func(m engine.Match, name string) bool { return (Node{Match: m}).AsksAboutOne(name) },
	}).Findings()
}

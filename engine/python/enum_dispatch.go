package python

import (
	"slices"

	"github.com/jessegall/code-commandments/engine"
)

func typeDispatch(m engine.Match) bool {
	n := Node{Match: m}
	if !n.IsCall() || !n.IsEvaluated() || !n.IsTypeSwitchHead() || n.IsInDunder() || n.IsWithinNamedConstructor() || n.TypeSwitchTranslatesEveryArm() {
		return false
	}
	program := Of(m.Codebase())
	return !slices.ContainsFunc(n.TypeSwitchClasses(), func(class string) bool { return !program.DeclaresClass(class) })
}
func sideTables(codebase *engine.Codebase) []engine.Match {
	return (engine.SideTableReading{
		Declared: In(codebase).WhereAssign().Where(engine.As(Node.IsModuleLevelAssign)).Get(), Checked: In(codebase).WhereKind("Compare").Get(),
		Registered: func(m engine.Match) []string { _, names := (Node{Match: m}).InstanceNames(); return names },
		Listed:     func(m engine.Match) []string { return (Node{Match: m}).StringMembers() },
		Name:       func(m engine.Match) string { return (Node{Match: m}).AssignedName() },
		AsksAbout: func(m engine.Match, name string) bool {
			return slices.ContainsFunc(m.Children(), func(child engine.Match) bool { return child.Kind() == "Name" && child.Name() == name })
		},
	}).Findings()
}

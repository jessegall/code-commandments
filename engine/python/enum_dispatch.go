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

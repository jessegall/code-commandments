package php

import (
	"github.com/jessegall/code-commandments/engine"
)

func typeDispatch(m engine.Match) bool {
	n := Node{Match: m}
	if !n.IsTypeSwitchHead() || n.IsInFromSourceFactory() || n.TypeSwitchTranslatesEveryArm() {
		return false
	}
	program := ProgramOf(m.Codebase())
	for _, class := range n.TypeSwitchClasses() {
		if _, known := program.Declaration(class); !known {
			return false
		}
	}
	return true
}

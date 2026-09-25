package csharp

import (
	"slices"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// TypeSwitchDetector finds a switch asking which of two or more of the codebase's own types a value of its own type is, outside a mapper, a closed union and a named constructor.
type TypeSwitchDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, TypeSwitchDetector{})
}

// Sin is the sin the detector finds.
func (TypeSwitchDetector) Sin() sins.Sin {
	return cssins.TypeSwitch{}
}

// Find is every place the sin is committed.
func (TypeSwitchDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := cs.In(codebase).Program

	return cs.In(codebase).
		Where(engine.As(func(n cs.Node) bool { return distinct(n.SwitchedTypes()) >= 2 })).
		Where(engine.As(func(n cs.Node) bool {
			return !slices.ContainsFunc(n.SwitchedTypes(), func(switched string) bool { return !program.DeclaresType(switched) })
		})).
		Where(engine.As(func(n cs.Node) bool { return program.DeclaresType(n.SwitchedSubjectType()) })).
		Reject(engine.As(cs.Node.IsTranslatingEveryArm)).
		Reject(engine.As(cs.Node.IsSwitchOverOwnCases)).
		Reject(engine.As(cs.Node.IsWithinNamedConstructor)).
		Get()
}

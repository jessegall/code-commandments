package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// StringMirrorsEnumDetector finds a switch or `if` ladder dispatching on two or more strings that all spell members of one of the codebase's enums.
type StringMirrorsEnumDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, StringMirrorsEnumDetector{})
}

// Sin is the sin the detector finds.
func (StringMirrorsEnumDetector) Sin() sins.Sin {
	return cssins.StringMirrorsEnum{}
}

// Find is every place the sin is committed.
func (StringMirrorsEnumDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := cs.In(codebase).Program

	return cs.In(codebase).
		WhereKind("SwitchStatement", "SwitchExpression", "IfStatement").
		Where(engine.As(func(n cs.Node) bool { return program.EnumMirroredBy(n.ComparedLiterals()) })).
		Get()
}

// CrossFile says the rule reads more than the file it reports in, which the fixture measures by asking it
// of one file and of the whole tree: a scoped run reads the whole scan while it is enabled.
func (StringMirrorsEnumDetector) CrossFile() {}

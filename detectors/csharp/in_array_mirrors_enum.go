package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// InArrayMirrorsEnumDetector finds a membership test against two or more strings that all spell members of one of the codebase's enums.
type InArrayMirrorsEnumDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, InArrayMirrorsEnumDetector{})
}

// Sin is the sin the detector finds.
func (InArrayMirrorsEnumDetector) Sin() sins.Sin {
	return cssins.InArrayMirrorsEnum{}
}

// Find is every place the sin is committed.
func (InArrayMirrorsEnumDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := cs.In(codebase).Program

	return cs.In(codebase).
		WhereKind("InvocationExpression", "IsPatternExpression").
		Where(engine.As(func(n cs.Node) bool { return program.EnumMirroredBy(n.MembershipLiterals()) })).
		Get()
}

// CrossFile says the rule reads more than the file it reports in, which the fixture measures by asking it
// of one file and of the whole tree: a scoped run reads the whole scan while it is enabled.
func (InArrayMirrorsEnumDetector) CrossFile() {}

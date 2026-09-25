package csharp

import (
	"strings"

	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// DeNulledFinderDetector finds a method of the codebase's own declaring a nullable object result that every caller, two or more outside the tests, asserts is there.
type DeNulledFinderDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, DeNulledFinderDetector{})
}

// Sin is the sin the detector finds.
func (DeNulledFinderDetector) Sin() sins.Sin {
	return cssins.DeNulledFinder{}
}

// Find is every place the sin is committed.
func (DeNulledFinderDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := cs.In(codebase).Program

	return cs.In(codebase).
		WhereMethodDeclaration().
		Reject(engine.As(cs.Node.IsInherited)).
		Where(engine.As(func(n cs.Node) bool { return n.DeclaredType().IsNullable() })).
		Where(engine.As(func(n cs.Node) bool { return n.DeclaredType().Exists() && !n.DeclaredType().IsValueType() })).
		Reject(engine.As(func(n cs.Node) bool {
			return strings.TrimSuffix(n.DeclaredType().Name(), "?") == "global::System.String"
		})).
		Where(engine.As(func(n cs.Node) bool { return isDeNulledByEveryCaller(n, program) })).
		Get()
}

// isDeNulledByEveryCaller says whether two or more calls outside the tests reach the finder, and every one asserts
// its result is there.
func isDeNulledByEveryCaller(finder cs.Node, program *cs.Program) bool {
	calls := program.CallsTo(finder)

	return calls.OutsideTests >= 2 && calls.Asserting == calls.OutsideTests
}

// CrossFile says the PHP tool finds it reaching beyond the file it judges, so a per-file check must not ask it.
func (DeNulledFinderDetector) CrossFile() {}

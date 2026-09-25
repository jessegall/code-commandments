package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// FeatureEnvyDetector finds a method reaching through one other object it was handed more than its own state, looping or writing it: behaviour exiled from its data.
type FeatureEnvyDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, FeatureEnvyDetector{})
}

// Sin is the sin the detector finds.
func (FeatureEnvyDetector) Sin() sins.Sin {
	return cssins.FeatureEnvy{}
}

// Find is every place the sin is committed.
func (FeatureEnvyDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := cs.In(codebase).Program

	return cs.In(codebase).
		WhereMethodDeclaration().
		Where(engine.As(func(n cs.Node) bool { return n.EnviedParameter(program) != "" })).
		Get()
}

// CrossFile says the PHP tool finds it reaching beyond the file it judges, so a per-file check must not ask it.
func (FeatureEnvyDetector) CrossFile() {}

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

// EnumCaseOrChainDetector finds an `||` chain or `or` pattern testing two or more cases of one of the codebase's enums: a per-case fact kept outside the enum.
type EnumCaseOrChainDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, EnumCaseOrChainDetector{})
}

// Sin is the sin the detector finds.
func (EnumCaseOrChainDetector) Sin() sins.Sin {
	return cssins.EnumCaseOrChain{}
}

// Find is every place the sin is committed.
func (EnumCaseOrChainDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := cs.In(codebase).Program

	return cs.In(codebase).
		WhereKind("LogicalOrExpression", "OrPattern").
		Where(engine.As(cs.Node.IsGroupTestRoot)).
		Where(engine.As(func(n cs.Node) bool { return slices.ContainsFunc(n.EnumsTestedAsAGroup(), program.DeclaresEnum) })).
		Get()
}

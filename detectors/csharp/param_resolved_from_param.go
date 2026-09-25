package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// ParamResolvedFromParamDetector finds a method looking its target up in a container parameter by a key parameter, and then using the container for nothing else: it should be handed the target.
type ParamResolvedFromParamDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, ParamResolvedFromParamDetector{})
}

// Sin is the sin the detector finds.
func (ParamResolvedFromParamDetector) Sin() sins.Sin {
	return cssins.ParamResolvedFromParam{}
}

// Find is every place the sin is committed.
func (ParamResolvedFromParamDetector) Find(codebase *engine.Codebase) []engine.Match {
	program := cs.In(codebase).Program

	return cs.In(codebase).
		WhereMethodDeclaration().
		Where(engine.As(func(n cs.Node) bool { return n.UnpacksTargetFromContainerParam(program) })).
		Get()
}

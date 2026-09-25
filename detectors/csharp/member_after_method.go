package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// MemberAfterMethodDetector finds a field, constant or stored property declared below one of its type's constructors or methods.
type MemberAfterMethodDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, MemberAfterMethodDetector{})
}

// Sin is the sin the detector finds.
func (MemberAfterMethodDetector) Sin() sins.Sin {
	return cssins.MemberAfterMethod{}
}

// Find is every place the sin is committed.
func (MemberAfterMethodDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		Where(engine.As(cs.Node.IsStateMember)).
		Where(engine.As(cs.Node.IsMemberAfterMethod)).
		Get()
}

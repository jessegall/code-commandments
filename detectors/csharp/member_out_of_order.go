package csharp

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/detectors"
	"github.com/jessegall/code-commandments/engine"
	cs "github.com/jessegall/code-commandments/engine/csharp"
	"github.com/jessegall/code-commandments/sins"
	cssins "github.com/jessegall/code-commandments/sins/csharp"
)

// MemberOutOfOrderDetector finds a constant declared below one of its type's instance fields or stored properties.
type MemberOutOfOrderDetector struct{}

func init() {
	detectors.Register(catalog.CSharp, MemberOutOfOrderDetector{})
}

// Sin is the sin the detector finds.
func (MemberOutOfOrderDetector) Sin() sins.Sin {
	return cssins.MemberOutOfOrder{}
}

// Find is every place the sin is committed.
func (MemberOutOfOrderDetector) Find(codebase *engine.Codebase) []engine.Match {
	return cs.In(codebase).
		Where(engine.As(cs.Node.IsConstantMember)).
		Where(engine.As(cs.Node.IsMemberOutOfOrder)).
		Get()
}

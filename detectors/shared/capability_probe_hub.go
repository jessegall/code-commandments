package shared

import (
	"github.com/jessegall/code-commandments/engine"
	skills "github.com/jessegall/code-commandments/skill/shared"
)

// CapabilityProbeHub finds a type that repeatedly tests one field for optional capabilities.
type CapabilityProbeHub struct{}

func (CapabilityProbeHub) Find(codebase *engine.Codebase) []engine.Match {
	return codebase.WhereTypeDeclaration().Where(engine.Match.ProbesSeveralCapabilities).Reject(engine.Match.IsTest).Get()
}

func (CapabilityProbeHub) Sin() Text {
	return Text{
		Name:        "capability-probe-hub",
		Skill:       skills.Absence{},
		Description: "Several methods each ask the same field if it has a capability before using it",
		Rule:        "Decide which capabilities an object provides when giving it to its owner.",
		Suggestion:  "Give the owner a typed capability or an adapter that supplies the required behavior.",
	}
}

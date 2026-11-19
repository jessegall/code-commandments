package backend

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	skills "github.com/jessegall/code-commandments/skill/backend"
)

// CapabilityProbeHub is the capability-probe-hub sin.
type CapabilityProbeHub struct{}

func init() { sins.Register(catalog.PHP, CapabilityProbeHub{}) }

func (CapabilityProbeHub) Definition() sins.Definition {
	return sins.Definition{
		Name:        "capability-probe-hub",
		Skill:       skills.TellDontAsk{},
		Description: "Three or more methods each ask whether one held object implements an interface, then call it or answer empty: one accessor per optional capability",
		Rule:        "An optional capability is asked for by its contract in one place; a hub does not grow an `instanceof` accessor for every capability added.",
		Suggestion:  "Replace the accessors with one `provided(Contract::class)` lookup, or have the held object declare its capabilities as a typed list.",
	}
}

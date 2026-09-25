package spatie

import (
	"github.com/jessegall/code-commandments/catalog"
	"github.com/jessegall/code-commandments/sins"
	spatieskills "github.com/jessegall/code-commandments/skill/backend/spatie"
)

// RedundantEnumUnwrap is the redundant-enum-unwrap sin.
type RedundantEnumUnwrap struct{}

func init() { sins.Register(catalog.Backend, RedundantEnumUnwrap{}) }

// Definition is what the sin states about itself.
func (RedundantEnumUnwrap) Definition() sins.Definition {
	return sins.Definition{
		Name:        "redundant-enum-unwrap",
		Skill:       spatieskills.SpatieDataHydration{},
		Description: `An enum is unwrapped to ` + "`" + `->value` + "`" + ` at a hydration site (` + "`" + `'status' => $order->status->value` + "`" + `) where the property is typed as that enum — Spatie re-casts the scalar straight back to the enum`,
		Rule:        `Pass the enum itself to an enum slot — Spatie's enum cast keeps it; don't destructure it to ` + "`" + `->value` + "`" + ` at the hydration site only for it to be re-hydrated.`,
		Suggestion:  "`'status' => $order->status`, not `'status' => $order->status->value`.",
		Requires:    requiresSpatieData,
	}
}
